<?php
/**
 * Clock In Class
 * Handles daily staff clock-in with device verification and anti-proxy measures
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Clock_In {
    
    /** Cooldown in seconds between consecutive clock-ins on the same device */
    const COOLDOWN_SECONDS = 60;
    
    /**
     * Normalize an IP address for reliable comparison.
     * Strips IPv4-mapped IPv6 prefix (::ffff:), trims whitespace, lowercases.
     */
    private static function normalize_ip($ip) {
        $ip = trim($ip);
        // Strip IPv4-mapped IPv6 prefix
        if (stripos($ip, '::ffff:') === 0) {
            $ip = substr($ip, 7);
        }
        return strtolower($ip);
    }
    
    /**
     * Verify that the request comes from the allowed device
     */
    public static function verify_device($device_ip, $device_build) {
        $allowed_ip = get_option('stand120_allowed_device_ip', '');
        $allowed_build = get_option('stand120_allowed_device_build', '');
        
        // If not configured, skip device check (allow admin to set up first)
        if (empty($allowed_ip) && empty($allowed_build)) {
            return array('valid' => true, 'message' => 'Device verification not configured');
        }
        
        if (!empty($allowed_ip)) {
            $normalized_client = self::normalize_ip($device_ip);
            $normalized_allowed = self::normalize_ip($allowed_ip);
            
            if ($normalized_client !== $normalized_allowed) {
                return array(
                    'valid' => false,
                    'message' => 'Clock-in is only allowed from the designated device. Your detected IP: ' . $device_ip
                );
            }
        }
        
        if (!empty($allowed_build) && $device_build !== $allowed_build) {
            return array('valid' => false, 'message' => 'Unrecognized device build number');
        }
        
        return array('valid' => true, 'message' => 'Device verified');
    }
    
    /**
     * Re-authenticate user with password before clock-in
     */
    public static function verify_password($password) {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return false;
        }
        return wp_check_password($password, $user->user_pass, $user->ID);
    }
    
    /**
     * Submit a clock-in
     */
    public static function submit($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_clock_in';
        
        $password = $data['password'] ?? '';
        $device_build = sanitize_text_field($data['device_build'] ?? '');
        
        $staff_id = Stand120_Auth::get_current_staff_id();
        if (!$staff_id) {
            return array('success' => false, 'message' => 'Staff record not found');
        }
        
        // Re-authenticate with password
        if (empty($password)) {
            return array('success' => false, 'message' => 'Password is required to clock in');
        }
        
        if (!self::verify_password($password)) {
            return array('success' => false, 'message' => 'Incorrect password. You can only clock in for yourself.');
        }
        
        // Get client IP
        $client_ip = self::get_client_ip();
        
        // Verify device
        $device_check = self::verify_device($client_ip, $device_build);
        if (!$device_check['valid']) {
            return array('success' => false, 'message' => $device_check['message']);
        }
        
        // Check cooldown - prevent rapid sequential clock-ins
        $cooldown_check = self::check_cooldown();
        if (!$cooldown_check['allowed']) {
            return array('success' => false, 'message' => $cooldown_check['message']);
        }
        
        $today = current_time('Y-m-d');
        $now_time = current_time('H:i:s');
        
        // Check if already clocked in today
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE staff_id = %d AND clock_date = %s",
            $staff_id, $today
        ));
        
        if ($existing) {
            return array(
                'success' => false,
                'message' => 'Already clocked in today at ' . date('g:i A', strtotime($existing->clock_time))
            );
        }
        
        // Insert clock-in record
        $result = $wpdb->insert($table, array(
            'staff_id' => $staff_id,
            'clock_date' => $today,
            'clock_time' => $now_time,
            'device_ip' => $client_ip,
            'device_build' => $device_build
        ), array('%d', '%s', '%s', '%s', '%s'));
        
        if ($result === false) {
            return array('success' => false, 'message' => 'Failed to record clock-in');
        }
        
        Stand120_Database::log_activity('clock_in', 'stand120_clock_in', $wpdb->insert_id);
        
        // Get staff name for confirmation
        $staff_table = $wpdb->prefix . 'stand120_staff';
        $staff = $wpdb->get_row($wpdb->prepare(
            "SELECT full_name FROM $staff_table WHERE id = %d",
            $staff_id
        ));
        
        return array(
            'success' => true,
            'message' => 'Clock-in recorded successfully',
            'data' => array(
                'staff_name' => $staff ? $staff->full_name : 'Staff',
                'date' => $today,
                'time' => date('g:i A', strtotime($now_time))
            )
        );
    }
    
    /**
     * Check cooldown between consecutive clock-ins
     */
    private static function check_cooldown() {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_clock_in';
        $today = current_time('Y-m-d');
        
        // Get the most recent clock-in today from any staff
        $last = $wpdb->get_row($wpdb->prepare(
            "SELECT clock_time, created_at FROM $table WHERE clock_date = %s ORDER BY created_at DESC LIMIT 1",
            $today
        ));
        
        if (!$last) {
            return array('allowed' => true);
        }
        
        $last_timestamp = strtotime($last->created_at);
        $now_timestamp = current_time('timestamp');
        $diff = $now_timestamp - $last_timestamp;
        
        if ($diff < self::COOLDOWN_SECONDS) {
            $remaining = self::COOLDOWN_SECONDS - $diff;
            return array(
                'allowed' => false,
                'message' => "Please wait {$remaining} seconds before the next clock-in"
            );
        }
        
        return array('allowed' => true);
    }
    
    /**
     * Get today's clock-in status for current staff
     */
    public static function get_today_status() {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_clock_in';
        
        $staff_id = Stand120_Auth::get_current_staff_id();
        $today = current_time('Y-m-d');
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE staff_id = %d AND clock_date = %s",
            $staff_id, $today
        ));
        
        $server_time = current_time('Y-m-d H:i:s');
        
        if ($record) {
            return array(
                'clocked_in' => true,
                'clock_time' => date('g:i A', strtotime($record->clock_time)),
                'clock_date' => $record->clock_date,
                'server_time' => $server_time
            );
        }
        
        return array(
            'clocked_in' => false,
            'server_time' => $server_time
        );
    }
    
    /**
     * Get clock-in history (admin only)
     */
    public static function get_history($filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_clock_in';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $month = intval($filters['month'] ?? date('n'));
        $year = intval($filters['year'] ?? date('Y'));
        
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $late_threshold = get_option('stand120_late_threshold', '08:00');
        
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, s.full_name as staff_name 
             FROM $table c 
             JOIN $staff_table s ON c.staff_id = s.id 
             WHERE c.clock_date BETWEEN %s AND %s 
             ORDER BY c.clock_date DESC, c.clock_time ASC",
            $start_date, $end_date
        ));
        
        // Group by date
        $by_date = array();
        foreach ($records as $record) {
            $date = $record->clock_date;
            if (!isset($by_date[$date])) {
                $by_date[$date] = array();
            }
            $by_date[$date][] = array(
                'staff_id' => $record->staff_id,
                'staff_name' => $record->staff_name,
                'clock_time' => $record->clock_time,
                'formatted_time' => date('g:i A', strtotime($record->clock_time)),
                'is_late' => $record->clock_time > $late_threshold,
                'device_ip' => $record->device_ip
            );
        }
        
        // Get all active staff for absence tracking
        $all_staff = $wpdb->get_results(
            "SELECT id, full_name FROM $staff_table WHERE status = 'active' ORDER BY full_name ASC"
        );
        
        return array(
            'records' => $by_date,
            'all_staff' => $all_staff,
            'late_threshold' => $late_threshold,
            'month' => $month,
            'year' => $year
        );
    }
    
    /**
     * Export clock-in data as CSV
     */
    public static function get_export_data($filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'stand120_clock_in';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $month = intval($filters['month'] ?? date('n'));
        $year = intval($filters['year'] ?? date('Y'));
        
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $late_threshold = get_option('stand120_late_threshold', '08:00');
        
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT c.clock_date, c.clock_time, s.full_name as staff_name 
             FROM $table c 
             JOIN $staff_table s ON c.staff_id = s.id 
             WHERE c.clock_date BETWEEN %s AND %s 
             ORDER BY c.clock_date ASC, c.clock_time ASC",
            $start_date, $end_date
        ));
        
        $csv_rows = array();
        $csv_rows[] = array('Date', 'Staff Name', 'Clock-In Time', 'Status');
        
        foreach ($records as $record) {
            $is_late = $record->clock_time > $late_threshold;
            $csv_rows[] = array(
                $record->clock_date,
                $record->staff_name,
                date('g:i A', strtotime($record->clock_time)),
                $is_late ? 'Late' : 'On Time'
            );
        }
        
        return $csv_rows;
    }
    
    /**
     * Save device settings
     */
    public static function save_device_settings($data) {
        $ip = sanitize_text_field($data['device_ip'] ?? '');
        $build = sanitize_text_field($data['device_build'] ?? '');
        $late_threshold = sanitize_text_field($data['late_threshold'] ?? '08:00');
        
        update_option('stand120_allowed_device_ip', $ip);
        update_option('stand120_allowed_device_build', $build);
        update_option('stand120_late_threshold', $late_threshold);
        
        Stand120_Database::log_activity('update_clock_in_settings');
        
        return array('success' => true, 'message' => 'Clock-in device settings saved');
    }
    
    /**
     * Get device settings (includes the server-detected IP of the current request)
     */
    public static function get_device_settings() {
        return array(
            'device_ip' => get_option('stand120_allowed_device_ip', ''),
            'device_build' => get_option('stand120_allowed_device_build', ''),
            'late_threshold' => get_option('stand120_late_threshold', '08:00'),
            'current_ip' => self::get_client_ip()
        );
    }
    
    /**
     * Get client IP address
     */
    public static function get_client_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ip_list[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }
}