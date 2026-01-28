<?php
/**
 * Family management class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Family {
    
    /**
     * Table name
     */
    private $table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        $this->table = $tables['families'];
    }
    
    /**
     * Create a new family
     */
    public function create($data) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', __('You must be logged in to create a family.', 'family-tree-connect'));
        }
        
        $defaults = array(
            'uuid' => FTC_Core::generate_uuid(),
            'spouse1_id' => null,
            'spouse2_id' => null,
            'marriage_date' => null,
            'marriage_date_calendar' => 'gregorian',
            'marriage_date_approximate' => 0,
            'marriage_location' => '',
            'marriage_place_id' => null,
            'divorce_date' => null,
            'divorce_date_calendar' => 'gregorian',
            'status' => 'unknown',
            'default_photo_id' => null,
            'notes' => '',
            'created_by' => $user_id,
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitize data
        $data = $this->sanitize_family_data($data);
        
        // Handle place creation/lookup
        if (!empty($data['marriage_location']) && empty($data['marriage_place_id'])) {
            $place = FTC_Core::get_instance()->places->get_or_create($data['marriage_location']);
            if ($place) {
                $data['marriage_place_id'] = $place->id;
            }
        }
        
        $result = $wpdb->insert($this->table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create family.', 'family-tree-connect'));
        }
        
        $family_id = $wpdb->insert_id;
        
        // Create spouse relationships
        if ($data['spouse1_id'] && $data['spouse2_id']) {
            FTC_Core::get_instance()->relationship->create_spouse_relationship(
                $data['spouse1_id'],
                $data['spouse2_id'],
                $family_id
            );
        }
        
        do_action('ftc_family_created', $family_id, $data);
        
        return $family_id;
    }
    
    /**
     * Update a family
     */
    public function update($family_id, $data) {
        global $wpdb;
        
        $family = $this->get($family_id);
        if (!$family) {
            return new WP_Error('not_found', __('Family not found.', 'family-tree-connect'));
        }
        
        // Check permissions via spouses
        $can_edit = false;
        if ($family->spouse1_id && FTC_Core::can_edit_person($family->spouse1_id)) {
            $can_edit = true;
        }
        if ($family->spouse2_id && FTC_Core::can_edit_person($family->spouse2_id)) {
            $can_edit = true;
        }
        
        if (!$can_edit && !current_user_can('manage_options')) {
            return new WP_Error('permission_denied', __('You do not have permission to edit this family.', 'family-tree-connect'));
        }
        
        // Remove non-updatable fields
        unset($data['id'], $data['uuid'], $data['created_by'], $data['created_at']);
        
        // Sanitize data
        $data = $this->sanitize_family_data($data);
        
        // Handle place creation/lookup
        if (!empty($data['marriage_location'])) {
            $place = FTC_Core::get_instance()->places->get_or_create($data['marriage_location']);
            if ($place) {
                $data['marriage_place_id'] = $place->id;
            }
        }
        
        $result = $wpdb->update($this->table, $data, array('id' => $family_id));
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update family.', 'family-tree-connect'));
        }
        
        do_action('ftc_family_updated', $family_id, $data);
        
        return true;
    }
    
    /**
     * Get a family by ID
     */
    public function get($family_id) {
        global $wpdb;
        
        $family = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $family_id
        ));
        
        if ($family) {
            $family = $this->enrich_family($family);
        }
        
        return $family;
    }
    
    /**
     * Get family by UUID
     */
    public function get_by_uuid($uuid) {
        global $wpdb;
        
        $family = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE uuid = %s",
            $uuid
        ));
        
        if ($family) {
            $family = $this->enrich_family($family);
        }
        
        return $family;
    }
    
    /**
     * Delete a family
     */
    public function delete($family_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $family = $this->get($family_id);
        if (!$family) {
            return new WP_Error('not_found', __('Family not found.', 'family-tree-connect'));
        }
        
        // Check permissions
        $can_edit = false;
        if ($family->spouse1_id && FTC_Core::can_edit_person($family->spouse1_id)) {
            $can_edit = true;
        }
        if ($family->spouse2_id && FTC_Core::can_edit_person($family->spouse2_id)) {
            $can_edit = true;
        }
        
        if (!$can_edit && !current_user_can('manage_options')) {
            return new WP_Error('permission_denied', __('You do not have permission to delete this family.', 'family-tree-connect'));
        }
        
        do_action('ftc_before_family_delete', $family_id);
        
        // Delete related records
        $wpdb->delete($tables['family_children'], array('family_id' => $family_id));
        $wpdb->delete($tables['relationships'], array('family_id' => $family_id));
        $wpdb->delete($tables['events'], array('family_id' => $family_id));
        $wpdb->delete($tables['custom_field_values'], array('entity_type' => 'family', 'entity_id' => $family_id));
        
        // Delete the family
        $result = $wpdb->delete($this->table, array('id' => $family_id));
        
        do_action('ftc_family_deleted', $family_id);
        
        return $result !== false;
    }
    
    /**
     * Add a child to a family
     */
    public function add_child($family_id, $person_id, $data = array()) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $defaults = array(
            'birth_order' => 0,
            'relationship_type' => 'biological',
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Check if already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tables['family_children']} 
             WHERE family_id = %d AND person_id = %d",
            $family_id, $person_id
        ));
        
        if ($exists) {
            return new WP_Error('exists', __('This person is already a child of this family.', 'family-tree-connect'));
        }
        
        $result = $wpdb->insert($tables['family_children'], array(
            'family_id' => $family_id,
            'person_id' => $person_id,
            'birth_order' => absint($data['birth_order']),
            'relationship_type' => $data['relationship_type'],
        ));
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to add child to family.', 'family-tree-connect'));
        }
        
        // Create parent-child relationships
        $family = $this->get($family_id);
        if ($family->spouse1_id) {
            FTC_Core::get_instance()->relationship->create_parent_child_relationship(
                $family->spouse1_id,
                $person_id,
                $family_id
            );
        }
        if ($family->spouse2_id) {
            FTC_Core::get_instance()->relationship->create_parent_child_relationship(
                $family->spouse2_id,
                $person_id,
                $family_id
            );
        }
        
        do_action('ftc_family_child_added', $family_id, $person_id);
        
        return true;
    }
    
    /**
     * Remove a child from a family
     */
    public function remove_child($family_id, $person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Delete relationships
        $family = $this->get($family_id);
        if ($family->spouse1_id) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$tables['relationships']} 
                 WHERE person1_id = %d AND person2_id = %d AND relationship_type = 'parent_child'",
                $family->spouse1_id, $person_id
            ));
        }
        if ($family->spouse2_id) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$tables['relationships']} 
                 WHERE person1_id = %d AND person2_id = %d AND relationship_type = 'parent_child'",
                $family->spouse2_id, $person_id
            ));
        }
        
        $result = $wpdb->delete($tables['family_children'], array(
            'family_id' => $family_id,
            'person_id' => $person_id,
        ));
        
        do_action('ftc_family_child_removed', $family_id, $person_id);
        
        return $result !== false;
    }
    
    /**
     * Get children of a family
     */
    public function get_children($family_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $children = $wpdb->get_results($wpdb->prepare(
            "SELECT fc.*, p.* FROM {$tables['family_children']} fc
             INNER JOIN {$tables['persons']} p ON fc.person_id = p.id
             WHERE fc.family_id = %d
             ORDER BY fc.birth_order ASC, p.birth_date ASC",
            $family_id
        ));
        
        foreach ($children as &$child) {
            $child->display_name = FTC_Core::get_instance()->person->get_display_name($child);
        }
        
        return $children;
    }
    
    /**
     * Reorder children
     */
    public function reorder_children($family_id, $child_order) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        foreach ($child_order as $order => $person_id) {
            $wpdb->update(
                $tables['family_children'],
                array('birth_order' => $order),
                array(
                    'family_id' => $family_id,
                    'person_id' => $person_id,
                )
            );
        }
        
        return true;
    }
    
    /**
     * Enrich family object
     */
    private function enrich_family($family) {
        // Get spouse objects
        if ($family->spouse1_id) {
            $family->spouse1 = FTC_Core::get_instance()->person->get($family->spouse1_id);
        }
        
        if ($family->spouse2_id) {
            $family->spouse2 = FTC_Core::get_instance()->person->get($family->spouse2_id);
        }
        
        // Get children
        $family->children = $this->get_children($family->id);
        
        // Get custom field values
        $family->custom_fields = FTC_Core::get_instance()->custom_fields->get_values('family', $family->id);
        
        // Get display title
        $family->display_title = $this->get_display_title($family);
        
        // Get default photo URL
        if ($family->default_photo_id) {
            $media = FTC_Core::get_instance()->media->get($family->default_photo_id);
            if ($media) {
                $family->default_photo_url = $media->url;
            }
        }
        
        return $family;
    }
    
    /**
     * Get display title for family
     */
    public function get_display_title($family) {
        $parts = array();
        
        if (isset($family->spouse1) && $family->spouse1) {
            $parts[] = FTC_Core::get_instance()->person->get_display_name($family->spouse1, 'short');
        }
        
        if (isset($family->spouse2) && $family->spouse2) {
            $parts[] = FTC_Core::get_instance()->person->get_display_name($family->spouse2, 'short');
        }
        
        if (empty($parts)) {
            return __('Unknown Family', 'family-tree-connect');
        }
        
        return implode(' & ', $parts) . ' ' . __('Family', 'family-tree-connect');
    }
    
    /**
     * Sanitize family data
     */
    private function sanitize_family_data($data) {
        $sanitized = array();
        
        // Text fields
        if (isset($data['marriage_location'])) {
            $sanitized['marriage_location'] = sanitize_text_field($data['marriage_location']);
        }
        
        if (isset($data['notes'])) {
            $sanitized['notes'] = sanitize_textarea_field($data['notes']);
        }
        
        // Integer fields
        $int_fields = array('spouse1_id', 'spouse2_id', 'marriage_place_id', 'default_photo_id', 'created_by');
        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field] ? absint($data[$field]) : null;
            }
        }
        
        // Date fields
        if (isset($data['marriage_date'])) {
            $sanitized['marriage_date'] = $data['marriage_date'] ? sanitize_text_field($data['marriage_date']) : null;
        }
        
        if (isset($data['divorce_date'])) {
            $sanitized['divorce_date'] = $data['divorce_date'] ? sanitize_text_field($data['divorce_date']) : null;
        }
        
        // Calendar
        if (isset($data['marriage_date_calendar'])) {
            $valid_calendars = array_keys(FTC_Calendar::get_calendar_systems());
            $sanitized['marriage_date_calendar'] = in_array($data['marriage_date_calendar'], $valid_calendars) 
                ? $data['marriage_date_calendar'] : 'gregorian';
        }
        
        if (isset($data['divorce_date_calendar'])) {
            $valid_calendars = array_keys(FTC_Calendar::get_calendar_systems());
            $sanitized['divorce_date_calendar'] = in_array($data['divorce_date_calendar'], $valid_calendars) 
                ? $data['divorce_date_calendar'] : 'gregorian';
        }
        
        // Boolean
        if (isset($data['marriage_date_approximate'])) {
            $sanitized['marriage_date_approximate'] = (int) (bool) $data['marriage_date_approximate'];
        }
        
        // Status
        if (isset($data['status'])) {
            $valid_statuses = array('married', 'divorced', 'separated', 'widowed', 'partnership', 'unknown');
            $sanitized['status'] = in_array($data['status'], $valid_statuses) ? $data['status'] : 'unknown';
        }
        
        // UUID
        if (isset($data['uuid'])) {
            $sanitized['uuid'] = sanitize_text_field($data['uuid']);
        }
        
        return $sanitized;
    }
    
    /**
     * Get family view data (includes grandparents)
     */
    public function get_family_view($family_id) {
        $family = $this->get($family_id);
        if (!$family) {
            return null;
        }
        
        $view = array(
            'family' => $family,
            'grandparents' => array(
                'paternal' => array('grandfather' => null, 'grandmother' => null),
                'maternal' => array('grandfather' => null, 'grandmother' => null),
            ),
        );
        
        // Get grandparents through spouse1 (typically father)
        if ($family->spouse1) {
            $parents = FTC_Core::get_instance()->person->get_parents($family->spouse1->id);
            if ($parents['father']) {
                $view['grandparents']['paternal']['grandfather'] = $parents['father'];
            }
            if ($parents['mother']) {
                $view['grandparents']['paternal']['grandmother'] = $parents['mother'];
            }
        }
        
        // Get grandparents through spouse2 (typically mother)
        if ($family->spouse2) {
            $parents = FTC_Core::get_instance()->person->get_parents($family->spouse2->id);
            if ($parents['father']) {
                $view['grandparents']['maternal']['grandfather'] = $parents['father'];
            }
            if ($parents['mother']) {
                $view['grandparents']['maternal']['grandmother'] = $parents['mother'];
            }
        }
        
        return $view;
    }
    
    /**
     * Find or create family for two people
     */
    public function find_or_create($spouse1_id, $spouse2_id) {
        global $wpdb;
        
        // Check if family already exists
        $family_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} 
             WHERE (spouse1_id = %d AND spouse2_id = %d) 
                OR (spouse1_id = %d AND spouse2_id = %d)",
            $spouse1_id, $spouse2_id,
            $spouse2_id, $spouse1_id
        ));
        
        if ($family_id) {
            return $this->get($family_id);
        }
        
        // Create new family
        $new_family_id = $this->create(array(
            'spouse1_id' => $spouse1_id,
            'spouse2_id' => $spouse2_id,
        ));
        
        if (is_wp_error($new_family_id)) {
            return $new_family_id;
        }
        
        return $this->get($new_family_id);
    }
}
