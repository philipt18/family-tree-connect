<?php
/**
 * Person management class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Person {
    
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
        $this->table = $tables['persons'];
    }
    
    /**
     * Create a new person
     */
    public function create($data) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', __('You must be logged in to create a person.', 'family-tree-connect'));
        }
        
        $defaults = array(
            'uuid' => FTC_Core::generate_uuid(),
            'first_name' => '',
            'middle_name' => '',
            'surname' => '',
            'maiden_name' => '',
            'nickname' => '',
            'gender' => 'unknown',
            'birth_date' => null,
            'birth_date_calendar' => 'gregorian',
            'birth_date_approximate' => 0,
            'birth_location' => '',
            'birth_place_id' => null,
            'death_date' => null,
            'death_date_calendar' => 'gregorian',
            'death_date_approximate' => 0,
            'death_location' => '',
            'death_place_id' => null,
            'occupation' => '',
            'biography' => '',
            'notes' => '',
            'living' => 1,
            'default_photo_id' => null,
            'created_by' => $user_id,
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitize data
        $data = $this->sanitize_person_data($data);
        
        // Handle place creation/lookup
        if (!empty($data['birth_location']) && empty($data['birth_place_id'])) {
            $place = FTC_Core::get_instance()->places->get_or_create($data['birth_location']);
            if ($place) {
                $data['birth_place_id'] = $place->id;
            }
        }
        
        if (!empty($data['death_location']) && empty($data['death_place_id'])) {
            $place = FTC_Core::get_instance()->places->get_or_create($data['death_location']);
            if ($place) {
                $data['death_place_id'] = $place->id;
            }
        }
        
        $result = $wpdb->insert($this->table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create person.', 'family-tree-connect'));
        }
        
        $person_id = $wpdb->insert_id;
        
        // Add creator as owner/manager
        $wpdb->insert($tables['person_managers'], array(
            'person_id' => $person_id,
            'user_id' => $user_id,
            'role' => 'owner',
        ));
        
        // Add to tree if specified
        if (!empty($data['tree_id'])) {
            $wpdb->insert($tables['tree_persons'], array(
                'tree_id' => $data['tree_id'],
                'person_id' => $person_id,
            ));
        }
        
        // Index places
        $this->index_person_places($person_id);
        
        do_action('ftc_person_created', $person_id, $data);
        
        return $person_id;
    }
    
    /**
     * Update a person
     */
    public function update($person_id, $data) {
        global $wpdb;
        
        if (!FTC_Core::can_edit_person($person_id)) {
            return new WP_Error('permission_denied', __('You do not have permission to edit this person.', 'family-tree-connect'));
        }
        
        // Remove non-updatable fields
        unset($data['id'], $data['uuid'], $data['created_by'], $data['created_at']);
        
        // Sanitize data
        $data = $this->sanitize_person_data($data);
        
        // Handle place creation/lookup
        if (!empty($data['birth_location'])) {
            $place = FTC_Core::get_instance()->places->get_or_create($data['birth_location']);
            if ($place) {
                $data['birth_place_id'] = $place->id;
            }
        }
        
        if (!empty($data['death_location'])) {
            $place = FTC_Core::get_instance()->places->get_or_create($data['death_location']);
            if ($place) {
                $data['death_place_id'] = $place->id;
            }
        }
        
        $result = $wpdb->update($this->table, $data, array('id' => $person_id));
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update person.', 'family-tree-connect'));
        }
        
        // Re-index places
        $this->index_person_places($person_id);
        
        do_action('ftc_person_updated', $person_id, $data);
        
        return true;
    }
    
    /**
     * Get a person by ID
     */
    public function get($person_id) {
        global $wpdb;
        
        $person = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $person_id
        ));
        
        if ($person) {
            $person = $this->enrich_person($person);
        }
        
        return $person;
    }
    
    /**
     * Get a person by UUID
     */
    public function get_by_uuid($uuid) {
        global $wpdb;
        
        $person = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE uuid = %s",
            $uuid
        ));
        
        if ($person) {
            $person = $this->enrich_person($person);
        }
        
        return $person;
    }
    
    /**
     * Delete a person
     */
    public function delete($person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        if (!FTC_Core::can_edit_person($person_id)) {
            return new WP_Error('permission_denied', __('You do not have permission to delete this person.', 'family-tree-connect'));
        }
        
        // Check if person has relationships
        $has_relationships = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['relationships']} 
             WHERE person1_id = %d OR person2_id = %d",
            $person_id, $person_id
        ));
        
        if ($has_relationships > 0) {
            return new WP_Error('has_relationships', __('Cannot delete a person with existing relationships.', 'family-tree-connect'));
        }
        
        do_action('ftc_before_person_delete', $person_id);
        
        // Delete related records
        $wpdb->delete($tables['person_managers'], array('person_id' => $person_id));
        $wpdb->delete($tables['tree_persons'], array('person_id' => $person_id));
        $wpdb->delete($tables['media_persons'], array('person_id' => $person_id));
        $wpdb->delete($tables['media_crops'], array('person_id' => $person_id));
        $wpdb->delete($tables['faces'], array('person_id' => $person_id));
        $wpdb->delete($tables['custom_field_values'], array('entity_type' => 'person', 'entity_id' => $person_id));
        $wpdb->delete($tables['person_places'], array('person_id' => $person_id));
        $wpdb->delete($tables['events'], array('person_id' => $person_id));
        
        // Delete the person
        $result = $wpdb->delete($this->table, array('id' => $person_id));
        
        do_action('ftc_person_deleted', $person_id);
        
        return $result !== false;
    }
    
    /**
     * Get all people in a tree
     */
    public function get_by_tree($tree_id, $args = array()) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $defaults = array(
            'orderby' => 'surname',
            'order' => 'ASC',
            'limit' => 100,
            'offset' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = $wpdb->prepare(
            "SELECT p.* FROM {$this->table} p
             INNER JOIN {$tables['tree_persons']} tp ON p.id = tp.person_id
             WHERE tp.tree_id = %d
             ORDER BY p.{$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            $tree_id,
            $args['limit'],
            $args['offset']
        );
        
        $persons = $wpdb->get_results($sql);
        
        foreach ($persons as &$person) {
            $person = $this->enrich_person($person);
        }
        
        return $persons;
    }
    
    /**
     * Get all people created by a user
     */
    public function get_by_user($user_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'orderby' => 'surname',
            'order' => 'ASC',
            'limit' => 100,
            'offset' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE created_by = %d
             ORDER BY {$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            $user_id,
            $args['limit'],
            $args['offset']
        );
        
        $persons = $wpdb->get_results($sql);
        
        foreach ($persons as &$person) {
            $person = $this->enrich_person($person);
        }
        
        return $persons;
    }
    
    /**
     * Get people managed by a user
     */
    public function get_managed_by_user($user_id, $args = array()) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $defaults = array(
            'orderby' => 'surname',
            'order' => 'ASC',
            'limit' => 100,
            'offset' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = $wpdb->prepare(
            "SELECT p.* FROM {$this->table} p
             INNER JOIN {$tables['person_managers']} pm ON p.id = pm.person_id
             WHERE pm.user_id = %d
             ORDER BY p.{$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            $user_id,
            $args['limit'],
            $args['offset']
        );
        
        $persons = $wpdb->get_results($sql);
        
        foreach ($persons as &$person) {
            $person = $this->enrich_person($person);
        }
        
        return $persons;
    }
    
    /**
     * Enrich person object with additional data
     */
    private function enrich_person($person) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Get display name
        $person->display_name = $this->get_display_name($person);
        
        // Get managers
        $person->managers = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.*, u.display_name as user_display_name 
             FROM {$tables['person_managers']} pm
             INNER JOIN {$wpdb->users} u ON pm.user_id = u.ID
             WHERE pm.person_id = %d",
            $person->id
        ));
        
        // Get custom field values
        $person->custom_fields = FTC_Core::get_instance()->custom_fields->get_values('person', $person->id);
        
        // Get default photo URL
        if ($person->default_photo_id) {
            $media = FTC_Core::get_instance()->media->get($person->default_photo_id);
            if ($media) {
                $person->default_photo_url = $media->url;
                $person->default_photo_crop = FTC_Core::get_instance()->media->get_primary_crop($person->default_photo_id, $person->id);
            }
        }
        
        return $person;
    }
    
    /**
     * Get display name for a person
     */
    public function get_display_name($person, $format = 'full') {
        if (is_numeric($person)) {
            $person = $this->get($person);
        }
        
        if (!$person) {
            return '';
        }
        
        $parts = array();
        
        switch ($format) {
            case 'short':
                if ($person->first_name) {
                    $parts[] = $person->first_name;
                }
                if ($person->surname) {
                    $parts[] = $person->surname;
                }
                break;
                
            case 'full':
            default:
                if ($person->first_name) {
                    $parts[] = $person->first_name;
                }
                if ($person->middle_name) {
                    $parts[] = $person->middle_name;
                }
                if ($person->surname) {
                    $parts[] = $person->surname;
                }
                if ($person->maiden_name && $person->maiden_name !== $person->surname) {
                    $parts[] = '(née ' . $person->maiden_name . ')';
                }
                break;
                
            case 'surname_first':
                if ($person->surname) {
                    $parts[] = $person->surname . ',';
                }
                if ($person->first_name) {
                    $parts[] = $person->first_name;
                }
                break;
        }
        
        $name = implode(' ', $parts);
        
        if (empty($name) && $person->nickname) {
            $name = $person->nickname;
        }
        
        return $name ?: __('Unknown', 'family-tree-connect');
    }
    
    /**
     * Sanitize person data
     */
    private function sanitize_person_data($data) {
        $sanitized = array();
        
        $text_fields = array(
            'first_name', 'middle_name', 'surname', 'maiden_name', 'nickname',
            'birth_location', 'death_location', 'occupation'
        );
        
        $textarea_fields = array('biography', 'notes');
        
        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        foreach ($textarea_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_textarea_field($data[$field]);
            }
        }
        
        // Gender
        if (isset($data['gender'])) {
            $valid_genders = array('male', 'female', 'other', 'unknown');
            $sanitized['gender'] = in_array($data['gender'], $valid_genders) ? $data['gender'] : 'unknown';
        }
        
        // Dates
        $date_fields = array('birth_date', 'death_date');
        foreach ($date_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field] ? sanitize_text_field($data[$field]) : null;
            }
        }
        
        // Calendar systems
        $calendar_fields = array('birth_date_calendar', 'death_date_calendar');
        foreach ($calendar_fields as $field) {
            if (isset($data[$field])) {
                $valid_calendars = array_keys(FTC_Calendar::get_calendar_systems());
                $sanitized[$field] = in_array($data[$field], $valid_calendars) ? $data[$field] : 'gregorian';
            }
        }
        
        // Booleans
        $bool_fields = array('birth_date_approximate', 'death_date_approximate', 'living');
        foreach ($bool_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = (int) (bool) $data[$field];
            }
        }
        
        // Integer fields
        $int_fields = array('birth_place_id', 'death_place_id', 'default_photo_id', 'created_by');
        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field] ? absint($data[$field]) : null;
            }
        }
        
        // UUID
        if (isset($data['uuid'])) {
            $sanitized['uuid'] = sanitize_text_field($data['uuid']);
        }
        
        return $sanitized;
    }
    
    /**
     * Index person places
     */
    private function index_person_places($person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $person = $this->get($person_id);
        if (!$person) {
            return;
        }
        
        // Clear existing place links for this person
        $wpdb->delete($tables['person_places'], array('person_id' => $person_id));
        
        // Add birth place
        if ($person->birth_place_id) {
            $wpdb->insert($tables['person_places'], array(
                'person_id' => $person_id,
                'place_id' => $person->birth_place_id,
                'context' => 'birth',
            ));
        }
        
        // Add death place
        if ($person->death_place_id) {
            $wpdb->insert($tables['person_places'], array(
                'person_id' => $person_id,
                'place_id' => $person->death_place_id,
                'context' => 'death',
            ));
        }
    }
    
    /**
     * Get relationship to source person
     */
    public function get_relationship_to_source($person_id, $tree_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Get source person for tree
        $source_person_id = $wpdb->get_var($wpdb->prepare(
            "SELECT source_person_id FROM {$tables['trees']} WHERE id = %d",
            $tree_id
        ));
        
        if (!$source_person_id || $source_person_id == $person_id) {
            return $person_id == $source_person_id ? __('Self', 'family-tree-connect') : null;
        }
        
        return FTC_Core::get_instance()->relationship->calculate_relationship($source_person_id, $person_id);
    }
    
    /**
     * Get parents of a person
     */
    public function get_parents($person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Get family where this person is a child
        $family_id = $wpdb->get_var($wpdb->prepare(
            "SELECT family_id FROM {$tables['family_children']} WHERE person_id = %d",
            $person_id
        ));
        
        if (!$family_id) {
            return array('father' => null, 'mother' => null);
        }
        
        $family = FTC_Core::get_instance()->family->get($family_id);
        
        if (!$family) {
            return array('father' => null, 'mother' => null);
        }
        
        $parents = array('father' => null, 'mother' => null);
        
        if ($family->spouse1_id) {
            $spouse1 = $this->get($family->spouse1_id);
            if ($spouse1) {
                if ($spouse1->gender === 'male') {
                    $parents['father'] = $spouse1;
                } else {
                    $parents['mother'] = $spouse1;
                }
            }
        }
        
        if ($family->spouse2_id) {
            $spouse2 = $this->get($family->spouse2_id);
            if ($spouse2) {
                if ($spouse2->gender === 'male' && !$parents['father']) {
                    $parents['father'] = $spouse2;
                } elseif (!$parents['mother']) {
                    $parents['mother'] = $spouse2;
                }
            }
        }
        
        return $parents;
    }
    
    /**
     * Get siblings of a person
     */
    public function get_siblings($person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $family_id = $wpdb->get_var($wpdb->prepare(
            "SELECT family_id FROM {$tables['family_children']} WHERE person_id = %d",
            $person_id
        ));
        
        if (!$family_id) {
            return array();
        }
        
        $sibling_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT person_id FROM {$tables['family_children']} 
             WHERE family_id = %d AND person_id != %d
             ORDER BY birth_order ASC",
            $family_id, $person_id
        ));
        
        $siblings = array();
        foreach ($sibling_ids as $sibling_id) {
            $siblings[] = $this->get($sibling_id);
        }
        
        return $siblings;
    }
    
    /**
     * Get spouses/partners of a person
     */
    public function get_spouses($person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $spouse_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT CASE 
                WHEN spouse1_id = %d THEN spouse2_id 
                ELSE spouse1_id 
             END as spouse_id
             FROM {$tables['families']}
             WHERE spouse1_id = %d OR spouse2_id = %d",
            $person_id, $person_id, $person_id
        ));
        
        $spouses = array();
        foreach ($spouse_ids as $spouse_id) {
            if ($spouse_id) {
                $spouses[] = $this->get($spouse_id);
            }
        }
        
        return $spouses;
    }
    
    /**
     * Get children of a person
     */
    public function get_children($person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $child_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT fc.person_id 
             FROM {$tables['family_children']} fc
             INNER JOIN {$tables['families']} f ON fc.family_id = f.id
             WHERE f.spouse1_id = %d OR f.spouse2_id = %d
             ORDER BY fc.birth_order ASC",
            $person_id, $person_id
        ));
        
        $children = array();
        foreach ($child_ids as $child_id) {
            $children[] = $this->get($child_id);
        }
        
        return $children;
    }
    
    /**
     * Get all families where person is a spouse
     */
    public function get_families_as_spouse($person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $family_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$tables['families']}
             WHERE spouse1_id = %d OR spouse2_id = %d",
            $person_id, $person_id
        ));
        
        $families = array();
        foreach ($family_ids as $family_id) {
            $families[] = FTC_Core::get_instance()->family->get($family_id);
        }
        
        return $families;
    }
}
