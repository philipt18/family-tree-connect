<?php
/**
 * Plugin activator
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Activator {
    
    public static function activate() {
        FTC_Database::create_tables();
        self::create_default_options();
        self::create_directories();
        self::add_capabilities();
        flush_rewrite_rules();
        set_transient('ftc_activated', true, 30);
    }
    
    private static function create_default_options() {
        $defaults = array(
            'default_calendar' => 'gregorian',
            'default_chart_type' => 'ancestor',
            'default_chart_generations' => 4,
            'enable_facial_recognition' => 1,
            'enable_email_notifications' => 1,
        );
        
        if (!get_option('ftc_options')) {
            add_option('ftc_options', $defaults);
        }
    }
    
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $ftc_dir = $upload_dir['basedir'] . '/family-tree-connect';
        
        if (!file_exists($ftc_dir)) {
            wp_mkdir_p($ftc_dir);
            wp_mkdir_p($ftc_dir . '/photos');
            wp_mkdir_p($ftc_dir . '/documents');
            wp_mkdir_p($ftc_dir . '/exports');
        }
    }
    
    private static function add_capabilities() {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_family_trees');
            $admin->add_cap('edit_all_persons');
        }
    }
}
