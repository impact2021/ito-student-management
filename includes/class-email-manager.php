<?php
/**
 * Email notification management
 */

if (!defined('ABSPATH')) {
    exit;
}

class IELTS_MS_Email_Manager {
    
    /**
     * Send trial enrollment email
     */
    public static function send_trial_enrollment_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $subject = self::get_email_template('trial_enrollment_subject');
        $message = self::get_email_template('trial_enrollment_message');
        
        // Replace placeholders
        $subject = self::replace_placeholders($subject, $user);
        $message = self::replace_placeholders($message, $user);
        
        return wp_mail($user->user_email, $subject, $message, self::get_email_headers());
    }
    
    /**
     * Send trial expiration email
     */
    public static function send_trial_expiration_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $subject = self::get_email_template('trial_expiration_subject');
        $message = self::get_email_template('trial_expiration_message');
        
        // Replace placeholders
        $subject = self::replace_placeholders($subject, $user);
        $message = self::replace_placeholders($message, $user);
        
        return wp_mail($user->user_email, $subject, $message, self::get_email_headers());
    }
    
    /**
     * Send paid enrollment email
     */
    public static function send_paid_enrollment_email($user_id, $enrollment_type, $duration_days) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $subject = self::get_email_template('paid_enrollment_subject');
        $message = self::get_email_template('paid_enrollment_message');
        
        // Replace placeholders
        $subject = self::replace_placeholders($subject, $user, $enrollment_type, $duration_days);
        $message = self::replace_placeholders($message, $user, $enrollment_type, $duration_days);
        
        return wp_mail($user->user_email, $subject, $message, self::get_email_headers());
    }
    
    /**
     * Send paid membership expiration email
     */
    public static function send_paid_expiration_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $subject = self::get_email_template('paid_expiration_subject');
        $message = self::get_email_template('paid_expiration_message');
        
        // Replace placeholders
        $subject = self::replace_placeholders($subject, $user);
        $message = self::replace_placeholders($message, $user);
        
        return wp_mail($user->user_email, $subject, $message, self::get_email_headers());
    }
    
    /**
     * Get email template
     */
    private static function get_email_template($template_key) {
        $defaults = self::get_default_templates();
        return get_option('ielts_ms_email_' . $template_key, $defaults[$template_key]);
    }
    
    /**
     * Get default email templates
     */
    public static function get_default_templates() {
        return array(
            'trial_enrollment_subject' => 'Welcome to Your Free Trial',
            'trial_enrollment_message' => "Hi {user_name},\n\nWelcome to your free trial!\n\nYou now have access to all {enrollment_type} courses. Your trial will expire on {expiry_date}.\n\nTo continue learning after your trial, you can upgrade to a paid membership from your account page.\n\nBest regards,\nIELTS Online Team",
            
            'trial_expiration_subject' => 'Your Free Trial Has Ended',
            'trial_expiration_message' => "Hi {user_name},\n\nYour free trial has ended.\n\nWe hope you enjoyed exploring our courses! To continue learning, please visit your account page to purchase a membership.\n\nBest regards,\nIELTS Online Team",
            
            'paid_enrollment_subject' => 'Welcome to IELTS Online - Membership Activated',
            'paid_enrollment_message' => "Hi {user_name},\n\nThank you for your purchase! Your membership has been activated.\n\nMembership Details:\n- Type: {enrollment_type}\n- Duration: {duration} days\n- Expires: {expiry_date}\n\nYou now have full access to all {enrollment_type} courses.\n\nBest regards,\nIELTS Online Team",
            
            'paid_expiration_subject' => 'Your IELTS Membership Has Expired',
            'paid_expiration_message' => "Hi {user_name},\n\nYour IELTS membership has expired.\n\nTo regain access to our courses, you can extend your membership from your account page. We offer flexible extension options to suit your needs.\n\nBest regards,\nIELTS Online Team"
        );
    }
    
    /**
     * Replace email placeholders
     */
    private static function replace_placeholders($content, $user, $enrollment_type = null, $duration_days = null) {
        $membership = new IELTS_MS_Membership();
        $user_membership = $membership->get_user_membership($user->ID);
        
        // Determine enrollment type display name
        $enrollment_display = 'All';
        if ($enrollment_type) {
            switch ($enrollment_type) {
                case 'general_training':
                    $enrollment_display = 'General Training';
                    break;
                case 'academic':
                    $enrollment_display = 'Academic';
                    break;
                case 'both':
                    $enrollment_display = 'General Training and Academic';
                    break;
            }
        } elseif ($user_membership && $user_membership->enrollment_type) {
            switch ($user_membership->enrollment_type) {
                case 'general_training':
                    $enrollment_display = 'General Training';
                    break;
                case 'academic':
                    $enrollment_display = 'Academic';
                    break;
                case 'both':
                    $enrollment_display = 'General Training and Academic';
                    break;
            }
        }
        
        $placeholders = array(
            '{user_name}' => $user->display_name,
            '{user_email}' => $user->user_email,
            '{enrollment_type}' => $enrollment_display,
            '{duration}' => $duration_days ? $duration_days : '',
            '{expiry_date}' => $user_membership ? date('F j, Y', strtotime($user_membership->end_date)) : '',
            '{account_url}' => get_permalink(get_option('ielts_ms_account_page_id')),
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => get_bloginfo('url')
        );
        
        foreach ($placeholders as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }
        
        return $content;
    }
    
    /**
     * Get email headers
     */
    private static function get_email_headers() {
        $from_name = get_option('ielts_ms_email_from_name', get_bloginfo('name'));
        $from_email = get_option('ielts_ms_email_from_email', get_bloginfo('admin_email'));
        
        $headers = array();
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        
        return $headers;
    }
}
