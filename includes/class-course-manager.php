<?php
/**
 * Course post types and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class IELTS_MS_Course_Manager {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_types'));
    }
    
    /**
     * Register custom post types
     */
    public function register_post_types() {
        // Register ielts_course
        register_post_type('ielts_course', array(
            'labels' => array(
                'name' => 'IELTS Courses',
                'singular_name' => 'IELTS Course',
                'add_new' => 'Add New Course',
                'add_new_item' => 'Add New Course',
                'edit_item' => 'Edit Course',
                'new_item' => 'New Course',
                'view_item' => 'View Course',
                'search_items' => 'Search Courses',
                'not_found' => 'No courses found',
                'not_found_in_trash' => 'No courses found in trash'
            ),
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'ielts-course'),
            'capability_type' => 'post'
        ));
        
        // Register ielts_lesson
        register_post_type('ielts_lesson', array(
            'labels' => array(
                'name' => 'IELTS Lessons',
                'singular_name' => 'IELTS Lesson',
                'add_new' => 'Add New Lesson',
                'add_new_item' => 'Add New Lesson',
                'edit_item' => 'Edit Lesson',
                'new_item' => 'New Lesson',
                'view_item' => 'View Lesson',
                'search_items' => 'Search Lessons',
                'not_found' => 'No lessons found',
                'not_found_in_trash' => 'No lessons found in trash'
            ),
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-media-document',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'),
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'ielts-lesson'),
            'capability_type' => 'post'
        ));
        
        // Register ielts_resource
        register_post_type('ielts_resource', array(
            'labels' => array(
                'name' => 'IELTS Resources',
                'singular_name' => 'IELTS Resource',
                'add_new' => 'Add New Resource',
                'add_new_item' => 'Add New Resource',
                'edit_item' => 'Edit Resource',
                'new_item' => 'New Resource',
                'view_item' => 'View Resource',
                'search_items' => 'Search Resources',
                'not_found' => 'No resources found',
                'not_found_in_trash' => 'No resources found in trash'
            ),
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-book-alt',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'ielts-resource'),
            'capability_type' => 'post'
        ));
        
        // Register ielts_quiz
        register_post_type('ielts_quiz', array(
            'labels' => array(
                'name' => 'IELTS Quizzes',
                'singular_name' => 'IELTS Quiz',
                'add_new' => 'Add New Quiz',
                'add_new_item' => 'Add New Quiz',
                'edit_item' => 'Edit Quiz',
                'new_item' => 'New Quiz',
                'view_item' => 'View Quiz',
                'search_items' => 'Search Quizzes',
                'not_found' => 'No quizzes found',
                'not_found_in_trash' => 'No quizzes found in trash'
            ),
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-list-view',
            'supports' => array('title', 'editor', 'thumbnail'),
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'ielts-quiz'),
            'capability_type' => 'post'
        ));
        
        // Register taxonomy for course modules
        register_taxonomy('ielts_module', array('ielts_course'), array(
            'labels' => array(
                'name' => 'Course Modules',
                'singular_name' => 'Course Module',
                'search_items' => 'Search Modules',
                'all_items' => 'All Modules',
                'edit_item' => 'Edit Module',
                'update_item' => 'Update Module',
                'add_new_item' => 'Add New Module',
                'new_item_name' => 'New Module Name',
                'menu_name' => 'Modules'
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'ielts-module')
        ));
    }
    
    /**
     * Create default module terms
     */
    public static function create_default_modules() {
        // Create General Training module
        if (!term_exists('General Training', 'ielts_module')) {
            wp_insert_term('General Training', 'ielts_module', array(
                'description' => 'Courses for General Training module',
                'slug' => 'general-training'
            ));
        }
        
        // Create Academic module
        if (!term_exists('Academic', 'ielts_module')) {
            wp_insert_term('Academic', 'ielts_module', array(
                'description' => 'Courses for Academic module',
                'slug' => 'academic'
            ));
        }
    }
    
    /**
     * Get courses by module
     */
    public function get_courses_by_module($module_slug) {
        $args = array(
            'post_type' => 'ielts_course',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'ielts_module',
                    'field' => 'slug',
                    'terms' => $module_slug
                )
            )
        );
        
        return get_posts($args);
    }
}
