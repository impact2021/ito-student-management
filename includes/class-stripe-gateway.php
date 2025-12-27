<?php
/**
 * Stripe payment gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

class IELTS_MS_Stripe_Gateway extends IELTS_MS_Payment_Gateway {
    
    public function __construct() {
        $this->id = 'stripe';
        $this->title = 'Stripe';
        $this->enabled = get_option('ielts_ms_stripe_enabled', true);
        
        // Register Stripe webhook handler
        add_action('wp_ajax_nopriv_ielts_ms_stripe_webhook', array($this, 'handle_callback'));
        add_action('wp_ajax_ielts_ms_stripe_webhook', array($this, 'handle_callback'));
        
        // Register AJAX handler for creating checkout session (legacy)
        add_action('wp_ajax_ielts_ms_create_stripe_session', array($this, 'create_checkout_session'));
        add_action('wp_ajax_nopriv_ielts_ms_create_stripe_session', array($this, 'create_checkout_session'));
        
        // Register AJAX handlers for inline payment (Payment Intent)
        add_action('wp_ajax_ielts_ms_create_payment_intent', array($this, 'create_payment_intent'));
        add_action('wp_ajax_nopriv_ielts_ms_create_payment_intent', array($this, 'create_payment_intent'));
        add_action('wp_ajax_ielts_ms_confirm_payment', array($this, 'confirm_payment'));
        add_action('wp_ajax_nopriv_ielts_ms_confirm_payment', array($this, 'confirm_payment'));
    }
    
    /**
     * Process payment (create Stripe checkout session)
     */
    public function process_payment($amount, $user_id, $duration_days, $payment_type = 'new') {
        if (!$this->enabled) {
            return array('success' => false, 'message' => 'Stripe is not enabled');
        }
        
        $secret_key = get_option('ielts_ms_stripe_secret_key', '');
        
        if (empty($secret_key)) {
            return array('success' => false, 'message' => 'Stripe is not configured');
        }
        
        // This will be handled via AJAX to create a checkout session
        return array(
            'success' => true,
            'use_ajax' => true,
            'gateway' => 'stripe'
        );
    }
    
    /**
     * Create Stripe checkout session (AJAX handler)
     */
    public function create_checkout_session() {
        check_ajax_referer('ielts_ms_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'User not logged in'));
        }
        
        $amount = floatval($_POST['amount']);
        $duration_days = intval($_POST['duration_days']);
        $payment_type = sanitize_text_field($_POST['payment_type']);
        $plan_key = sanitize_text_field($_POST['plan_key']);
        
        $secret_key = get_option('ielts_ms_stripe_secret_key', '');
        
        if (empty($secret_key)) {
            wp_send_json_error(array('message' => 'Stripe is not configured'));
        }
        
        // Create pending payment record
        $payment_id = $this->record_payment($user_id, $amount, $duration_days, null, 'pending', $payment_type);
        
        // Success and cancel URLs
        $success_url = add_query_arg(array(
            'payment_status' => 'success',
            'gateway' => 'stripe',
            'session_id' => '{CHECKOUT_SESSION_ID}'
        ), get_permalink(get_option('ielts_ms_account_page_id')));
        
        $cancel_url = add_query_arg(array(
            'payment_status' => 'cancelled',
            'gateway' => 'stripe'
        ), get_permalink(get_option('ielts_ms_account_page_id')));
        
        // Prepare Stripe API request
        $pricing_options = self::get_pricing_options();
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
            'customer_email' => get_userdata($user_id)->user_email,
            'metadata' => array(
                'user_id' => $user_id,
                'duration_days' => $duration_days,
                'payment_type' => $payment_type,
                'payment_id' => $payment_id
            )
        );
        
        // Make API request to Stripe
        // Stripe API accepts form-encoded data with special formatting for nested arrays
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
        
        if (isset($body['id'])) {
            wp_send_json_success(array(
                'session_id' => $body['id'],
                'url' => $body['url']
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to create checkout session'));
        }
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handle_callback() {
        $payload = @file_get_contents('php://input');
        $event = json_decode($payload, true);
        
        if (!$event) {
            status_header(400);
            exit;
        }
        
        // Verify webhook signature (optional but recommended)
        $webhook_secret = get_option('ielts_ms_stripe_webhook_secret', '');
        if (!empty($webhook_secret)) {
            $sig_header = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
            
            // In production, verify the signature here
            // For now, we'll just process the event
        }
        
        // Handle the event
        if ($event['type'] === 'checkout.session.completed') {
            $session = $event['data']['object'];
            
            $payment_id = isset($session['client_reference_id']) ? $session['client_reference_id'] : null;
            $user_id = isset($session['metadata']['user_id']) ? intval($session['metadata']['user_id']) : 0;
            $duration_days = isset($session['metadata']['duration_days']) ? intval($session['metadata']['duration_days']) : 0;
            $payment_type = isset($session['metadata']['payment_type']) ? $session['metadata']['payment_type'] : 'new';
            $is_registration = isset($session['metadata']['is_registration']) && $session['metadata']['is_registration'] === 'true';
            
            if ($user_id && $duration_days) {
                // Update payment record
                global $wpdb;
                $table = IELTS_MS_Database::get_payments_table();
                
                $wpdb->update(
                    $table,
                    array(
                        'payment_status' => 'completed',
                        'transaction_id' => $session['id']
                    ),
                    array('id' => $payment_id),
                    array('%s', '%s'),
                    array('%d')
                );
                
                // Create/extend membership
                $membership = new IELTS_MS_Membership();
                $membership->create_membership($user_id, $duration_days, $payment_id);
                
                    // If this was a registration, complete the registration
                    if ($is_registration) {
                        delete_user_meta($user_id, 'ielts_ms_registration_pending');
                        delete_user_meta($user_id, 'ielts_ms_registration_timestamp');
                        
                        // Send welcome email
                        wp_new_user_notification($user_id, null, 'user');
                    }
            }
        }
        
        status_header(200);
        exit;
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
                $query[] = urlencode($formatted_key) . '=' . urlencode($value);
            }
        }
        
        return implode('&', $query);
    }
    
    /**
     * Create Payment Intent for inline payment (AJAX handler)
     */
    public function create_payment_intent() {
        check_ajax_referer('ielts_ms_nonce', 'nonce');
        
        // Validate required POST parameters
        if (!isset($_POST['amount']) || !isset($_POST['duration_days']) || 
            !isset($_POST['payment_type']) || !isset($_POST['plan_key'])) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
        }
        
        // Get user_id - either from POST or current user
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
        
        $amount = floatval($_POST['amount']);
        $duration_days = intval($_POST['duration_days']);
        $payment_type = sanitize_text_field($_POST['payment_type']);
        $plan_key = sanitize_text_field($_POST['plan_key']);
        $is_registration = isset($_POST['is_registration']) && $_POST['is_registration'] === 'true';
        
        // Validate amount is positive and reasonable (max $1000)
        if ($amount <= 0 || $amount > 1000) {
            wp_send_json_error(array('message' => 'Invalid amount'));
        }
        
        // Validate duration_days is positive
        if ($duration_days <= 0) {
            wp_send_json_error(array('message' => 'Invalid duration'));
        }
        
        $secret_key = get_option('ielts_ms_stripe_secret_key', '');
        
        if (empty($secret_key)) {
            wp_send_json_error(array('message' => 'Stripe is not configured'));
        }
        
        // For registration, user_id might be 0, so we'll use the email
        $email = '';
        if ($user_id) {
            $user = get_userdata($user_id);
            if ($user) {
                $email = $user->user_email;
            }
        }
        
        if (empty($email) && isset($_POST['email'])) {
            $email = sanitize_email($_POST['email']);
        }
        
        // For registration, ensure we have either a valid user_id or email
        if ($is_registration && $user_id === 0 && empty($email)) {
            wp_send_json_error(array('message' => 'User information is required'));
        }
        
        // Create pending payment record
        // For registration with user_id = 0, we still create the record but it will be updated later
        $payment_id = $this->record_payment($user_id, $amount, $duration_days, null, 'pending', $payment_type);
        
        // Prepare Stripe API request for Payment Intent
        $pricing_options = self::get_pricing_options();
        $plan = isset($pricing_options[$plan_key]) ? $pricing_options[$plan_key] : null;
        
        if (!$plan) {
            wp_send_json_error(array('message' => 'Invalid plan'));
        }
        
        $stripe_data = array(
            'amount' => intval($amount * 100), // Convert to cents
            'currency' => 'usd',
            'automatic_payment_methods' => array('enabled' => 'true'),
            'metadata' => array(
                'user_id' => $user_id,
                'duration_days' => $duration_days,
                'payment_type' => $payment_type,
                'payment_id' => $payment_id,
                'plan_key' => $plan_key,
                'is_registration' => $is_registration ? 'true' : 'false'
            )
        );
        
        if (!empty($email)) {
            $stripe_data['receipt_email'] = $email;
        }
        
        // Make API request to Stripe
        $body_data = $this->build_stripe_query($stripe_data);
        
        $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => $body_data,
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Failed to create payment intent'));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['client_secret'])) {
            wp_send_json_success(array(
                'clientSecret' => $body['client_secret'],
                'payment_id' => $payment_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to create payment intent'));
        }
    }
    
    /**
     * Confirm payment after successful completion (AJAX handler)
     */
    public function confirm_payment() {
        check_ajax_referer('ielts_ms_nonce', 'nonce');
        
        $payment_intent_id = sanitize_text_field($_POST['payment_intent_id']);
        $payment_id = intval($_POST['payment_id']);
        
        if (empty($payment_intent_id) || empty($payment_id)) {
            wp_send_json_error(array('message' => 'Invalid payment data'));
        }
        
        // Get payment details from database
        global $wpdb;
        $table = IELTS_MS_Database::get_payments_table();
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $payment_id
        ));
        
        if (!$payment) {
            wp_send_json_error(array('message' => 'Payment not found'));
        }
        
        // Update payment record
        $wpdb->update(
            $table,
            array(
                'payment_status' => 'completed',
                'transaction_id' => $payment_intent_id
            ),
            array('id' => $payment_id),
            array('%s', '%s'),
            array('%d')
        );
        
        // Create/extend membership
        $membership = new IELTS_MS_Membership();
        $membership->create_membership($payment->user_id, $payment->duration_days, $payment_id);
        
        // Check if this was a registration payment
        $is_registration = get_user_meta($payment->user_id, 'ielts_ms_registration_pending', true);
        
        if ($is_registration) {
            delete_user_meta($payment->user_id, 'ielts_ms_registration_pending');
            delete_user_meta($payment->user_id, 'ielts_ms_registration_timestamp');
            
            // Send welcome email
            wp_new_user_notification($payment->user_id, null, 'user');
        }
        
        wp_send_json_success(array(
            'message' => 'Payment confirmed successfully',
            'redirect' => $is_registration 
                ? get_permalink(get_option('ielts_ms_login_page_id'))
                : get_permalink(get_option('ielts_ms_account_page_id'))
        ));
    }
}
