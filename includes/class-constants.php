<?php
/**
 * Shared constants for IELTS Membership System
 */

if (!defined('ABSPATH')) {
    exit;
}

class IELTS_MS_Constants {
    
    /**
     * Allowed enrollment types
     * v11.0: Simplified to only Academic and General Training
     */
    const ENROLLMENT_TYPES = array('general_training', 'academic');
    
    // Commented out for v11.0 - will be restored if "Both" option is needed
    // const ENROLLMENT_TYPES = array('general_training', 'academic', 'both');
    
    /**
     * Default enrollment type for new memberships
     */
    const DEFAULT_ENROLLMENT_TYPE = 'academic';
    
    /**
     * Enrollment type to module slug mapping
     */
    const MODULE_SLUG_MAP = array(
        'general_training' => 'general-training',
        'academic' => 'academic'
    );
    
    /**
     * Get module slug for enrollment type
     */
    public static function get_module_slug($enrollment_type) {
        return isset(self::MODULE_SLUG_MAP[$enrollment_type]) 
            ? self::MODULE_SLUG_MAP[$enrollment_type] 
            : '';
    }
    
    /**
     * Validate enrollment type
     */
    public static function is_valid_enrollment_type($enrollment_type) {
        return in_array($enrollment_type, self::ENROLLMENT_TYPES);
    }
}
