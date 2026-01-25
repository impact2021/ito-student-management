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
     */
    const ENROLLMENT_TYPES = array('general_training', 'academic', 'both');
    
    /**
     * Default enrollment type for new memberships
     */
    const DEFAULT_ENROLLMENT_TYPE = 'academic';
    
    /**
     * Enrollment type to module slug mapping
     * Note: 'both' enrollment type doesn't have a specific module slug
     * as it grants access to all modules
     */
    const MODULE_SLUG_MAP = array(
        'general_training' => 'general-training',
        'academic' => 'academic'
    );
    
    /**
     * Get module slug for enrollment type
     * 
     * @param string $enrollment_type The enrollment type (general_training, academic, or both)
     * @return string The module slug, or empty string for 'both' (grants access to all modules)
     */
    public static function get_module_slug($enrollment_type) {
        // 'both' enrollment type grants access to all modules, so no specific slug needed
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
