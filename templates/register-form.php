<?php
/**
 * Registration form template
 */

if (!defined('ABSPATH')) {
    exit;
}

$pricing_options = IELTS_MS_Payment_Gateway::get_pricing_options();
$new_membership = $pricing_options['new_90'];
?>

<div class="ielts-ms-register-wrapper">
    <div class="ielts-ms-form-container">
        <h2><?php _e('Register', 'ielts-membership-system'); ?></h2>
        <p><?php _e('Create your account and purchase a membership to access IELTS preparation courses.', 'ielts-membership-system'); ?></p>
        
        <form id="ielts-ms-register-form" class="ielts-ms-form">
            <h3><?php _e('Account Information', 'ielts-membership-system'); ?></h3>
            
            <div class="ielts-ms-form-group">
                <label for="reg_username"><?php _e('Username', 'ielts-membership-system'); ?> *</label>
                <input type="text" id="reg_username" name="username" required>
            </div>
            
            <div class="ielts-ms-form-group">
                <label for="reg_email"><?php _e('Email', 'ielts-membership-system'); ?> *</label>
                <input type="email" id="reg_email" name="email" required>
            </div>
            
            <div class="ielts-ms-form-group">
                <label for="reg_password"><?php _e('Password', 'ielts-membership-system'); ?> *</label>
                <input type="password" id="reg_password" name="password" required>
                <small><?php _e('Minimum 8 characters', 'ielts-membership-system'); ?></small>
            </div>
            
            <div class="ielts-ms-form-group">
                <label for="reg_confirm_password"><?php _e('Confirm Password', 'ielts-membership-system'); ?> *</label>
                <input type="password" id="reg_confirm_password" name="confirm_password" required>
            </div>
            
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
            
            <h3><?php _e('Membership & Payment', 'ielts-membership-system'); ?></h3>
            <div class="ielts-ms-membership-info" style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h4 style="margin-top: 0;"><?php echo esc_html($new_membership['label']); ?></h4>
                <div class="price" style="font-size: 24px; font-weight: bold; color: #0073aa;">
                    $<?php echo number_format($new_membership['price'], 2); ?> USD
                </div>
                <p style="margin-bottom: 0;"><?php _e('Full access to all IELTS preparation courses', 'ielts-membership-system'); ?></p>
            </div>
            
            <div class="ielts-ms-form-group">
                <label><?php _e('Select Payment Method', 'ielts-membership-system'); ?> *</label>
                <div class="ielts-ms-payment-methods">
                    <?php if (get_option('ielts_ms_stripe_enabled', true)): ?>
                        <label class="payment-method-option">
                            <input type="radio" name="payment_gateway" value="stripe" checked required>
                            <span><?php _e('Credit Card (Stripe)', 'ielts-membership-system'); ?></span>
                        </label>
                    <?php endif; ?>
                    
                    <?php if (get_option('ielts_ms_paypal_enabled', true)): ?>
                        <label class="payment-method-option">
                            <input type="radio" name="payment_gateway" value="paypal" <?php echo !get_option('ielts_ms_stripe_enabled', true) && get_option('ielts_ms_paypal_enabled', true) ? 'checked' : ''; ?> required>
                            <span><?php _e('PayPal', 'ielts-membership-system'); ?></span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stripe Payment Element Container (for inline payment) -->
            <?php if (get_option('ielts_ms_stripe_enabled', true)): ?>
            <div id="stripe-payment-section" class="stripe-payment-section" style="display: none;">
                <div class="ielts-ms-form-group">
                    <label><?php _e('Card Details', 'ielts-membership-system'); ?></label>
                    <div id="payment-element" class="stripe-payment-element">
                        <!-- Stripe Elements will be inserted here -->
                    </div>
                    <div id="payment-errors" class="ielts-ms-message" style="display: none;"></div>
                </div>
            </div>
            <?php endif; ?>
            
            <input type="hidden" name="membership_plan" value="new_90">
            <input type="hidden" name="membership_amount" value="<?php echo $new_membership['price']; ?>">
            <input type="hidden" name="membership_days" value="<?php echo $new_membership['days']; ?>">
            
            <div class="ielts-ms-form-group">
                <button type="submit" class="ielts-ms-btn ielts-ms-btn-primary">
                    <?php _e('Register & Pay', 'ielts-membership-system'); ?>
                </button>
            </div>
            
            <div class="ielts-ms-message"></div>
        </form>
        
        <div class="ielts-ms-form-footer">
            <p><?php _e('Already have an account?', 'ielts-membership-system'); ?> 
                <a href="<?php echo get_permalink(get_page_by_path('membership-login')); ?>">
                    <?php _e('Login', 'ielts-membership-system'); ?>
                </a>
            </p>
        </div>
    </div>
    
    <!-- Hidden PayPal form for submission -->
    <form id="paypal-form" method="post" style="display: none;"></form>
</div>
