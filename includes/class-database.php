<?php
/**
 * Database management for membership system
 */

if (!defined('ABSPATH')) {
    exit;
}

class IELTS_MS_Database {
    
    private static $memberships_table;
    private static $payments_table;
    private static $trial_usage_table;
    
    public function __construct() {
        global $wpdb;
        self::$memberships_table = $wpdb->prefix . 'ielts_ms_memberships';
        self::$payments_table = $wpdb->prefix . 'ielts_ms_payments';
        self::$trial_usage_table = $wpdb->prefix . 'ielts_ms_trial_usage';
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Memberships table
        $memberships_table = $wpdb->prefix . 'ielts_ms_memberships';
        $sql_memberships = "CREATE TABLE IF NOT EXISTS $memberships_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            status varchar(20) DEFAULT 'active',
            enrollment_type varchar(20) DEFAULT 'both',
            is_trial tinyint(1) DEFAULT 0,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY end_date (end_date),
            KEY enrollment_type (enrollment_type),
            KEY is_trial (is_trial)
        ) $charset_collate;";
        
        // Payments table
        $payments_table = $wpdb->prefix . 'ielts_ms_payments';
        $sql_payments = "CREATE TABLE IF NOT EXISTS $payments_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            membership_id bigint(20) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) DEFAULT 'USD',
            payment_method varchar(20) NOT NULL,
            transaction_id varchar(255) DEFAULT NULL,
            payment_status varchar(20) DEFAULT 'pending',
            payment_type varchar(20) DEFAULT 'new',
            duration_days int(11) NOT NULL,
            payment_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY membership_id (membership_id),
            KEY transaction_id (transaction_id),
            KEY payment_status (payment_status)
        ) $charset_collate;";
        
        // Trial usage tracking table
        $trial_usage_table = $wpdb->prefix . 'ielts_ms_trial_usage';
        $sql_trial_usage = "CREATE TABLE IF NOT EXISTS $trial_usage_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            trial_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_memberships);
        dbDelta($sql_payments);
        dbDelta($sql_trial_usage);
    }
    
    /**
     * Get table names
     */
    public static function get_memberships_table() {
        global $wpdb;
        return $wpdb->prefix . 'ielts_ms_memberships';
    }
    
    public static function get_payments_table() {
        global $wpdb;
        return $wpdb->prefix . 'ielts_ms_payments';
    }
    
    public static function get_trial_usage_table() {
        global $wpdb;
        return $wpdb->prefix . 'ielts_ms_trial_usage';
    }
}
