<?php
/**
 * Login and registration management
 */

if (!defined('ABSPATH')) {
    exit;
}

class IELTS_MS_Login_Manager {
    
    public function __construct() {
        // Redirect default WordPress login to custom page
        add_action('init', array($this, 'redirect_wp_login'));
        
        // AJAX handlers
        add_action('wp_ajax_nopriv_ielts_ms_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_ielts_ms_register', array($this, 'handle_register'));
        add_action('wp_ajax_nopriv_ielts_ms_register_with_payment', array($this, 'handle_register_with_payment'));
        add_action('wp_ajax_nopriv_ielts_ms_register_trial', array($this, 'handle_register_trial'));
        add_action('wp_ajax_nopriv_ielts_ms_forgot_password', array($this, 'handle_forgot_password'));
        add_action('wp_ajax_nopriv_ielts_ms_reset_password', array($this, 'handle_reset_password'));
        add_action('wp_ajax_nopriv_ielts_ms_check_username', array($this, 'check_username_availability'));
        add_action('wp_ajax_nopriv_ielts_ms_check_email', array($this, 'check_email_availability'));
    }
    
    /**
     * Redirect default WordPress login to custom page
     */
    public function redirect_wp_login() {
        global $pagenow;
        
        // Check if custom login is enabled
        if (!get_option('ielts_ms_custom_login_enabled', true)) {
            return;
        }
        
        // Don't redirect if it's an AJAX request
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // Don't redirect admins - they should have full access
        if (current_user_can('manage_options')) {
            return;
        }
        
        // Don't redirect if user is already logged in
        if (is_user_logged_in()) {
            return;
        }
        
        // Redirect wp-login.php to custom login page
        if ($pagenow === 'wp-login.php' && !isset($_GET['action'])) {
            $login_page_id = get_option('ielts_ms_login_page_id');
            if ($login_page_id) {
                wp_redirect(get_permalink($login_page_id));
                exit;
            }
        }
    }
    
    /**
     * Handle login AJAX request
     */
    public function handle_login() {
        check_ajax_referer('ielts_ms_nonce', 'nonce');
        
        $username = sanitize_user($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']) ? true : false;
        
        $creds = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember
        );
        
        $user = wp_signon($creds, false);
        
        if (is_wp_error($user)) {
            wp_send_json_error(array('message' => $user->get_error_message()));
        }
        
        // Redirect admins to wp-admin, regular users to account page
        $redirect_url = user_can($user, 'manage_options') 
            ? admin_url() 
            : get_permalink(get_option('ielts_ms_account_page_id'));
        
        wp_send_json_success(array(
            'message' => 'Login successful',
            'redirect' => $redirect_url
        ));
    }
    
    /**
     * Handle registration AJAX request
     */
    public function handle_register() {
        check_ajax_referer('ielts_ms_nonce', 'nonce');
        
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error(array('message' => 'Please fill in all required fields'));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address'));
        }
        
        if ($password !== $confirm_password) {
            wp_send_json_error(array('message' => 'Passwords do not match'));
        }
        
        if (strlen($password) < 8) {
            wp_send_json_error(array('message' => 'Password must be at least 8 characters'));
        }
        
        if (username_exists($username)) {
            wp_send_json_error(array('message' => 'Username already exists'));
        }
        
