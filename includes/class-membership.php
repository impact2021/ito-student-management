<?php
/**
 * Membership management
 */

if (!defined('ABSPATH')) {
    exit;
}

class IELTS_MS_Membership {
    
    private $db;
    
    // Enrollment type to module slug mapping
    const MODULE_SLUG_MAP = array(
        'general_training' => 'general-training',
        'academic' => 'academic'
    );
    
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
        
        $start_date = current_time('mysql');
        $base_date = $start_date; // Base date for calculating end date
        
        if ($existing && $existing->status === 'active' && strtotime($existing->end_date) > time()) {
            // Extend existing membership from current end date
            $base_date = $existing->end_date;
        }
        
        // For trials, duration_days is actually in hours
        if ($is_trial) {
            $end_date = date('Y-m-d H:i:s', strtotime($base_date . ' +' . $duration_days . ' hours'));
        } else {
            $end_date = date('Y-m-d H:i:s', strtotime($base_date . ' +' . $duration_days . ' days'));
        }
        
        if ($existing) {
            // Update existing membership - keep original start_date
            // Don't downgrade enrollment_type if extending
            $update_data = array(
                'status' => 'active',
                'end_date' => $end_date,
                'updated_date' => current_time('mysql')
            );
            
            // Only update enrollment_type if it's an upgrade or new purchase (not trial)
            if (!$is_trial && $enrollment_type === 'both') {
                $update_data['enrollment_type'] = $enrollment_type;
            } elseif (!$is_trial && $existing->enrollment_type !== 'both') {
                $update_data['enrollment_type'] = $enrollment_type;
            }
            
            $result = $wpdb->update(
                $table,
                $update_data,
                array('id' => $existing->id),
                array_fill(0, count($update_data), '%s'),
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
        
        // Grant 'active' role to user
        $user = get_userdata($user_id);
        if ($user) {
            // Remove 'expired' role if present
            $user->remove_role('expired');
            // Add 'active' role
            if (!in_array('active', $user->roles)) {
                $user->add_role('active');
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
        return $membership->status === 'active' && strtotime($membership->end_date) > time();
    }
    
    /**
     * Check if membership is expired
     */
    public function is_expired($user_id) {
        $membership = $this->get_user_membership($user_id);
        
        if (!$membership) {
            return false;
        }
        
        return strtotime($membership->end_date) < time();
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
        
        // Update user role to 'expired'
        $user = get_userdata($user_id);
        if ($user) {
            // Remove 'active' role if present
            $user->remove_role('active');
            // Add 'expired' role
            if (!in_array('expired', $user->roles)) {
                $user->add_role('expired');
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
        
        $now = time();
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
        
        // Only filter for logged-in users with specific enrollment types
        if (!is_user_logged_in()) {
            return $query;
        }
        
        $user_id = get_current_user_id();
        $membership = $this->get_user_membership($user_id);
        
        // If no membership or membership is 'both', don't filter
        if (!$membership || $membership->enrollment_type === 'both') {
            return $query;
        }
        
        // Add tax query to filter by module
        $tax_query = $query->get('tax_query') ?: array();
        
        // Map enrollment type to module slug using constant
        $module_slug = isset(self::MODULE_SLUG_MAP[$membership->enrollment_type]) 
            ? self::MODULE_SLUG_MAP[$membership->enrollment_type] 
            : '';
        
        if ($module_slug) {
            $tax_query[] = array(
                'taxonomy' => 'ielts_module',
                'field' => 'slug',
                'terms' => $module_slug,
                'operator' => 'IN'
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
        
        // Only filter single ielts_course posts
        if (!$query->is_single() || $query->get('post_type') !== 'ielts_course') {
            // Also check if it's a single post and one of the posts is ielts_course
            if (!$query->is_single() || empty($posts)) {
                return $posts;
            }
            
            $is_course = false;
            foreach ($posts as $post) {
                if ($post->post_type === 'ielts_course') {
                    $is_course = true;
                    break;
                }
            }
            
            if (!$is_course) {
                return $posts;
            }
        }
        
        // Only filter for logged-in users
        if (!is_user_logged_in()) {
            return $posts;
        }
        
        $user_id = get_current_user_id();
        $has_access = true;
        
        // Check access for each post
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
