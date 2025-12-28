<?php
/**
 * Plugin Name: IELTS Membership System
 * Plugin URI: https://www.ieltstestonline.com/
 * Description: Membership and payment system for IELTS preparation courses with PayPal and Stripe integration.
 * Version: 3.1
 * Author: IELTStestONLINE
 * Author URI: https://www.ieltstestonline.com/
 * Text Domain: ielts-membership-system
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IELTS_MS_VERSION', '3.1');
define('IELTS_MS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IELTS_MS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IELTS_MS_PLUGIN_FILE', __FILE__);

// Include required files
require_once IELTS_MS_PLUGIN_DIR . 'includes/class-database.php';
require_once IELTS_MS_PLUGIN_DIR . 'includes/class-membership.php';
require_once IELTS_MS_PLUGIN_DIR . 'includes/class-payment-gateway.php';
require_once IELTS_MS_PLUGIN_DIR . 'includes/class-paypal-gateway.php';
require_once IELTS_MS_PLUGIN_DIR . 'includes/class-stripe-gateway.php';
require_once IELTS_MS_PLUGIN_DIR . 'includes/class-login-manager.php';
require_once IELTS_MS_PLUGIN_DIR . 'includes/class-account-manager.php';
require_once IELTS_MS_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once IELTS_MS_PLUGIN_DIR . 'admin/class-admin.php';

/**
 * Initialize the plugin
 */
function ielts_ms_init() {
    // Initialize components
    $membership = new IELTS_MS_Membership();
    $login_manager = new IELTS_MS_Login_Manager();
    $account_manager = new IELTS_MS_Account_Manager();
    $shortcodes = new IELTS_MS_Shortcodes();
    $shortcodes->register();
    
    // Initialize payment gateways
    new IELTS_MS_PayPal_Gateway();
    new IELTS_MS_Stripe_Gateway();
    
    // Initialize admin if in admin area
    if (is_admin()) {
        new IELTS_MS_Admin();
    }
    
    // Schedule cron job for membership expiration check
    if (!wp_next_scheduled('ielts_ms_check_expired_memberships')) {
        wp_schedule_event(time(), 'daily', 'ielts_ms_check_expired_memberships');
    }
    
    // Redirect logged-in users to custom homepage
    add_action('template_redirect', 'ielts_ms_redirect_logged_in_homepage');
    
    // Protect exercise, sublesson, and lesson-page content
    add_action('template_redirect', 'ielts_ms_protect_content');
}
add_action('plugins_loaded', 'ielts_ms_init');

/**
 * Redirect logged-in users to custom homepage
 */
function ielts_ms_redirect_logged_in_homepage() {
    // Only redirect on the actual homepage
    if (!is_front_page() || !is_user_logged_in()) {
        return;
    }
    
    // Don't redirect admins
    if (current_user_can('manage_options')) {
        return;
    }
    
    // Get custom homepage for logged-in users
    $logged_in_homepage_id = get_option('ielts_ms_logged_in_homepage_id', 0);
    $current_homepage_id = get_option('page_on_front');
    
    if ($logged_in_homepage_id && $logged_in_homepage_id != $current_homepage_id) {
        $redirect_url = get_permalink($logged_in_homepage_id);
        if ($redirect_url) {
            wp_redirect($redirect_url);
            exit;
        }
    }
}

/**
 * Protect exercise, sublesson, and lesson-page content
 */
function ielts_ms_protect_content() {
    // Don't protect admin area
    if (is_admin()) {
        return;
    }
    
    // Don't redirect admins - they should have full access
    if (current_user_can('manage_options')) {
        return;
    }
    
    // Get the current post/page
    $queried_object = get_queried_object();
    
    // Only check for posts/pages
    if (!$queried_object || !isset($queried_object->post_type)) {
        return;
    }
    
    $post_type = $queried_object->post_type;
    $post_slug = isset($queried_object->post_name) ? $queried_object->post_name : '';
    
    // Check if this is an exercise, sublesson, or lesson-page
    // This assumes custom post types 'exercise', 'sublesson', 'lesson-page', or 'ielts-lesson-page' exist
    // Or we can check by slug patterns
    $is_protected_content = false;
    
    // Protected content patterns
    $protected_post_types = array('exercise', 'sublesson', 'lesson-page', 'ielts-lesson-page');
    $protected_patterns = array('exercise', 'sublesson', 'lesson-page', 'ielts-lesson-page');
    $protected_url_patterns = array('/exercise', '/sublesson', '/lesson-page', '/ielts-lesson-page');
    
    // Check if it's a custom post type for exercises or sublessons
    if (in_array($post_type, $protected_post_types)) {
        $is_protected_content = true;
    }
    
    // Also check by slug patterns
    if (!$is_protected_content) {
        foreach ($protected_patterns as $pattern) {
            if (strpos($post_slug, $pattern) !== false) {
                $is_protected_content = true;
                break;
            }
        }
    }
    
    // Check current URL path for protected patterns
    if (!$is_protected_content) {
        $current_url = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
        foreach ($protected_url_patterns as $pattern) {
            if (strpos($current_url, $pattern) !== false) {
                $is_protected_content = true;
                break;
            }
        }
    }
    
    // If not protected content, allow access
    if (!$is_protected_content) {
        return;
    }
    
    // If user is not logged in, redirect to login/registration page
    if (!is_user_logged_in()) {
        $redirect_page_id = get_option('ielts_ms_protected_content_redirect_page_id', 0);
        
        // Fallback to login page if no redirect page is set
        if (!$redirect_page_id) {
            $redirect_page_id = get_option('ielts_ms_login_page_id');
        }
        
        if ($redirect_page_id) {
            $redirect_url = get_permalink($redirect_page_id);
            
            // Add a return URL parameter so user can be redirected back after login
            $return_url = urlencode(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])));
            $redirect_url = add_query_arg('redirect_to', $return_url, $redirect_url);
            
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    // If user is logged in but doesn't have active membership, also redirect
    $membership = new IELTS_MS_Membership();
    if (!$membership->has_active_membership(get_current_user_id())) {
        $redirect_page_id = get_option('ielts_ms_protected_content_redirect_page_id', 0);
        
        // Fallback to login page if no redirect page is set
        if (!$redirect_page_id) {
            $redirect_page_id = get_option('ielts_ms_login_page_id');
        }
        
        if ($redirect_page_id) {
            $redirect_url = get_permalink($redirect_page_id);
            wp_redirect($redirect_url);
            exit;
        }
    }
}

