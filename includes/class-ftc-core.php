<?php
/**
 * Core plugin class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Core {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Components
     */
    public $person;
    public $family;
    public $relationship;
    public $event;
    public $media;
    public $merge;
    public $notification;
    public $search;
    public $calendar;
    public $chart;
    public $export;
    public $facial_recognition;
    public $places;
    public $custom_fields;
    public $ajax;
    public $shortcodes;
    public $rest_api;
    public $admin;
    public $public;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Check for database upgrades
        add_action('admin_init', array('FTC_Database', 'maybe_upgrade'));
        
        // Register custom post types and taxonomies
        add_action('init', array($this, 'register_post_types'));
        
        // Handle RTL languages
        add_filter('body_class', array($this, 'add_rtl_class'));
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        $this->person = new FTC_Person();
        $this->family = new FTC_Family();
        $this->relationship = new FTC_Relationship();
        $this->event = new FTC_Event();
        $this->media = new FTC_Media();
        $this->merge = new FTC_Merge();
        $this->notification = new FTC_Notification();
        $this->search = new FTC_Search();
        $this->calendar = new FTC_Calendar();
        $this->chart = new FTC_Chart();
        $this->export = new FTC_Export();
        $this->facial_recognition = new FTC_Facial_Recognition();
        $this->places = new FTC_Places();
        $this->custom_fields = new FTC_Custom_Fields();
        $this->ajax = new FTC_Ajax();
        $this->shortcodes = new FTC_Shortcodes();
        $this->rest_api = new FTC_REST_API();
        $this->public = new FTC_Public();
        
        if (is_admin()) {
            $this->admin = new FTC_Admin();
        }
    }
    
    /**
     * Init callback
     */
    public function init() {
        // Add rewrite rules for family tree pages
        $this->add_rewrite_rules();
    }
    
    /**
     * Add rewrite rules
     */
    private function add_rewrite_rules() {
        add_rewrite_rule(
            'family-tree/person/([^/]+)/?$',
            'index.php?ftc_person=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            'family-tree/family/([^/]+)/?$',
            'index.php?ftc_family=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            'family-tree/chart/([^/]+)/?$',
            'index.php?ftc_chart=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            'family-tree/place/([^/]+)/?$',
            'index.php?ftc_place=$matches[1]',
            'top'
        );
        
        add_rewrite_tag('%ftc_person%', '([^&]+)');
        add_rewrite_tag('%ftc_family%', '([^&]+)');
        add_rewrite_tag('%ftc_chart%', '([^&]+)');
        add_rewrite_tag('%ftc_place%', '([^&]+)');
    }
    
    /**
     * Register post types
     */
    public function register_post_types() {
        // Register a CPT for family tree documentation if needed
        register_post_type('ftc_tree', array(
            'labels' => array(
                'name' => __('Family Trees', 'family-tree-connect'),
                'singular_name' => __('Family Tree', 'family-tree-connect'),
            ),
            'public' => false,
            'show_ui' => false,
            'capability_type' => 'post',
            'supports' => array('title', 'author'),
        ));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Main stylesheet
        wp_enqueue_style(
            'ftc-main',
            FTC_PLUGIN_URL . 'assets/css/main.css',
            array(),
            FTC_VERSION
        );
        
        // RTL stylesheet
        if (is_rtl() || $this->is_rtl_content()) {
            wp_enqueue_style(
                'ftc-rtl',
                FTC_PLUGIN_URL . 'assets/css/rtl.css',
                array('ftc-main'),
                FTC_VERSION
            );
        }
        
        // Chart styles
        wp_enqueue_style(
            'ftc-chart',
            FTC_PLUGIN_URL . 'assets/css/chart.css',
            array('ftc-main'),
            FTC_VERSION
        );
        
        // Cropper.js for image cropping
        wp_enqueue_style(
            'cropperjs',
            'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css',
            array(),
            '1.5.13'
        );
        
        // Main JavaScript
        wp_enqueue_script(
            'ftc-main',
            FTC_PLUGIN_URL . 'assets/js/main.js',
            array('jquery'),
            FTC_VERSION,
            true
        );
        
        // Chart JavaScript
        wp_enqueue_script(
            'ftc-chart',
            FTC_PLUGIN_URL . 'assets/js/chart.js',
            array('jquery', 'ftc-main'),
            FTC_VERSION,
            true
        );
        
        // Cropper.js
        wp_enqueue_script(
            'cropperjs',
            'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js',
            array(),
            '1.5.13',
            true
        );
        
        // Media handler
        wp_enqueue_script(
            'ftc-media',
            FTC_PLUGIN_URL . 'assets/js/media.js',
            array('jquery', 'cropperjs'),
            FTC_VERSION,
            true
        );
        
        // Family view handler
        wp_enqueue_script(
            'ftc-family-view',
            FTC_PLUGIN_URL . 'assets/js/family-view.js',
            array('jquery', 'ftc-main'),
            FTC_VERSION,
            true
        );
        
        // Localize scripts
        wp_localize_script('ftc-main', 'ftcData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('ftc/v1/'),
            'nonce' => wp_create_nonce('ftc_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'isLoggedIn' => is_user_logged_in(),
            'userId' => get_current_user_id(),
            'locale' => get_locale(),
            'isRtl' => is_rtl() || $this->is_rtl_content(),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this?', 'family-tree-connect'),
                'saving' => __('Saving...', 'family-tree-connect'),
                'saved' => __('Saved', 'family-tree-connect'),
                'error' => __('An error occurred', 'family-tree-connect'),
                'selectPerson' => __('Select a person', 'family-tree-connect'),
                'noResults' => __('No results found', 'family-tree-connect'),
                'loading' => __('Loading...', 'family-tree-connect'),
                'confirmMerge' => __('Send merge request?', 'family-tree-connect'),
            ),
            'eventTypes' => FTC_Event::get_event_types(),
            'calendarSystems' => FTC_Calendar::get_calendar_systems(),
        ));
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'ftc') === false && strpos($hook, 'family-tree') === false) {
            return;
        }
        
        wp_enqueue_style(
            'ftc-admin',
            FTC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FTC_VERSION
        );
        
        wp_enqueue_script(
            'ftc-admin',
            FTC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            FTC_VERSION,
            true
        );
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_media();
    }
    
    /**
     * Check if content requires RTL
     */
    public function is_rtl_content() {
        $rtl_locales = array('he_IL', 'ar', 'fa_IR', 'ur');
        $locale = get_locale();
        
        foreach ($rtl_locales as $rtl) {
            if (strpos($locale, $rtl) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add RTL body class
     */
    public function add_rtl_class($classes) {
        if ($this->is_rtl_content() && !is_rtl()) {
            $classes[] = 'ftc-rtl';
        }
        return $classes;
    }
    
    /**
     * Get plugin option
     */
    public static function get_option($key, $default = '') {
        $options = get_option('ftc_options', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    /**
     * Update plugin option
     */
    public static function update_option($key, $value) {
        $options = get_option('ftc_options', array());
        $options[$key] = $value;
        update_option('ftc_options', $options);
    }
    
    /**
     * Generate UUID
     */
    public static function generate_uuid() {
        return wp_generate_uuid4();
    }
    
    /**
     * Check user permission for a person
     */
    public static function can_edit_person($person_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        // Admins can edit everything
        if (current_user_can('manage_options')) {
            return true;
        }
        
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Check if user is a manager of this person
        $manager = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tables['person_managers']} 
             WHERE person_id = %d AND user_id = %d AND role IN ('owner', 'manager')",
            $person_id,
            $user_id
        ));
        
        if ($manager) {
            return true;
        }
        
        // Check if user created the person
        $creator = $wpdb->get_var($wpdb->prepare(
            "SELECT created_by FROM {$tables['persons']} WHERE id = %d",
            $person_id
        ));
        
        return $creator == $user_id;
    }
    
    /**
     * Check user permission for a tree
     */
    public static function can_edit_tree($tree_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        if (current_user_can('manage_options')) {
            return true;
        }
        
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$tables['trees']} WHERE id = %d",
            $tree_id
        ));
        
        return $owner == $user_id;
    }
}
