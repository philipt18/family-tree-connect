<?php
/**
 * Places management class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Places {
    
    private $table;
    
    public function __construct() {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        $this->table = $tables['places'];
    }
    
    /**
     * Get or create a place
     */
    public function get_or_create($name) {
        global $wpdb;
        
        $name = trim($name);
        if (empty($name)) {
            return null;
        }
        
        $normalized = $this->normalize_place_name($name);
        
        // Check if exists
        $place = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE normalized_name = %s",
            $normalized
        ));
        
        if ($place) {
            return $place;
        }
        
        // Create new
        $data = array(
            'name' => sanitize_text_field($name),
            'normalized_name' => $normalized,
        );
        
        // Try to parse place components
        $components = $this->parse_place_name($name);
        if ($components) {
            $data = array_merge($data, $components);
        }
        
        $wpdb->insert($this->table, $data);
        
        return $this->get($wpdb->insert_id);
    }
    
    /**
     * Get a place by ID
     */
    public function get($place_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $place_id
        ));
    }
    
    /**
     * Update a place
     */
    public function update($place_id, $data) {
        global $wpdb;
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $update_data['normalized_name'] = $this->normalize_place_name($data['name']);
        }
        
        if (isset($data['latitude'])) {
            $update_data['latitude'] = floatval($data['latitude']);
        }
        
        if (isset($data['longitude'])) {
            $update_data['longitude'] = floatval($data['longitude']);
        }
        
        $text_fields = array('place_type', 'country', 'state_province', 'city');
        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        if (isset($data['parent_place_id'])) {
            $update_data['parent_place_id'] = absint($data['parent_place_id']);
        }
        
        return $wpdb->update($this->table, $update_data, array('id' => $place_id));
    }
    
    /**
     * Delete a place
     */
    public function delete($place_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Check if place is in use
        $in_use = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['person_places']} WHERE place_id = %d",
            $place_id
        ));
        
        if ($in_use > 0) {
            return new WP_Error('in_use', __('Cannot delete a place that is in use.', 'family-tree-connect'));
        }
        
        return $wpdb->delete($this->table, array('id' => $place_id));
    }
    
    /**
     * Get people at a place
     */
    public function get_people_at_place($place_id, $context = null) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $sql = "SELECT DISTINCT p.*, pp.context 
                FROM {$tables['persons']} p
                INNER JOIN {$tables['person_places']} pp ON p.id = pp.person_id
                WHERE pp.place_id = %d";
        $params = array($place_id);
        
        if ($context) {
            $sql .= " AND pp.context = %s";
            $params[] = $context;
        }
        
        $sql .= " ORDER BY p.surname, p.first_name";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        
        foreach ($results as &$person) {
            $person->display_name = FTC_Core::get_instance()->person->get_display_name($person);
        }
        
        return $results;
    }
    
    /**
     * Get place statistics
     */
    public function get_place_stats($place_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $stats = array(
            'total_people' => 0,
            'births' => 0,
            'deaths' => 0,
            'marriages' => 0,
            'other_events' => 0,
        );
        
        // Count by context
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT context, COUNT(DISTINCT person_id) as count 
             FROM {$tables['person_places']} 
             WHERE place_id = %d 
             GROUP BY context",
            $place_id
        ));
        
        foreach ($counts as $row) {
            if ($row->context === 'birth') {
                $stats['births'] = (int) $row->count;
            } elseif ($row->context === 'death') {
                $stats['deaths'] = (int) $row->count;
            } elseif ($row->context === 'marriage') {
                $stats['marriages'] = (int) $row->count;
            } else {
                $stats['other_events'] += (int) $row->count;
            }
        }
        
        $stats['total_people'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT person_id) FROM {$tables['person_places']} WHERE place_id = %d",
            $place_id
        ));
        
        return $stats;
    }
    
    /**
     * Get all places
     */
    public function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => 100,
            'offset' => 0,
            'country' => null,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $params = array();
        
        if ($args['country']) {
            $where[] = 'country = %s';
            $params[] = $args['country'];
        }
        
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE " . implode(' AND ', $where) . "
             ORDER BY {$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            ...$params
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get countries list
     */
    public function get_countries() {
        global $wpdb;
        
        return $wpdb->get_col(
            "SELECT DISTINCT country FROM {$this->table} 
             WHERE country IS NOT NULL AND country != '' 
             ORDER BY country"
        );
    }
    
    /**
     * Normalize place name for comparison
     */
    private function normalize_place_name($name) {
        $name = strtolower(trim($name));
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/[^\p{L}\p{N}\s,]/u', '', $name);
        return $name;
    }
    
    /**
     * Parse place name into components
     */
    private function parse_place_name($name) {
        $components = array();
        
        // Split by comma
        $parts = array_map('trim', explode(',', $name));
        
        if (count($parts) >= 3) {
            // Assume: City, State/Province, Country
            $components['city'] = $parts[0];
            $components['state_province'] = $parts[1];
            $components['country'] = end($parts);
        } elseif (count($parts) === 2) {
            // Could be: City, Country or State, Country
            $components['city'] = $parts[0];
            $components['country'] = $parts[1];
        } elseif (count($parts) === 1) {
            // Just a name - could be country or city
            // Try to determine based on common country names
            $common_countries = array(
                'usa', 'united states', 'canada', 'uk', 'united kingdom',
                'germany', 'france', 'israel', 'australia', 'poland',
            );
            
            if (in_array(strtolower($parts[0]), $common_countries)) {
                $components['country'] = $parts[0];
            } else {
                $components['city'] = $parts[0];
            }
        }
        
        return $components;
    }
    
    /**
     * Merge duplicate places
     */
    public function merge_places($keep_id, $merge_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Update all references
        $wpdb->update(
            $tables['persons'],
            array('birth_place_id' => $keep_id),
            array('birth_place_id' => $merge_id)
        );
        
        $wpdb->update(
            $tables['persons'],
            array('death_place_id' => $keep_id),
            array('death_place_id' => $merge_id)
        );
        
        $wpdb->update(
            $tables['families'],
            array('marriage_place_id' => $keep_id),
            array('marriage_place_id' => $merge_id)
        );
        
        $wpdb->update(
            $tables['events'],
            array('place_id' => $keep_id),
            array('place_id' => $merge_id)
        );
        
        $wpdb->update(
            $tables['media'],
            array('place_id' => $keep_id),
            array('place_id' => $merge_id)
        );
        
        $wpdb->update(
            $tables['person_places'],
            array('place_id' => $keep_id),
            array('place_id' => $merge_id)
        );
        
        // Delete merged place
        return $wpdb->delete($this->table, array('id' => $merge_id));
    }
}