        if (email_exists($email)) {
            wp_send_json_error(array('message' => 'Email already registered'));
        }
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }
        
        // Set user role to subscriber (no membership yet)
        $user = new WP_User($user_id);
        $user->set_role('subscriber');
        
        // Log user in
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        // Send welcome email
        wp_new_user_notification($user_id, null, 'user');
        
        wp_send_json_success(array(
            'message' => 'Registration successful',
            'redirect' => get_permalink(get_option('ielts_ms_account_page_id'))
        ));
    }
    
    /**
     * Handle trial registration AJAX request
     */
    public function handle_register_trial() {
        check_ajax_referer('ielts_ms_nonce', 'nonce');
        
        // Check if trials are enabled
        if (!get_option('ielts_ms_trial_enabled', false)) {
            wp_send_json_error(array('message' => 'Free trials are not currently available'));
        }
        
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $enrollment_type = isset($_POST['enrollment_type']) ? sanitize_text_field($_POST['enrollment_type']) : 'both';
        $trial_duration = isset($_POST['trial_duration']) ? intval($_POST['trial_duration']) : get_option('ielts_ms_trial_duration', 72);
        
        // Validation
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error(array('message' => 'Please fill in all required fields'));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address'));
        }
        
        if ($password !== $confirm_password) {
            wp_send_json_error(array('message' => 'Passwords do not match'));
        }
        
        if (strlen($password) < 8) {
            wp_send_json_error(array('message' => 'Password must be at least 8 characters'));
        }
        
        if (username_exists($username)) {
            wp_send_json_error(array('message' => 'Username already exists'));
        }
        
        if (email_exists($email)) {
            wp_send_json_error(array('message' => 'Email already registered'));
        }
        
        // Check if email has already used trial
        $membership = new IELTS_MS_Membership();
        if (!$membership->is_trial_eligible($email)) {
            wp_send_json_error(array('message' => 'This email has already been used for a free trial'));
        }
        
        // Validate enrollment type
        if (!in_array($enrollment_type, array('general_training', 'academic', 'both'))) {
            $enrollment_type = 'both';
        }
        
        // Create user account
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }
        
        // Set user role to subscriber initially
        $user = new WP_User($user_id);
        $user->set_role('subscriber');
        
        // Update user meta with first and last name
        if (!empty($first_name)) {
            update_user_meta($user_id, 'first_name', $first_name);
        }
        if (!empty($last_name)) {
            update_user_meta($user_id, 'last_name', $last_name);
        }
        
        // Set display name
        $display_name = trim($first_name . ' ' . $last_name);
        if (!empty($display_name)) {
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $display_name
            ));
        }
        
        // Create trial membership
        $membership->create_membership($user_id, $trial_duration, null, $enrollment_type, true);
        
        // Mark trial as used for this email
        $membership->mark_trial_used($email, $user_id);
        
        // Log the user in
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        wp_send_json_success(array(
            'message' => 'Your free trial has been activated! Welcome!',
            'redirect' => get_permalink(get_option('ielts_ms_account_page_id'))
        ));
    }
    
    /**
     * Handle registration with payment AJAX request
     */
    public function handle_register_with_payment() {
        check_ajax_referer('ielts_ms_nonce', 'nonce');
        
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $payment_gateway = sanitize_text_field($_POST['payment_gateway']);
        $membership_plan = sanitize_text_field($_POST['membership_plan']);
        $membership_amount = floatval($_POST['membership_amount']);
        $membership_days = intval($_POST['membership_days']);
        $enrollment_type = isset($_POST['enrollment_type']) ? sanitize_text_field($_POST['enrollment_type']) : 'both';
        
        // Validate enrollment type
        if (!in_array($enrollment_type, array('general_training', 'academic', 'both'))) {
            $enrollment_type = 'both';
        }
        
        // Validation
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error(array('message' => 'Please fill in all required fields'));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address'));
        }
        
        if ($password !== $confirm_password) {
            wp_send_json_error(array('message' => 'Passwords do not match'));
        }
        
        if (strlen($password) < 8) {
            wp_send_json_error(array('message' => 'Password must be at least 8 characters'));
        }
        
        if (username_exists($username)) {
            wp_send_json_error(array('message' => 'Username already exists'));
        }
        
        if (email_exists($email)) {
            wp_send_json_error(array('message' => 'Email already registered'));
        }
        
        if (empty($payment_gateway) || !in_array($payment_gateway, array('stripe', 'paypal', 'stripe_inline'))) {
            wp_send_json_error(array('message' => 'Please select a payment method'));
        }
        
        // Create user account (but don't log them in yet)
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }
        
        // Set user role to subscriber
        $user = new WP_User($user_id);
        $user->set_role('subscriber');
        
        // Update user meta with first and last name if provided
        if (!empty($first_name)) {
            update_user_meta($user_id, 'first_name', $first_name);
        }
        if (!empty($last_name)) {
            update_user_meta($user_id, 'last_name', $last_name);
        }
        
        // Store registration data in user meta for completion after payment
        update_user_meta($user_id, 'ielts_ms_registration_pending', true);
        update_user_meta($user_id, 'ielts_ms_registration_timestamp', time());
        update_user_meta($user_id, 'ielts_ms_enrollment_type', $enrollment_type);
        
        // Create a temporary trial membership while payment is being processed
        // This ensures users have a membership record in the database
        // The membership will be upgraded to paid once payment completes
        $membership = new IELTS_MS_Membership();
        $trial_duration = get_option('ielts_ms_trial_duration', 72); // Hours
        $membership->create_membership($user_id, $trial_duration, null, $enrollment_type, true);
        
        // Process payment based on gateway
        if ($payment_gateway === 'stripe') {
            $this->process_stripe_registration($user_id, $email, $membership_plan, $membership_amount, $membership_days, $enrollment_type);
        } elseif ($payment_gateway === 'stripe_inline') {
            // For inline Stripe payment, just return user_id to continue on client side
            wp_send_json_success(array(
                'user_id' => $user_id,
                'message' => 'Account created. Please complete payment.'
            ));
        } elseif ($payment_gateway === 'paypal') {
            $this->process_paypal_registration($user_id, $email, $membership_plan, $membership_amount, $membership_days, $enrollment_type);
        }
    }
    
    /**
     * Process Stripe payment for registration
     */
    private function process_stripe_registration($user_id, $email, $plan_key, $amount, $duration_days, $enrollment_type = 'both') {
        $secret_key = get_option('ielts_ms_stripe_secret_key', '');
        
        if (empty($secret_key)) {
            wp_send_json_error(array('message' => 'Stripe is not configured'));
        }
        
        // Create pending payment record
        $stripe_gateway = new IELTS_MS_Stripe_Gateway();
        $payment_id = $stripe_gateway->record_payment($user_id, $amount, $duration_days, null, 'pending', 'new');
        
        // Success and cancel URLs
        $success_url = add_query_arg(array(
            'registration' => 'complete',
            'payment_status' => 'success',
            'gateway' => 'stripe',
            'session_id' => '{CHECKOUT_SESSION_ID}'
        ), get_permalink(get_option('ielts_ms_login_page_id')));
        
        $cancel_url = add_query_arg(array(
            'registration' => 'cancelled',
            'payment_status' => 'cancelled',
            'gateway' => 'stripe'
        ), get_permalink(get_page_by_path('membership-register')));
        
        // Prepare Stripe API request
        $pricing_options = IELTS_MS_Payment_Gateway::get_pricing_options();
        $plan = isset($pricing_options[$plan_key]) ? $pricing_options[$plan_key] : null;
        
        if (!$plan) {
            wp_send_json_error(array('message' => 'Invalid plan'));
        }
        
        $stripe_data = array(
            'payment_method_types' => array('card'),
            'line_items' => array(
                array(
                    'price_data' => array(
                        'currency' => 'usd',
                        'product_data' => array(
                            'name' => $plan['label']
                        ),
                        'unit_amount' => intval($amount * 100) // Convert to cents
                    ),
                    'quantity' => 1
                )
            ),
            'mode' => 'payment',
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'client_reference_id' => $payment_id,
            'customer_email' => $email,
            'metadata' => array(
                'user_id' => $user_id,
                'duration_days' => $duration_days,
                'payment_type' => 'new',
                'payment_id' => $payment_id,
                'is_registration' => 'true'
            )
        );
        
        // Build Stripe query
        $body_data = $this->build_stripe_query($stripe_data);
        
        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => $body_data,
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Failed to create checkout session'));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['url'])) {
            wp_send_json_success(array(
                'redirect_url' => $body['url']
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to create checkout session'));
        }
    }
    
    /**
     * Process PayPal payment for registration
     */
    private function process_paypal_registration($user_id, $email, $plan_key, $amount, $duration_days, $enrollment_type = 'both') {
        $paypal_email = get_option('ielts_ms_paypal_email', '');
        
        if (empty($paypal_email)) {
            wp_send_json_error(array('message' => 'PayPal is not configured'));
        }
        
        // Create pending payment record
        $paypal_gateway = new IELTS_MS_PayPal_Gateway();
        $payment_id = $paypal_gateway->record_payment($user_id, $amount, $duration_days, null, 'pending', 'new');
        
        $pricing_options = IELTS_MS_Payment_Gateway::get_pricing_options();
        $plan = isset($pricing_options[$plan_key]) ? $pricing_options[$plan_key] : null;
        
        if (!$plan) {
            wp_send_json_error(array('message' => 'Invalid plan'));
        }
        
        // Build PayPal form data
        $return_url = add_query_arg(array(
            'registration' => 'complete',
            'payment_status' => 'success',
            'gateway' => 'paypal'
        ), get_permalink(get_option('ielts_ms_login_page_id')));
        
        $cancel_url = add_query_arg(array(
            'registration' => 'cancelled',
            'payment_status' => 'cancelled',
            'gateway' => 'paypal'
        ), get_permalink(get_page_by_path('membership-register')));
        
        $notify_url = admin_url('admin-ajax.php?action=ielts_ms_paypal_ipn');
        
        $form_data = array(
            'business' => $paypal_email,
            'cmd' => '_xclick',
            'item_name' => $plan['label'],
            'item_number' => $payment_id,
            'amount' => number_format($amount, 2, '.', ''),
            'currency_code' => 'USD',
            'return' => $return_url,
            'cancel_return' => $cancel_url,
            'notify_url' => $notify_url,
            'custom' => json_encode(array(
                'user_id' => $user_id,
                'duration_days' => $duration_days,
                'payment_type' => 'new',
                'payment_id' => $payment_id,
                'is_registration' => true
            ))
        );
        
        $paypal_url = get_option('ielts_ms_paypal_sandbox_mode', true) 
            ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://www.paypal.com/cgi-bin/webscr';
        
        wp_send_json_success(array(
            'form_data' => $form_data,
            'form_action' => $paypal_url
        ));
    }
    
    /**
     * Build Stripe query string with proper nested array formatting
     */
    private function build_stripe_query($data, $prefix = '') {
        $query = array();
        
        foreach ($data as $key => $value) {
            $formatted_key = $prefix ? "{$prefix}[{$key}]" : $key;
            
            if (is_array($value)) {
                $query[] = $this->build_stripe_query($value, $formatted_key);
            } else {
                // Convert boolean values to strings for Stripe API
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                $query[] = urlencode($formatted_key) . '=' . urlencode($value);
            }
        }
        
        return implode('&', $query);
    }
    
    /**
     * Handle forgot password AJAX request
     */
    public function handle_forgot_password() {
        check_ajax_referer('ielts_ms_nonce', 'nonce');
        
        $user_login = sanitize_text_field($_POST['user_login']);
        
        if (empty($user_login)) {
            wp_send_json_error(array('message' => 'Please enter your username or email'));
        }
        
        // Check if user exists
        if (strpos($user_login, '@')) {
            $user = get_user_by('email', $user_login);
        } else {
            $user = get_user_by('login', $user_login);
        }
        
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
        }
        
        // Generate reset key
        $reset_key = get_password_reset_key($user);
        
        if (is_wp_error($reset_key)) {
            wp_send_json_error(array('message' => 'Failed to generate reset key'));
        }
        
        // Send reset email
        $reset_url = add_query_arg(array(
            'action' => 'reset_password',
            'key' => $reset_key,
            'login' => rawurlencode($user->user_login)
        ), get_permalink(get_option('ielts_ms_login_page_id')));
        
        $subject = 'Password Reset Request';
        $message = "You requested a password reset.\n\n";
        $message .= "Click the link below to reset your password:\n";
        $message .= $reset_url . "\n\n";
        $message .= "If you did not request this, please ignore this email.";
        
        $sent = wp_mail($user->user_email, $subject, $message);
        
        if (!$sent) {
            wp_send_json_error(array('message' => 'Failed to send reset email'));
        }
        
        wp_send_json_success(array('message' => 'Password reset link sent to your email'));
    }
    
    /**
     * Handle reset password AJAX request
     */
    public function handle_reset_password() {
        check_ajax_referer('ielts_ms_nonce', 'nonce');
        
        $key = sanitize_text_field($_POST['key']);
        $login = sanitize_text_field($_POST['login']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($password) || empty($confirm_password)) {
            wp_send_json_error(array('message' => 'Please enter your new password'));
        }
        
        if ($password !== $confirm_password) {
            wp_send_json_error(array('message' => 'Passwords do not match'));
        }
        
        if (strlen($password) < 8) {
            wp_send_json_error(array('message' => 'Password must be at least 8 characters'));
        }
        
        $user = check_password_reset_key($key, $login);
        
        if (is_wp_error($user)) {
            wp_send_json_error(array('message' => 'Invalid or expired reset key'));
        }
        
        reset_password($user, $password);
        
        wp_send_json_success(array(
            'message' => 'Password reset successful',
            'redirect' => get_permalink(get_option('ielts_ms_login_page_id'))
        ));
    }
    
    /**
     * Check username availability
     */
    public function check_username_availability() {
        check_ajax_referer('ielts_ms_nonce', 'nonce');
        
        if (!isset($_POST['username']) || empty($_POST['username'])) {
            wp_send_json_error(array('message' => 'Username is required'));
            return;
        }
        
        $username = sanitize_user($_POST['username']);
        
        if (username_exists($username)) {
            wp_send_json_error(array('message' => 'This username is already taken'));
        } else {
            wp_send_json_success(array('message' => 'Username is available'));
        }
    }
    
    /**
     * Check email availability
     */
    public function check_email_availability() {
        check_ajax_referer('ielts_ms_nonce', 'nonce');
        
        if (!isset($_POST['email']) || empty($_POST['email'])) {
            wp_send_json_error(array('message' => 'Email is required'));
            return;
        }
        
        $email = sanitize_email($_POST['email']);
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address'));
            return;
        }
        
        if (email_exists($email)) {
            wp_send_json_error(array('message' => 'This email is already registered'));
        } else {
            wp_send_json_success(array('message' => 'Email is available'));
        }
    }
}
