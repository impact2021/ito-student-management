<?php
/**
 * Membership management
 */

if (!defined('ABSPATH')) {
    exit;
}

class IELTS_MS_Membership {
    
    private $db;
    
    public function __construct() {
        $this->db = new IELTS_MS_Database();
        
        // Hook into IELTS Course Manager enrollment check
        add_filter('ielts_cm_has_course_access', array($this, 'check_course_access'), 10, 2);
        
        // Filter courses based on membership type
        add_filter('pre_get_posts', array($this, 'filter_courses_by_membership'));
        
        // Filter individual course access
        add_filter('the_posts', array($this, 'filter_single_course_access'), 10, 2);
    }
    
    /**
     * Create or update membership
     */
    public function create_membership($user_id, $duration_days, $payment_id = null, $enrollment_type = 'both', $is_trial = false) {
        global $wpdb;
        $table = IELTS_MS_Database::get_memberships_table();
        
        // Check for existing active membership
        $existing = $this->get_user_membership($user_id);
        
        // Work entirely with timestamps for timezone consistency
        // current_time('timestamp') returns Unix timestamp respecting WordPress timezone
        $start_timestamp = current_time('timestamp');
        
        if ($existing && $existing->status === 'active' && strtotime($existing->end_date) > current_time('timestamp')) {
            // Extend existing membership from current end date
            // Note: strtotime() interprets datetime string in server timezone
            // Since we stored dates using date() which also uses server timezone,
            // parsing with strtotime() maintains consistency
            $start_timestamp = strtotime($existing->end_date);
        }
        
        // Calculate end timestamp by adding duration
        if ($is_trial) {
            // For trials, duration_days is actually in hours
            $end_timestamp = $start_timestamp + ($duration_days * HOUR_IN_SECONDS);
        } else {
            $end_timestamp = $start_timestamp + ($duration_days * DAY_IN_SECONDS);
        }
        
        // Convert timestamps to MySQL datetime format
        // date() uses server timezone, which is consistent with how strtotime() parses above
        // All comparisons use current_time('timestamp') which adjusts for WordPress timezone
        $start_date = date('Y-m-d H:i:s', $start_timestamp);
        $end_date = date('Y-m-d H:i:s', $end_timestamp);
        
        if ($existing) {
            // Update existing membership - keep original start_date
            // Don't downgrade enrollment_type if extending
            $update_data = array(
                'status' => 'active',
                'end_date' => $end_date,
                'updated_date' => current_time('mysql')
            );
            
            $format = array('%s', '%s', '%s'); // status, end_date, updated_date
            
            // Only update enrollment_type if it's an upgrade or new purchase (not trial)
            if (!$is_trial && $enrollment_type === 'both') {
                $update_data['enrollment_type'] = $enrollment_type;
                $format[] = '%s';
            } elseif (!$is_trial && $existing->enrollment_type !== 'both') {
                $update_data['enrollment_type'] = $enrollment_type;
                $format[] = '%s';
            }
            
            // Update is_trial flag when upgrading from trial to paid
            if (!$is_trial && $existing->is_trial == 1) {
                $update_data['is_trial'] = 0;
                $format[] = '%d';
            }
            
            $result = $wpdb->update(
                $table,
                $update_data,
                array('id' => $existing->id),
                $format,
                array('%d')
            );
            $membership_id = $existing->id;
        } else {
            // Create new membership
            $result = $wpdb->insert(
                $table,
                array(
                    'user_id' => $user_id,
                    'status' => 'active',
                    'enrollment_type' => $enrollment_type,
                    'is_trial' => $is_trial ? 1 : 0,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ),
                array('%d', '%s', '%s', '%d', '%s', '%s')
            );
            $membership_id = $wpdb->insert_id;
        }
        
        // Update payment with membership ID
        if ($payment_id && $membership_id) {
            $payments_table = IELTS_MS_Database::get_payments_table();
            $wpdb->update(
                $payments_table,
                array('membership_id' => $membership_id),
                array('id' => $payment_id),
                array('%d'),
                array('%d')
            );
        }
        
        // Grant 'active' role to user while preserving existing roles
        $user = get_userdata($user_id);
        if ($user) {
            // Remove 'expired' role if present
            $user->remove_role('expired');
            // Add 'active' role
            if (!in_array('active', $user->roles)) {
                $user->add_role('active');
            }
            // Ensure subscriber role is maintained (WordPress default for registered users)
            if (!in_array('subscriber', $user->roles)) {
                $user->add_role('subscriber');
            }
        }
        
        // Send appropriate email
        if ($is_trial) {
            IELTS_MS_Email_Manager::send_trial_enrollment_email($user_id);
        } else {
            IELTS_MS_Email_Manager::send_paid_enrollment_email($user_id, $enrollment_type, $duration_days);
        }
        
        return $membership_id;
    }
    
