<?php
/**
 * Database management class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Database {
    
    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Table names
     */
    public static function get_table_names() {
        global $wpdb;
        return array(
            'persons'           => $wpdb->prefix . 'ftc_persons',
            'relationships'     => $wpdb->prefix . 'ftc_relationships',
            'families'          => $wpdb->prefix . 'ftc_families',
            'family_children'   => $wpdb->prefix . 'ftc_family_children',
            'events'            => $wpdb->prefix . 'ftc_events',
            'media'             => $wpdb->prefix . 'ftc_media',
            'media_persons'     => $wpdb->prefix . 'ftc_media_persons',
            'media_crops'       => $wpdb->prefix . 'ftc_media_crops',
            'faces'             => $wpdb->prefix . 'ftc_faces',
            'custom_fields'     => $wpdb->prefix . 'ftc_custom_fields',
            'custom_field_values' => $wpdb->prefix . 'ftc_custom_field_values',
            'places'            => $wpdb->prefix . 'ftc_places',
            'person_places'     => $wpdb->prefix . 'ftc_person_places',
            'merge_requests'    => $wpdb->prefix . 'ftc_merge_requests',
            'notifications'     => $wpdb->prefix . 'ftc_notifications',
            'trees'             => $wpdb->prefix . 'ftc_trees',
            'tree_persons'      => $wpdb->prefix . 'ftc_tree_persons',
            'person_managers'   => $wpdb->prefix . 'ftc_person_managers',
        );
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::get_table_names();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Trees table - user's family trees
        $sql = "CREATE TABLE {$tables['trees']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            source_person_id bigint(20) UNSIGNED DEFAULT NULL,
            privacy enum('public','private','shared') DEFAULT 'private',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY source_person_id (source_person_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Persons table
        $sql = "CREATE TABLE {$tables['persons']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid varchar(36) NOT NULL,
            first_name varchar(255) DEFAULT NULL,
            middle_name varchar(255) DEFAULT NULL,
            surname varchar(255) DEFAULT NULL,
            maiden_name varchar(255) DEFAULT NULL,
            nickname varchar(255) DEFAULT NULL,
            gender enum('male','female','other','unknown') DEFAULT 'unknown',
            birth_date varchar(50) DEFAULT NULL,
            birth_date_calendar varchar(20) DEFAULT 'gregorian',
            birth_date_approximate tinyint(1) DEFAULT 0,
            birth_location varchar(500) DEFAULT NULL,
            birth_place_id bigint(20) UNSIGNED DEFAULT NULL,
            death_date varchar(50) DEFAULT NULL,
            death_date_calendar varchar(20) DEFAULT 'gregorian',
            death_date_approximate tinyint(1) DEFAULT 0,
            death_location varchar(500) DEFAULT NULL,
            death_place_id bigint(20) UNSIGNED DEFAULT NULL,
            occupation varchar(500) DEFAULT NULL,
            biography text,
            notes text,
            living tinyint(1) DEFAULT 1,
            default_photo_id bigint(20) UNSIGNED DEFAULT NULL,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            KEY first_name (first_name),
            KEY surname (surname),
            KEY maiden_name (maiden_name),
            KEY birth_place_id (birth_place_id),
            KEY death_place_id (death_place_id),
            KEY created_by (created_by),
            FULLTEXT KEY name_search (first_name, middle_name, surname, maiden_name, nickname)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Person managers (for shared management after merge)
        $sql = "CREATE TABLE {$tables['person_managers']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            person_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            role enum('owner','manager','viewer') DEFAULT 'manager',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY person_user (person_id, user_id),
            KEY person_id (person_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Tree persons (links persons to trees)
        $sql = "CREATE TABLE {$tables['tree_persons']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tree_id bigint(20) UNSIGNED NOT NULL,
            person_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tree_person (tree_id, person_id),
            KEY tree_id (tree_id),
            KEY person_id (person_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Families table (marriage/partnership)
        $sql = "CREATE TABLE {$tables['families']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid varchar(36) NOT NULL,
            spouse1_id bigint(20) UNSIGNED DEFAULT NULL,
            spouse2_id bigint(20) UNSIGNED DEFAULT NULL,
            marriage_date varchar(50) DEFAULT NULL,
            marriage_date_calendar varchar(20) DEFAULT 'gregorian',
            marriage_date_approximate tinyint(1) DEFAULT 0,
            marriage_location varchar(500) DEFAULT NULL,
            marriage_place_id bigint(20) UNSIGNED DEFAULT NULL,
            divorce_date varchar(50) DEFAULT NULL,
            divorce_date_calendar varchar(20) DEFAULT 'gregorian',
            status enum('married','divorced','separated','widowed','partnership','unknown') DEFAULT 'unknown',
            default_photo_id bigint(20) UNSIGNED DEFAULT NULL,
            notes text,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            KEY spouse1_id (spouse1_id),
            KEY spouse2_id (spouse2_id),
            KEY marriage_place_id (marriage_place_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Family children
        $sql = "CREATE TABLE {$tables['family_children']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            family_id bigint(20) UNSIGNED NOT NULL,
            person_id bigint(20) UNSIGNED NOT NULL,
            birth_order int(11) DEFAULT 0,
            relationship_type enum('biological','adopted','foster','step','unknown') DEFAULT 'biological',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY family_person (family_id, person_id),
            KEY family_id (family_id),
            KEY person_id (person_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Relationships (parent-child, spouse connections)
        $sql = "CREATE TABLE {$tables['relationships']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            person1_id bigint(20) UNSIGNED NOT NULL,
            person2_id bigint(20) UNSIGNED NOT NULL,
            relationship_type varchar(50) NOT NULL,
            family_id bigint(20) UNSIGNED DEFAULT NULL,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY person_relationship (person1_id, person2_id, relationship_type),
            KEY person1_id (person1_id),
            KEY person2_id (person2_id),
            KEY family_id (family_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Events table
        $sql = "CREATE TABLE {$tables['events']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid varchar(36) NOT NULL,
            person_id bigint(20) UNSIGNED DEFAULT NULL,
            family_id bigint(20) UNSIGNED DEFAULT NULL,
            event_type varchar(100) NOT NULL,
            event_date varchar(50) DEFAULT NULL,
            event_date_calendar varchar(20) DEFAULT 'gregorian',
            event_date_approximate tinyint(1) DEFAULT 0,
            event_date_end varchar(50) DEFAULT NULL,
            location varchar(500) DEFAULT NULL,
            place_id bigint(20) UNSIGNED DEFAULT NULL,
            description text,
            notes text,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            KEY person_id (person_id),
            KEY family_id (family_id),
            KEY event_type (event_type),
            KEY place_id (place_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Media table
        $sql = "CREATE TABLE {$tables['media']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid varchar(36) NOT NULL,
            attachment_id bigint(20) UNSIGNED DEFAULT NULL,
            original_filename varchar(255) DEFAULT NULL,
            file_path varchar(500) NOT NULL,
            file_type varchar(50) NOT NULL,
            mime_type varchar(100) NOT NULL,
            title varchar(255) DEFAULT NULL,
            description text,
            media_date varchar(50) DEFAULT NULL,
            media_date_calendar varchar(20) DEFAULT 'gregorian',
            location varchar(500) DEFAULT NULL,
            place_id bigint(20) UNSIGNED DEFAULT NULL,
            event_id bigint(20) UNSIGNED DEFAULT NULL,
            media_category enum('photo','document','audio','video','other') DEFAULT 'photo',
            faces_processed tinyint(1) DEFAULT 0,
            uploaded_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            KEY attachment_id (attachment_id),
            KEY event_id (event_id),
            KEY place_id (place_id),
            KEY uploaded_by (uploaded_by)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Media persons (linking media to persons)
        $sql = "CREATE TABLE {$tables['media_persons']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            media_id bigint(20) UNSIGNED NOT NULL,
            person_id bigint(20) UNSIGNED NOT NULL,
            is_default tinyint(1) DEFAULT 0,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY media_person (media_id, person_id),
            KEY media_id (media_id),
            KEY person_id (person_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Media crops (non-destructive cropping)
        $sql = "CREATE TABLE {$tables['media_crops']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            media_id bigint(20) UNSIGNED NOT NULL,
            person_id bigint(20) UNSIGNED DEFAULT NULL,
            crop_x int(11) NOT NULL DEFAULT 0,
            crop_y int(11) NOT NULL DEFAULT 0,
            crop_width int(11) NOT NULL,
            crop_height int(11) NOT NULL,
            rotation float DEFAULT 0,
            is_primary tinyint(1) DEFAULT 0,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY media_id (media_id),
            KEY person_id (person_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Faces table (facial recognition data)
        $sql = "CREATE TABLE {$tables['faces']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            media_id bigint(20) UNSIGNED NOT NULL,
            person_id bigint(20) UNSIGNED DEFAULT NULL,
            suggested_person_id bigint(20) UNSIGNED DEFAULT NULL,
            face_x int(11) NOT NULL,
            face_y int(11) NOT NULL,
            face_width int(11) NOT NULL,
            face_height int(11) NOT NULL,
            face_encoding longtext,
            confidence float DEFAULT NULL,
            status enum('unidentified','suggested','confirmed','rejected') DEFAULT 'unidentified',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY media_id (media_id),
            KEY person_id (person_id),
            KEY suggested_person_id (suggested_person_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Custom fields
        $sql = "CREATE TABLE {$tables['custom_fields']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            field_name varchar(255) NOT NULL,
            field_label varchar(255) NOT NULL,
            field_type enum('text','textarea','date','number','select','checkbox','url') DEFAULT 'text',
            field_options text,
            applies_to enum('person','family','event') DEFAULT 'person',
            is_global tinyint(1) DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY applies_to (applies_to)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Custom field values
        $sql = "CREATE TABLE {$tables['custom_field_values']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            custom_field_id bigint(20) UNSIGNED NOT NULL,
            entity_type enum('person','family','event') NOT NULL,
            entity_id bigint(20) UNSIGNED NOT NULL,
            field_value text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY field_entity (custom_field_id, entity_type, entity_id),
            KEY custom_field_id (custom_field_id),
            KEY entity_lookup (entity_type, entity_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Places index
        $sql = "CREATE TABLE {$tables['places']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(500) NOT NULL,
            normalized_name varchar(500) NOT NULL,
            place_type varchar(100) DEFAULT NULL,
            parent_place_id bigint(20) UNSIGNED DEFAULT NULL,
            latitude decimal(10, 8) DEFAULT NULL,
            longitude decimal(11, 8) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            state_province varchar(100) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY normalized_name (normalized_name(191)),
            KEY parent_place_id (parent_place_id),
            KEY country (country),
            FULLTEXT KEY place_search (name)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Person places (linking people to places)
        $sql = "CREATE TABLE {$tables['person_places']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            person_id bigint(20) UNSIGNED NOT NULL,
            place_id bigint(20) UNSIGNED NOT NULL,
            context varchar(100) NOT NULL,
            event_id bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY person_id (person_id),
            KEY place_id (place_id),
            KEY context (context)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Merge requests
        $sql = "CREATE TABLE {$tables['merge_requests']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid varchar(36) NOT NULL,
            requesting_user_id bigint(20) UNSIGNED NOT NULL,
            target_user_id bigint(20) UNSIGNED NOT NULL,
            source_person_id bigint(20) UNSIGNED NOT NULL,
            target_person_id bigint(20) UNSIGNED NOT NULL,
            status enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
            message text,
            response_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            KEY requesting_user_id (requesting_user_id),
            KEY target_user_id (target_user_id),
            KEY source_person_id (source_person_id),
            KEY target_person_id (target_person_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Notifications
        $sql = "CREATE TABLE {$tables['notifications']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            type varchar(100) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            link varchar(500) DEFAULT NULL,
            related_id bigint(20) UNSIGNED DEFAULT NULL,
            related_type varchar(50) DEFAULT NULL,
            is_read tinyint(1) DEFAULT 0,
            email_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_read (is_read),
            KEY type (type),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Update database version
        update_option('ftc_db_version', self::DB_VERSION);
    }
    
    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = self::get_table_names();
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('ftc_db_version');
    }
    
    /**
     * Check if tables need upgrade
     */
    public static function maybe_upgrade() {
        $installed_version = get_option('ftc_db_version', '0');
        
        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            self::create_tables();
        }
    }
}
