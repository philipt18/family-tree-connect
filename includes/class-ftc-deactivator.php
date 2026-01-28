<?php
/**
 * Plugin deactivator
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Deactivator {
    
    public static function deactivate() {
        flush_rewrite_rules();
        wp_clear_scheduled_hook('ftc_daily_cleanup');
    }
}
