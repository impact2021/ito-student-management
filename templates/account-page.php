<?php
/**
 * Account page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$has_membership = $membership->has_active_membership($user_id);
$is_expired = $membership->is_expired($user_id);
$days_remaining = $membership->get_days_remaining($user_id);
$pricing_options = IELTS_MS_Payment_Gateway::get_pricing_options();

// Handle payment status messages
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';
?>

<div class="ielts-ms-account-wrapper">
    
    <?php if ($payment_status === 'success'): ?>
        <div class="ielts-ms-alert ielts-ms-alert-success">
            <?php _e('Payment successful! Your membership has been activated/extended.', 'ielts-membership-system'); ?>
        </div>
    <?php elseif ($payment_status === 'cancelled'): ?>
        <div class="ielts-ms-alert ielts-ms-alert-warning">
            <?php _e('Payment was cancelled.', 'ielts-membership-system'); ?>
        </div>
    <?php endif; ?>
    
    <div class="ielts-ms-account-header">
        <h2><?php _e('My Account', 'ielts-membership-system'); ?></h2>
        <p><?php printf(__('Welcome back, %s!', 'ielts-membership-system'), esc_html($user->display_name)); ?></p>
    </div>
    
    <!-- Tabs Navigation -->
    <div class="ielts-ms-tabs">
        <div class="ielts-ms-tab-nav">
            <button class="ielts-ms-tab-link active" data-tab="membership-status">
                <?php _e('Membership Status', 'ielts-membership-system'); ?>
            </button>
            <button class="ielts-ms-tab-link" data-tab="extend-course">
                <?php _e('Extend My Course', 'ielts-membership-system'); ?>
            </button>
            <button class="ielts-ms-tab-link" data-tab="payment-history">
                <?php _e('Payment History', 'ielts-membership-system'); ?>
            </button>
            <button class="ielts-ms-tab-link" data-tab="account-settings">
                <?php _e('Account Settings', 'ielts-membership-system'); ?>
            </button>
        </div>
        
        <!-- Tab Content -->
        <div class="ielts-ms-tab-content">
            
            <!-- Membership Status Tab -->
            <!-- Membership Status Tab -->
            <div class="ielts-ms-tab-pane active" id="membership-status">
                <div class="ielts-ms-section">
                    <h3><?php _e('Membership Status', 'ielts-membership-system'); ?></h3>
                    
                    <?php if ($has_membership): ?>
                        <?php 
                        // Check if this is a trial membership
                        $is_trial = $user_membership && (int)$user_membership->is_trial === 1;
                        ?>
                        <div class="ielts-ms-membership-card active">
                            <div class="membership-status">
                                <span class="status-badge active">
                                    <?php echo $is_trial ? __('Free Trial Active', 'ielts-membership-system') : __('Active', 'ielts-membership-system'); ?>
                                </span>
                            </div>
                            <div class="membership-details">
                                <?php if ($is_trial): ?>
                                    <?php
                                    // Calculate hours and minutes remaining for trial
                                    $end_timestamp = strtotime($user_membership->end_date);
                                    $now = current_time('timestamp');
                                    $remaining_seconds = $end_timestamp - $now;
                                    
                                    // Handle expired trial
                                    if ($remaining_seconds <= 0) {
                                        $time_display = __('expired', 'ielts-membership-system');
                                    } else {
                                        $hours_remaining = floor($remaining_seconds / 3600);
                                        $minutes_remaining = floor(($remaining_seconds % 3600) / 60);
                                        
                                        if ($hours_remaining > 0) {
                                            $time_display = sprintf('%d %s', 
                                                $hours_remaining, 
                                                _n('hour', 'hours', $hours_remaining, 'ielts-membership-system')
                                            );
                                            // Only add minutes if greater than 0
                                            if ($minutes_remaining > 0) {
                                                $time_display .= sprintf(' %d %s', 
                                                    $minutes_remaining,
                                                    _n('minute', 'minutes', $minutes_remaining, 'ielts-membership-system')
                                                );
                                            }
                                        } else {
                                            $time_display = sprintf('%d %s', 
                                                $minutes_remaining,
                                                _n('minute', 'minutes', $minutes_remaining, 'ielts-membership-system')
                                            );
                                        }
                                    }
                                    ?>
                                    <p><?php printf(__('You have %s left in your membership.', 'ielts-membership-system'), '<strong>' . esc_html($time_display) . '</strong>'); ?></p>
                                    <p>
                                        <a href="#extend-course" class="ielts-ms-btn ielts-ms-btn-primary" onclick="jQuery('.ielts-ms-tab-link[data-tab=\'extend-course\']').click(); return false;">
                                            <?php _e('Click here to become a full member', 'ielts-membership-system'); ?>
                                        </a>
                                    </p>
                                <?php else: ?>
                                    <p><strong><?php _e('Access Expires:', 'ielts-membership-system'); ?></strong> 
                                        <?php echo esc_html(date('F j, Y', strtotime($user_membership->end_date))); ?>
                                    </p>
                                    <p><strong><?php _e('Days Remaining:', 'ielts-membership-system'); ?></strong> 
                                        <?php echo esc_html($days_remaining); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($is_expired): ?>
                        <div class="ielts-ms-membership-card expired">
                            <div class="membership-status">
                                <span class="status-badge expired"><?php _e('Expired', 'ielts-membership-system'); ?></span>
                            </div>
                            <div class="membership-details">
                                <p><?php _e('Your membership expired on:', 'ielts-membership-system'); ?> 
                                    <?php echo esc_html(date('F j, Y', strtotime($user_membership->end_date))); ?>
                                </p>
                                <p><?php _e('Renew your membership below to continue accessing courses.', 'ielts-membership-system'); ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="ielts-ms-membership-card no-membership">
                            <p><?php _e('You do not have an active membership.', 'ielts-membership-system'); ?></p>
                            <p><?php _e('Purchase a membership below to access all IELTS preparation courses.', 'ielts-membership-system'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Extend My Course Tab -->
            <!-- Extend My Course Tab -->
            <div class="ielts-ms-tab-pane" id="extend-course">
                <div class="ielts-ms-section">
                    <h3><?php echo ($has_membership || $is_expired) ? __('Extend Membership', 'ielts-membership-system') : __('Purchase Membership', 'ielts-membership-system'); ?></h3>
        
        <div class="ielts-ms-pricing-grid">
            <?php if (!$has_membership && !$is_expired): ?>
                <!-- New membership option -->
                <div class="pricing-card">
                    <h4><?php echo esc_html($pricing_options['new_90']['label']); ?></h4>
                    <div class="price"><?php echo '$' . number_format($pricing_options['new_90']['price'], 2); ?> USD</div>
                    <p><?php _e('Full access to all IELTS courses', 'ielts-membership-system'); ?></p>
                    <button class="ielts-ms-btn ielts-ms-btn-primary purchase-btn" 
                            data-plan="new_90" 
                            data-amount="<?php echo $pricing_options['new_90']['price']; ?>"
                            data-days="<?php echo $pricing_options['new_90']['days']; ?>"
                            data-type="new">
                        <?php _e('Purchase', 'ielts-membership-system'); ?>
                    </button>
                </div>
            <?php else: ?>
                <!-- Extension options -->
                <div class="pricing-card">
                    <h4><?php echo esc_html($pricing_options['extend_7']['label']); ?></h4>
                    <div class="price"><?php echo '$' . number_format($pricing_options['extend_7']['price'], 2); ?> USD</div>
                    <button class="ielts-ms-btn ielts-ms-btn-primary purchase-btn" 
                            data-plan="extend_7" 
                            data-amount="<?php echo $pricing_options['extend_7']['price']; ?>"
                            data-days="<?php echo $pricing_options['extend_7']['days']; ?>"
                            data-type="extension">
                        <?php _e('Extend', 'ielts-membership-system'); ?>
                    </button>
                </div>
                
                <div class="pricing-card recommended">
                    <div class="recommended-badge"><?php _e('Recommended', 'ielts-membership-system'); ?></div>
                    <h4><?php echo esc_html($pricing_options['extend_30']['label']); ?></h4>
                    <div class="price"><?php echo '$' . number_format($pricing_options['extend_30']['price'], 2); ?> USD</div>
                    <button class="ielts-ms-btn ielts-ms-btn-primary purchase-btn" 
                            data-plan="extend_30" 
                            data-amount="<?php echo $pricing_options['extend_30']['price']; ?>"
                            data-days="<?php echo $pricing_options['extend_30']['days']; ?>"
                            data-type="extension">
                        <?php _e('Extend', 'ielts-membership-system'); ?>
                    </button>
                </div>
                
                <div class="pricing-card">
                    <h4><?php echo esc_html($pricing_options['extend_90']['label']); ?></h4>
                    <div class="price"><?php echo '$' . number_format($pricing_options['extend_90']['price'], 2); ?> USD</div>
                    <p><?php _e('Best value!', 'ielts-membership-system'); ?></p>
                    <button class="ielts-ms-btn ielts-ms-btn-primary purchase-btn" 
                            data-plan="extend_90" 
                            data-amount="<?php echo $pricing_options['extend_90']['price']; ?>"
                            data-days="<?php echo $pricing_options['extend_90']['days']; ?>"
                            data-type="extension">
                        <?php _e('Extend', 'ielts-membership-system'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Payment Gateway Selection -->
        <div id="payment-gateway-selector" class="ielts-ms-gateway-selector" style="display: none;">
            <h4><?php _e('Select Payment Method', 'ielts-membership-system'); ?></h4>
            <div class="gateway-buttons">
                <?php if (get_option('ielts_ms_paypal_enabled', true)): ?>
                    <button class="gateway-btn" data-gateway="paypal">
                        <img src="<?php echo IELTS_MS_PLUGIN_URL; ?>assets/images/paypal-logo.png" alt="PayPal" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                        <span style="display:none;">PayPal</span>
                    </button>
                <?php endif; ?>
                
                <?php if (get_option('ielts_ms_stripe_enabled', true)): ?>
                    <button class="gateway-btn" data-gateway="stripe">
                        <img src="<?php echo IELTS_MS_PLUGIN_URL; ?>assets/images/stripe-logo.png" alt="Credit Card"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                        <span style="display:none;">Credit Card</span>
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Stripe Inline Payment Section -->
            <?php if (get_option('ielts_ms_stripe_enabled', true)): ?>
            <div id="stripe-payment-section-account" class="stripe-payment-section" style="display: none; margin-top: 20px;">
                <h4><?php _e('Card Details', 'ielts-membership-system'); ?></h4>
                <div id="payment-element-account" class="stripe-payment-element">
                    <!-- Stripe Elements will be inserted here -->
                </div>
                <div id="payment-errors-account" class="ielts-ms-message" style="display: none;"></div>
                <button id="complete-payment-btn" class="ielts-ms-btn ielts-ms-btn-primary" style="margin-top: 15px; display: none;">
                    <?php _e('Complete Payment', 'ielts-membership-system'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
        
                    <!-- Hidden PayPal form -->
                    <form id="paypal-form" method="post" action="" style="display: none;">
                        <input type="hidden" name="cmd" value="">
                    </form>
                </div>
            </div>
            
            <!-- Payment History Tab -->
            <?php if (!empty($payments)): ?>
            <div class="ielts-ms-tab-pane" id="payment-history">
                <div class="ielts-ms-section">
                    <h3><?php _e('Payment History', 'ielts-membership-system'); ?></h3>
                    
                    <table class="ielts-ms-table">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'ielts-membership-system'); ?></th>
                                <th><?php _e('Amount', 'ielts-membership-system'); ?></th>
                                <th><?php _e('Type', 'ielts-membership-system'); ?></th>
                                <th><?php _e('Method', 'ielts-membership-system'); ?></th>
                                <th><?php _e('Status', 'ielts-membership-system'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($payment->payment_date))); ?></td>
                                    <td><?php echo '$' . number_format($payment->amount, 2); ?> <?php echo esc_html($payment->currency); ?></td>
                                    <td><?php echo esc_html(ucfirst($payment->payment_type)); ?></td>
                                    <td><?php echo esc_html(ucfirst($payment->payment_method)); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo esc_attr($payment->payment_status); ?>">
                                            <?php echo esc_html(ucfirst($payment->payment_status)); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="ielts-ms-tab-pane" id="payment-history">
                <div class="ielts-ms-section">
                    <h3><?php _e('Payment History', 'ielts-membership-system'); ?></h3>
                    <p><?php _e('No payment history available.', 'ielts-membership-system'); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Account Settings Tab -->
            <!-- Account Settings Tab -->
            <div class="ielts-ms-tab-pane" id="account-settings">
                <div class="ielts-ms-section">
                    <h3><?php _e('Account Settings', 'ielts-membership-system'); ?></h3>
        
        <div class="ielts-ms-settings-grid">
            <!-- Update Email -->
            <div class="settings-card">
                <h4><?php _e('Change Email', 'ielts-membership-system'); ?></h4>
                <form id="ielts-ms-update-email-form" class="ielts-ms-form">
                    <div class="ielts-ms-form-group">
                        <label><?php _e('Current Email:', 'ielts-membership-system'); ?></label>
                        <p><?php echo esc_html($user->user_email); ?></p>
                    </div>
                    
                    <div class="ielts-ms-form-group">
                        <label for="new_email"><?php _e('New Email', 'ielts-membership-system'); ?></label>
                        <input type="email" id="new_email" name="new_email" required>
                    </div>
                    
                    <div class="ielts-ms-form-group">
                        <label for="email_password"><?php _e('Current Password', 'ielts-membership-system'); ?></label>
                        <input type="password" id="email_password" name="password" required>
                    </div>
                    
                    <button type="submit" class="ielts-ms-btn ielts-ms-btn-secondary">
                        <?php _e('Update Email', 'ielts-membership-system'); ?>
                    </button>
                    
                    <div class="ielts-ms-message"></div>
                </form>
            </div>
            
            <!-- Update Password -->
            <div class="settings-card">
                <h4><?php _e('Change Password', 'ielts-membership-system'); ?></h4>
                <form id="ielts-ms-update-password-form" class="ielts-ms-form">
                    <div class="ielts-ms-form-group">
                        <label for="current_password"><?php _e('Current Password', 'ielts-membership-system'); ?></label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="ielts-ms-form-group">
                        <label for="new_password"><?php _e('New Password', 'ielts-membership-system'); ?></label>
                        <input type="password" id="new_password" name="new_password" required>
                        <small><?php _e('Minimum 8 characters', 'ielts-membership-system'); ?></small>
                    </div>
                    
                    <div class="ielts-ms-form-group">
                        <label for="confirm_new_password"><?php _e('Confirm New Password', 'ielts-membership-system'); ?></label>
                        <input type="password" id="confirm_new_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="ielts-ms-btn ielts-ms-btn-secondary">
                        <?php _e('Update Password', 'ielts-membership-system'); ?>
                    </button>
                    
                    <div class="ielts-ms-message"></div>
                </form>
            </div>
        </div>
                </div>
            </div>
            
        </div><!-- End of tab-content -->
    </div><!-- End of tabs -->
    
    <!-- Logout -->
    <div class="ielts-ms-section" style="margin-top: 20px;">
        <a href="<?php echo wp_logout_url(get_permalink(get_page_by_path('membership-login'))); ?>" class="ielts-ms-btn ielts-ms-btn-link">
            <?php _e('Logout', 'ielts-membership-system'); ?>
        </a>
    </div>
</div>