    /**
     * Get user's membership
     */
    public function get_user_membership($user_id) {
        global $wpdb;
        $table = IELTS_MS_Database::get_memberships_table();
        
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY end_date DESC LIMIT 1",
            $user_id
        ));
        
        return $membership;
    }
    
    /**
     * Check if user has active membership
     */
    public function has_active_membership($user_id) {
        $membership = $this->get_user_membership($user_id);
        
        if (!$membership) {
            return false;
        }
        
        // Check if membership is active and not expired
        return $membership->status === 'active' && strtotime($membership->end_date) > current_time('timestamp');
    }
    
    /**
     * Check if membership is expired
     */
    public function is_expired($user_id) {
        $membership = $this->get_user_membership($user_id);
        
        if (!$membership) {
            return false;
        }
        
        return strtotime($membership->end_date) < current_time('timestamp');
    }
    
    /**
     * Expire membership
     */
    public function expire_membership($user_id) {
        global $wpdb;
        $table = IELTS_MS_Database::get_memberships_table();
        
        // Get membership before expiring to check if it was trial
        $membership = $this->get_user_membership($user_id);
        $was_trial = $membership && $membership->is_trial;
        $was_paid = $membership && !$membership->is_trial;
        
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'expired',
                'updated_date' => current_time('mysql')
            ),
            array(
                'user_id' => $user_id,
                'status' => 'active'
            ),
            array('%s', '%s'),
            array('%d', '%s')
        );
        
        // Update user role to 'expired' while preserving existing roles
        $user = get_userdata($user_id);
        if ($user) {
            // Remove 'active' role if present
            $user->remove_role('active');
            // Add 'expired' role
            if (!in_array('expired', $user->roles)) {
                $user->add_role('expired');
            }
            // Ensure subscriber role is maintained (WordPress default for registered users)
            if (!in_array('subscriber', $user->roles)) {
                $user->add_role('subscriber');
            }
        }
        
        // Send appropriate expiration email
        if ($was_trial) {
            IELTS_MS_Email_Manager::send_trial_expiration_email($user_id);
        } elseif ($was_paid) {
            IELTS_MS_Email_Manager::send_paid_expiration_email($user_id);
        }
        
        return $result;
    }
    
    /**
     * Get days remaining in membership
     */
    public function get_days_remaining($user_id) {
        $membership = $this->get_user_membership($user_id);
        
        if (!$membership || $membership->status !== 'active') {
            return 0;
        }
        
        $now = current_time('timestamp');
        $end = strtotime($membership->end_date);
        
        if ($end < $now) {
            return 0;
        }
        
        return ceil(($end - $now) / (60 * 60 * 24));
    }
    
    /**
     * Hook into IELTS Course Manager to check access
     */
    public function check_course_access($has_access, $user_id) {
        // Admins always have full access
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // If already has access (admin, etc), don't override
        if ($has_access) {
            return $has_access;
        }
        
        // Check if user has active membership in database first (source of truth)
        return $this->has_active_membership($user_id);
    }
    
    /**
     * Get all memberships (for admin)
     */
    public function get_all_memberships($status = null, $limit = 100, $offset = 0) {
        global $wpdb;
        $table = IELTS_MS_Database::get_memberships_table();
        
        $where = '';
        if ($status) {
            $where = $wpdb->prepare(" WHERE status = %s", $status);
        }
        
        $memberships = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table $where ORDER BY created_date DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ));
        
        return $memberships;
    }
    
    /**
     * Check if user is eligible for trial
     */
    public function is_trial_eligible($email) {
        global $wpdb;
        $table = IELTS_MS_Database::get_trial_usage_table();
        
        $used = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE email = %s",
            $email
        ));
        
        return $used == 0;
    }
    
    /**
     * Mark trial as used for email
     */
    public function mark_trial_used($email, $user_id = null) {
        global $wpdb;
        $table = IELTS_MS_Database::get_trial_usage_table();
        
        return $wpdb->insert(
            $table,
            array(
                'email' => $email,
                'user_id' => $user_id
            ),
            array('%s', '%d')
        );
    }
    
    /**
     * Check if user has access to specific course based on enrollment type
     */
    public function has_course_access($user_id, $course_id) {
        global $wpdb;
        
        // Admins always have access
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Check if user has active membership
        if (!$this->has_active_membership($user_id)) {
            return false;
        }
        
        $membership = $this->get_user_membership($user_id);
        
        // Get user's enrollment type
        $enrollment_type = $membership->enrollment_type;
        
        // First, check if there's specific course configuration for this membership type
        $table = IELTS_MS_Database::get_membership_courses_table();
        $configured_courses = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT course_id FROM $table WHERE membership_type = %s",
                $enrollment_type
            )
        );
        
        // If courses are configured for this membership type, check if this course is in the list
        if (!empty($configured_courses)) {
            return in_array((int)$course_id, array_map('intval', $configured_courses), true);
        }
        
        // Fall back to module-based access if no specific configuration exists
        // If enrollment type is 'both', user has access to all courses
        if ($membership->enrollment_type === 'both') {
            return true;
        }
        
        // Get course modules
        $course_modules = wp_get_post_terms($course_id, 'ielts_module', array('fields' => 'slugs'));
        
        // If no modules assigned, course is accessible to all
        if (empty($course_modules)) {
            return true;
        }
        
        // Check if user's enrollment type matches any of the course modules
        if ($membership->enrollment_type === 'general_training' && in_array('general-training', $course_modules)) {
            return true;
        }
        
        if ($membership->enrollment_type === 'academic' && in_array('academic', $course_modules)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Add tax query to show only courses without module restrictions
     */
    private function add_unrestricted_courses_filter($query) {
        $tax_query = $query->get('tax_query') ?: array();
        $tax_query[] = array(
            'taxonomy' => 'ielts_module',
            'operator' => 'NOT EXISTS'
        );
        $query->set('tax_query', $tax_query);
    }
    
    /**
     * Filter courses in queries based on user's membership type
     */
    public function filter_courses_by_membership($query) {
        // Only filter on frontend, for course queries, and for non-admin users
        if (is_admin() || !$query->is_main_query() || current_user_can('manage_options')) {
            return $query;
        }
        
        // Only filter ielts_course post type
        if ($query->get('post_type') !== 'ielts_course' && !$query->is_post_type_archive('ielts_course')) {
            return $query;
        }
        
        // For non-logged-in users or users without active memberships, 
        // don't show any courses with module restrictions
        if (!is_user_logged_in()) {
            // Show only courses without module restrictions
            $this->add_unrestricted_courses_filter($query);
            return $query;
        }
        
        $user_id = get_current_user_id();
        $membership = $this->get_user_membership($user_id);
        
        // If no active membership, show only courses without module restrictions
        if (!$membership || $membership->status !== 'active' || strtotime($membership->end_date) <= current_time('timestamp')) {
            $this->add_unrestricted_courses_filter($query);
            return $query;
        }
        
        // If membership is 'both', show all courses
        if ($membership->enrollment_type === 'both') {
            return $query;
        }
        
        // Add tax query to filter by module - show courses with user's module OR no module
        $tax_query = $query->get('tax_query') ?: array();
        
        // Map enrollment type to module slug using shared constant
        $module_slug = IELTS_MS_Constants::get_module_slug($membership->enrollment_type);
        
        if ($module_slug) {
            $tax_query['relation'] = 'OR';
            $tax_query[] = array(
                'taxonomy' => 'ielts_module',
                'field' => 'slug',
                'terms' => $module_slug,
                'operator' => 'IN'
            );
            $tax_query[] = array(
                'taxonomy' => 'ielts_module',
                'operator' => 'NOT EXISTS'
            );
            
            $query->set('tax_query', $tax_query);
        }
        
        return $query;
    }
    
    /**
     * Filter single course access based on membership
     */
    public function filter_single_course_access($posts, $query) {
        // Only filter on frontend single course views for non-admins
        if (is_admin() || !$query->is_main_query() || current_user_can('manage_options')) {
            return $posts;
        }
        
        // Only filter single posts
        if (!$query->is_single() || empty($posts)) {
            return $posts;
        }
        
        // Check if any of the posts is an ielts_course
        $has_course = false;
        foreach ($posts as $post) {
            if ($post->post_type === 'ielts_course') {
                $has_course = true;
                break;
            }
        }
        
        // If no course posts, don't filter
        if (!$has_course) {
            return $posts;
        }
        
        // Only filter for logged-in users
        if (!is_user_logged_in()) {
            return $posts;
        }
        
        $user_id = get_current_user_id();
        $has_access = true;
        
        // Check access for each course post
        foreach ($posts as $key => $post) {
            if ($post->post_type === 'ielts_course') {
                if (!$this->has_course_access($user_id, $post->ID)) {
                    // Remove post from results
                    unset($posts[$key]);
                    $has_access = false;
                }
            }
        }
        
        // Reindex array and check if empty
        $posts = array_values($posts);
        
        // If no posts remain after filtering, trigger 404
        if (!$has_access && empty($posts)) {
            $query->set_404();
            status_header(404);
        }
        
        return $posts;
    }
}
