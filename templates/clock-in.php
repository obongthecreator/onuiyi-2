<?php
/**
 * Clock In Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Clock In - 120 Stand Inventory';
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
            <span><?php echo esc_html($current_user['role']); ?></span>
        </div>
    </div>
    <div class="date-display">
        <i class="fas fa-clock"></i>
        <span id="serverDateTime"><?php echo current_time('l, F j, Y \a\t g:i A'); ?></span>
    </div>
</div>

<div class="glass-card" style="max-width: 500px; margin: 0 auto; text-align: center;">
    <h3 style="margin-bottom: 24px; color: var(--primary-color);">
        <i class="fas fa-clock"></i> Staff Clock-In
    </h3>
    
    <!-- Status area -->
    <div id="clockInStatus" style="margin-bottom: 24px;">
        <div class="loading-spinner" style="margin: 20px auto;"></div>
        <p style="color: var(--text-muted);">Checking status...</p>
    </div>
    
    <!-- Clock-in form (hidden when already clocked in) -->
    <div id="clockInForm" style="display: none;">
        <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 0.9rem;">
            <i class="fas fa-shield-alt"></i> 
            Enter your password to confirm your identity and clock in.
        </p>
        
        <div class="form-group" style="text-align: left; margin-bottom: 20px;">
            <label class="form-label">Your Password</label>
            <div style="position: relative;">
                <input type="password" id="clockInPassword" class="form-control" placeholder="Enter your password" autocomplete="current-password">
                <button type="button" id="togglePassword" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer;">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        
        <button id="submitClockIn" class="btn btn-success" style="width: 100%; padding: 14px; font-size: 1.1rem;">
            <i class="fas fa-check-circle"></i> Clock In Now
        </button>
    </div>
    
    <!-- Already clocked in (hidden by default) -->
    <div id="clockInDone" style="display: none;">
        <div style="font-size: 3rem; color: var(--success-color); margin-bottom: 16px;">
            <i class="fas fa-check-circle"></i>
        </div>
        <h4 style="color: var(--success-color); margin-bottom: 8px;">Already Clocked In</h4>
        <p id="clockInDoneMessage" style="color: var(--text-muted); font-size: 1rem;"></p>
    </div>
    
    <!-- Success confirmation (hidden by default) -->
    <div id="clockInSuccess" style="display: none;">
        <div style="font-size: 3rem; color: var(--success-color); margin-bottom: 16px;">
            <i class="fas fa-check-circle"></i>
        </div>
        <h4 style="color: var(--success-color); margin-bottom: 8px;">Clock-In Recorded!</h4>
        <p id="clockInSuccessName" style="font-size: 1.1rem; font-weight: 600; margin-bottom: 4px;"></p>
        <p id="clockInSuccessTime" style="color: var(--text-muted); font-size: 1rem;"></p>
    </div>
</div>

<script>
    // ClockIn.init() is safe to call multiple times (guarded internally).
    // This ensures the module initialises even when the page is loaded
    // in isolation (e.g. via back-button or cache).
    jQuery(function() {
        if (typeof ClockIn !== 'undefined') {
            ClockIn.init();
        }
    });
</script>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>