/**
 * Cron job to check and expire memberships
 */
function ielts_ms_check_expired_memberships_callback() {
    global $wpdb;
    $table = IELTS_MS_Database::get_memberships_table();
    
    // Get all active memberships that have expired (only need user_id)
    $expired_memberships = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT user_id FROM $table WHERE status = %s AND end_date < NOW()",
            'active'
        )
    );
    
    if (empty($expired_memberships)) {
        return;
    }
    
    $membership = new IELTS_MS_Membership();
    foreach ($expired_memberships as $member) {
        $membership->expire_membership($member->user_id);
    }
}
add_action('ielts_ms_check_expired_memberships', 'ielts_ms_check_expired_memberships_callback');

/**
 * Register custom user roles
 */
function ielts_ms_register_roles() {
    // Add 'active' role for users with active memberships
    add_role('active', 'Active Member', array(
        'read' => true,
        'level_0' => true
    ));
    
    // Add 'expired' role for users with expired memberships
    add_role('expired', 'Expired Member', array(
        'read' => true,
        'level_0' => true
    ));
}

/**
 * Activation hook
 */
function ielts_ms_activate() {
    IELTS_MS_Database::create_tables();
    
    // Register custom roles
    ielts_ms_register_roles();
    
    // Create default pages if they don't exist
    ielts_ms_create_default_pages();
    
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'ielts_ms_activate');

/**
 * Deactivation hook
 */
function ielts_ms_deactivate() {
    // Clear scheduled cron job
    $timestamp = wp_next_scheduled('ielts_ms_check_expired_memberships');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'ielts_ms_check_expired_memberships');
    }
    
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'ielts_ms_deactivate');

/**
 * Create default pages
 */
function ielts_ms_create_default_pages() {
    // Login page
    $login_page = get_page_by_path('membership-login');
    if (!$login_page) {
        $login_id = wp_insert_post(array(
            'post_title' => 'Membership Login',
            'post_name' => 'membership-login',
            'post_content' => '[ielts_membership_login]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'comment_status' => 'closed'
        ));
        update_option('ielts_ms_login_page_id', $login_id);
    } else {
        update_option('ielts_ms_login_page_id', $login_page->ID);
    }
    
    // Registration page
    $register_page = get_page_by_path('membership-register');
    if (!$register_page) {
        wp_insert_post(array(
            'post_title' => 'Membership Registration',
            'post_name' => 'membership-register',
            'post_content' => '[ielts_membership_register]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'comment_status' => 'closed'
        ));
    }
    
    // Account page
    $account_page = get_page_by_path('my-account');
    if (!$account_page) {
        $account_id = wp_insert_post(array(
            'post_title' => 'My Account',
            'post_name' => 'my-account',
            'post_content' => '[ielts_membership_account]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'comment_status' => 'closed'
        ));
        update_option('ielts_ms_account_page_id', $account_id);
    } else {
        update_option('ielts_ms_account_page_id', $account_page->ID);
    }
}

/**
 * Enqueue frontend assets
 */
function ielts_ms_enqueue_assets() {
    wp_enqueue_style('ielts-membership-style', IELTS_MS_PLUGIN_URL . 'assets/css/style.css', array(), IELTS_MS_VERSION);
    
    // Enqueue Stripe.js if Stripe is enabled
    if (get_option('ielts_ms_stripe_enabled', true)) {
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
    }
    
    $script_deps = array('jquery');
    if (get_option('ielts_ms_stripe_enabled', true)) {
        $script_deps[] = 'stripe-js';
    }
    
    wp_enqueue_script('ielts-membership-script', IELTS_MS_PLUGIN_URL . 'assets/js/script.js', $script_deps, IELTS_MS_VERSION, true);
    
    wp_localize_script('ielts-membership-script', 'ieltsMS', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ielts_ms_nonce'),
        'stripePublicKey' => get_option('ielts_ms_stripe_publishable_key', ''),
        'stripeEnabled' => get_option('ielts_ms_stripe_enabled', true)
    ));
}
add_action('wp_enqueue_scripts', 'ielts_ms_enqueue_assets');
