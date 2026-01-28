<?php
/**
 * Admin functionality
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Family Tree', 'family-tree-connect'),
            __('Family Tree', 'family-tree-connect'),
            'manage_options',
            'family-tree-connect',
            array($this, 'dashboard_page'),
            'dashicons-groups',
            30
        );
        
        add_submenu_page(
            'family-tree-connect',
            __('Dashboard', 'family-tree-connect'),
            __('Dashboard', 'family-tree-connect'),
            'manage_options',
            'family-tree-connect',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'family-tree-connect',
            __('Trees', 'family-tree-connect'),
            __('Trees', 'family-tree-connect'),
            'manage_options',
            'ftc-trees',
            array($this, 'trees_page')
        );
        
        add_submenu_page(
            'family-tree-connect',
            __('Merge Requests', 'family-tree-connect'),
            __('Merge Requests', 'family-tree-connect'),
            'manage_options',
            'ftc-merge-requests',
            array($this, 'merge_requests_page')
        );
        
        add_submenu_page(
            'family-tree-connect',
            __('Places', 'family-tree-connect'),
            __('Places', 'family-tree-connect'),
            'manage_options',
            'ftc-places',
            array($this, 'places_page')
        );
        
        add_submenu_page(
            'family-tree-connect',
            __('Custom Fields', 'family-tree-connect'),
            __('Custom Fields', 'family-tree-connect'),
            'manage_options',
            'ftc-custom-fields',
            array($this, 'custom_fields_page')
        );
        
        add_submenu_page(
            'family-tree-connect',
            __('Settings', 'family-tree-connect'),
            __('Settings', 'family-tree-connect'),
            'manage_options',
            'ftc-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_init() {
        register_setting('ftc_options', 'ftc_options', array($this, 'sanitize_options'));
    }
    
    public function sanitize_options($options) {
        $sanitized = array();
        
        $sanitized['default_calendar'] = sanitize_key($options['default_calendar'] ?? 'gregorian');
        $sanitized['default_chart_type'] = sanitize_key($options['default_chart_type'] ?? 'ancestor');
        $sanitized['default_chart_generations'] = absint($options['default_chart_generations'] ?? 4);
        $sanitized['enable_facial_recognition'] = (int) (bool) ($options['enable_facial_recognition'] ?? 1);
        $sanitized['enable_email_notifications'] = (int) (bool) ($options['enable_email_notifications'] ?? 1);
        $sanitized['default_privacy'] = sanitize_key($options['default_privacy'] ?? 'private');
        $sanitized['tree_privacy_mode'] = in_array($options['tree_privacy_mode'] ?? '', array('user_choice', 'admin_enforced'))
            ? $options['tree_privacy_mode']
            : 'user_choice';

        return $sanitized;
    }
    
    public function dashboard_page() {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $stats = array(
            'persons' => $wpdb->get_var("SELECT COUNT(*) FROM {$tables['persons']}"),
            'families' => $wpdb->get_var("SELECT COUNT(*) FROM {$tables['families']}"),
            'trees' => $wpdb->get_var("SELECT COUNT(*) FROM {$tables['trees']}"),
            'media' => $wpdb->get_var("SELECT COUNT(*) FROM {$tables['media']}"),
            'places' => $wpdb->get_var("SELECT COUNT(*) FROM {$tables['places']}"),
            'pending_merges' => $wpdb->get_var("SELECT COUNT(*) FROM {$tables['merge_requests']} WHERE status = 'pending'"),
        );
        
        include FTC_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    public function trees_page() {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $trees = $wpdb->get_results("SELECT t.*, u.display_name as owner_name, 
            (SELECT COUNT(*) FROM {$tables['tree_persons']} WHERE tree_id = t.id) as person_count
            FROM {$tables['trees']} t
            LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
            ORDER BY t.name");
        
        include FTC_PLUGIN_DIR . 'admin/views/trees.php';
    }
    
    public function merge_requests_page() {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $requests = $wpdb->get_results(
            "SELECT mr.*, 
                    u1.display_name as requester_name,
                    u2.display_name as target_name
             FROM {$tables['merge_requests']} mr
             LEFT JOIN {$wpdb->users} u1 ON mr.requesting_user_id = u1.ID
             LEFT JOIN {$wpdb->users} u2 ON mr.target_user_id = u2.ID
             ORDER BY mr.created_at DESC"
        );
        
        include FTC_PLUGIN_DIR . 'admin/views/merge-requests.php';
    }
    
    public function places_page() {
        $places = FTC_Core::get_instance()->places->get_all(array('limit' => 200));
        include FTC_PLUGIN_DIR . 'admin/views/places.php';
    }
    
    public function custom_fields_page() {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $fields = $wpdb->get_results("SELECT * FROM {$tables['custom_fields']} ORDER BY applies_to, sort_order");
        include FTC_PLUGIN_DIR . 'admin/views/custom-fields.php';
    }
    
    public function settings_page() {
        $options = get_option('ftc_options', array());
        $calendars = FTC_Calendar::get_calendar_systems();
        $chart_types = FTC_Chart::get_chart_types();
        
        include FTC_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
