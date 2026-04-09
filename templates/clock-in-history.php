<?php
/**
 * Clock In History Page Template (Admin Only)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!Stand120_Auth::is_admin()) {
    wp_redirect(home_url('/120-stand/'));
    exit;
}

$page_title = 'Clock-In History - 120 Stand Inventory';
$current_user = Stand120_Auth::get_current_user_data();

include STAND120_PLUGIN_DIR . 'templates/partials/header.php';
?>

<!-- Staff Info Bar -->
<div class="staff-info-bar">
    <div class="staff-info">
        <div class="staff-avatar">
            <?php echo strtoupper(substr($current_user['display_name'], 0, 1)); ?>
        </div>
        <div class="staff-details">
            <h4><?php echo esc_html($current_user['display_name']); ?></h4>
            <span>Administrator</span>
        </div>
    </div>
    <div class="date-display">
        <i class="fas fa-history"></i>
        <span>Clock-In History</span>
    </div>
</div>

<div class="glass-card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
        <h3 style="color: var(--primary-color); margin: 0;">
            <i class="fas fa-clock"></i> Clock-In Records
        </h3>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <button id="prevMonth" class="btn btn-secondary" style="padding: 6px 12px;">
                <i class="fas fa-chevron-left"></i>
            </button>
            <span id="currentMonthLabel" style="font-weight: 600; min-width: 140px; text-align: center;"></span>
            <button id="nextMonth" class="btn btn-secondary" style="padding: 6px 12px;">
                <i class="fas fa-chevron-right"></i>
            </button>
            <button id="exportCsv" class="btn btn-success" style="padding: 6px 16px; margin-left: 8px;">
                <i class="fas fa-download"></i> Export CSV
            </button>
        </div>
    </div>
    
    <div id="clockInHistoryContent">
        <div class="loading-spinner" style="margin: 40px auto;"></div>
        <p style="text-align: center; color: var(--text-muted);">Loading records...</p>
    </div>
</div>

<!-- Device Settings Card (Admin) -->
<div class="glass-card" style="margin-top: 20px;">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <i class="fas fa-cog"></i> Clock-In Device Settings
    </h3>
    
    <div style="background: rgba(var(--primary-rgb, 110, 86, 207), 0.1); padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
        <strong><i class="fas fa-info-circle"></i> Your current detected IP:</strong>
        <span id="currentDetectedIp" style="font-family: monospace; font-weight: 600;">Loading...</span>
        <button type="button" id="useCurrentIp" class="btn btn-secondary" style="padding: 4px 12px; font-size: 0.85rem; margin-left: 8px;">
            <i class="fas fa-crosshairs"></i> Use This IP
        </button>
    </div>
    
    <div class="form-group">
        <label class="form-label">Allowed Device IP Address</label>
        <input type="text" id="deviceIp" class="form-control" placeholder="e.g., 192.168.1.100">
        <small style="color: var(--text-muted);">Only this IP can submit clock-ins. Leave empty to allow all. Use the "Use This IP" button above to auto-fill.</small>
    </div>
    
    <div class="form-group">
        <label class="form-label">Allowed Device Build Number</label>
        <input type="text" id="deviceBuild" class="form-control" placeholder="e.g., ABC123XYZ">
        <small style="color: var(--text-muted);">Device build identifier. Leave empty to skip build check.</small>
    </div>
    
    <div class="form-group">
        <label class="form-label">Late Threshold Time</label>
        <input type="time" id="lateThreshold" class="form-control" value="08:00">
        <small style="color: var(--text-muted);">Staff clocking in after this time will be marked as late.</small>
    </div>
    
    <button id="saveDeviceSettings" class="btn btn-success">
        <i class="fas fa-save"></i> Save Settings
    </button>
</div>

<script>
    $(document).ready(function() {
        if (typeof ClockInHistory !== 'undefined') {
            ClockInHistory.init();
        }
    });
</script>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>