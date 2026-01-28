<?php
/**
 * Event management class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Event {
    
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
        $this->table = $tables['events'];
    }
    
    /**
     * Get predefined event types
     */
    public static function get_event_types() {
        return apply_filters('ftc_event_types', array(
            'birth' => array(
                'label' => __('Birth', 'family-tree-connect'),
                'category' => 'vital',
                'icon' => 'baby',
            ),
            'death' => array(
                'label' => __('Death', 'family-tree-connect'),
                'category' => 'vital',
                'icon' => 'cross',
            ),
            'marriage' => array(
                'label' => __('Marriage', 'family-tree-connect'),
                'category' => 'family',
                'icon' => 'rings',
            ),
            'divorce' => array(
                'label' => __('Divorce', 'family-tree-connect'),
                'category' => 'family',
                'icon' => 'broken-heart',
            ),
            'baptism' => array(
                'label' => __('Baptism', 'family-tree-connect'),
                'category' => 'religious',
                'icon' => 'water',
            ),
            'christening' => array(
                'label' => __('Christening', 'family-tree-connect'),
                'category' => 'religious',
                'icon' => 'church',
            ),
            'bar_mitzvah' => array(
                'label' => __('Bar Mitzvah', 'family-tree-connect'),
                'category' => 'religious',
                'icon' => 'star-of-david',
            ),
            'bat_mitzvah' => array(
                'label' => __('Bat Mitzvah', 'family-tree-connect'),
                'category' => 'religious',
                'icon' => 'star-of-david',
            ),
            'confirmation' => array(
                'label' => __('Confirmation', 'family-tree-connect'),
                'category' => 'religious',
                'icon' => 'church',
            ),
            'first_communion' => array(
                'label' => __('First Communion', 'family-tree-connect'),
                'category' => 'religious',
                'icon' => 'chalice',
            ),
            'graduation' => array(
                'label' => __('Graduation', 'family-tree-connect'),
                'category' => 'education',
                'icon' => 'graduation-cap',
            ),
            'immigration' => array(
                'label' => __('Immigration', 'family-tree-connect'),
                'category' => 'residence',
                'icon' => 'ship',
            ),
            'emigration' => array(
                'label' => __('Emigration', 'family-tree-connect'),
                'category' => 'residence',
                'icon' => 'plane',
            ),
            'naturalization' => array(
                'label' => __('Naturalization', 'family-tree-connect'),
                'category' => 'legal',
                'icon' => 'flag',
            ),
            'military_service' => array(
                'label' => __('Military Service', 'family-tree-connect'),
                'category' => 'military',
                'icon' => 'shield',
            ),
            'retirement' => array(
                'label' => __('Retirement', 'family-tree-connect'),
                'category' => 'occupation',
                'icon' => 'clock',
            ),
            'burial' => array(
                'label' => __('Burial', 'family-tree-connect'),
                'category' => 'vital',
                'icon' => 'grave',
            ),
            'cremation' => array(
                'label' => __('Cremation', 'family-tree-connect'),
                'category' => 'vital',
                'icon' => 'fire',
            ),
            'residence' => array(
                'label' => __('Residence', 'family-tree-connect'),
                'category' => 'residence',
                'icon' => 'home',
            ),
            'occupation' => array(
                'label' => __('Occupation', 'family-tree-connect'),
                'category' => 'occupation',
                'icon' => 'briefcase',
            ),
            'census' => array(
                'label' => __('Census', 'family-tree-connect'),
                'category' => 'record',
                'icon' => 'document',
            ),
            'other' => array(
                'label' => __('Other Event', 'family-tree-connect'),
                'category' => 'other',
                'icon' => 'calendar',
            ),
        ));
    }
    
    /**
     * Get event categories
     */
    public static function get_event_categories() {
        return array(
            'vital' => __('Vital Records', 'family-tree-connect'),
            'family' => __('Family Events', 'family-tree-connect'),
            'religious' => __('Religious Events', 'family-tree-connect'),
            'education' => __('Education', 'family-tree-connect'),
            'residence' => __('Residence', 'family-tree-connect'),
            'legal' => __('Legal', 'family-tree-connect'),
            'military' => __('Military', 'family-tree-connect'),
            'occupation' => __('Occupation', 'family-tree-connect'),
            'record' => __('Records', 'family-tree-connect'),
            'other' => __('Other', 'family-tree-connect'),
        );
    }
    
    /**
     * Create an event
     */
    public function create($data) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', __('You must be logged in to create an event.', 'family-tree-connect'));
        }
        
        $defaults = array(
            'uuid' => FTC_Core::generate_uuid(),
            'person_id' => null,
            'family_id' => null,
            'event_type' => 'other',
            'event_date' => null,
            'event_date_calendar' => 'gregorian',
            'event_date_approximate' => 0,
            'event_date_end' => null,
            'location' => '',
            'place_id' => null,
            'description' => '',
            'notes' => '',
            'created_by' => $user_id,
        );
        
        $data = wp_parse_args($data, $defaults);
        $data = $this->sanitize_event_data($data);
        
        // Handle place creation/lookup
        if (!empty($data['location']) && empty($data['place_id'])) {
            $place = FTC_Core::get_instance()->places->get_or_create($data['location']);
            if ($place) {
                $data['place_id'] = $place->id;
            }
        }
        
        $result = $wpdb->insert($this->table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create event.', 'family-tree-connect'));
        }
        
        $event_id = $wpdb->insert_id;
        
        // Index place
        $this->index_event_place($event_id);
        
        do_action('ftc_event_created', $event_id, $data);
        
        return $event_id;
    }
    
    /**
     * Update an event
     */
    public function update($event_id, $data) {
        global $wpdb;
        
        $event = $this->get($event_id);
        if (!$event) {
            return new WP_Error('not_found', __('Event not found.', 'family-tree-connect'));
        }
        
        // Check permissions
        if ($event->person_id && !FTC_Core::can_edit_person($event->person_id)) {
            return new WP_Error('permission_denied', __('You do not have permission to edit this event.', 'family-tree-connect'));
        }
        
        unset($data['id'], $data['uuid'], $data['created_by'], $data['created_at']);
        $data = $this->sanitize_event_data($data);
        
        // Handle place
        if (!empty($data['location'])) {
            $place = FTC_Core::get_instance()->places->get_or_create($data['location']);
            if ($place) {
                $data['place_id'] = $place->id;
            }
        }
        
        $result = $wpdb->update($this->table, $data, array('id' => $event_id));
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update event.', 'family-tree-connect'));
        }
        
        // Re-index place
        $this->index_event_place($event_id);
        
        do_action('ftc_event_updated', $event_id, $data);
        
        return true;
    }
    
    /**
     * Get an event
     */
    public function get($event_id) {
        global $wpdb;
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $event_id
        ));
        
        if ($event) {
            $event = $this->enrich_event($event);
        }
        
        return $event;
    }
    
    /**
     * Get event by UUID
     */
    public function get_by_uuid($uuid) {
        global $wpdb;
        
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE uuid = %s",
            $uuid
        ));
        
        if ($event) {
            $event = $this->enrich_event($event);
        }
        
        return $event;
    }
    
    /**
     * Delete an event
     */
    public function delete($event_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $event = $this->get($event_id);
        if (!$event) {
            return new WP_Error('not_found', __('Event not found.', 'family-tree-connect'));
        }
        
        // Check permissions
        if ($event->person_id && !FTC_Core::can_edit_person($event->person_id)) {
            return new WP_Error('permission_denied', __('You do not have permission to delete this event.', 'family-tree-connect'));
        }
        
        do_action('ftc_before_event_delete', $event_id);
        
        // Delete related records
        $wpdb->delete($tables['custom_field_values'], array('entity_type' => 'event', 'entity_id' => $event_id));
        $wpdb->delete($tables['person_places'], array('event_id' => $event_id));
        
        // Update media that references this event
        $wpdb->update($tables['media'], array('event_id' => null), array('event_id' => $event_id));
        
        $result = $wpdb->delete($this->table, array('id' => $event_id));
        
        do_action('ftc_event_deleted', $event_id);
        
        return $result !== false;
    }
    
    /**
     * Get events for a person
     */
    public function get_by_person($person_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'orderby' => 'event_date',
            'order' => 'ASC',
            'event_type' => null,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE person_id = %d",
            $person_id
        );
        
        if ($args['event_type']) {
            $sql .= $wpdb->prepare(" AND event_type = %s", $args['event_type']);
        }
        
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        $events = $wpdb->get_results($sql);
        
        foreach ($events as &$event) {
            $event = $this->enrich_event($event);
        }
        
        return $events;
    }
    
    /**
     * Get events for a family
     */
    public function get_by_family($family_id) {
        global $wpdb;
        
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE family_id = %d ORDER BY event_date ASC",
            $family_id
        ));
        
        foreach ($events as &$event) {
            $event = $this->enrich_event($event);
        }
        
        return $events;
    }
    
    /**
     * Get timeline for a person
     */
    public function get_timeline($person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $person = FTC_Core::get_instance()->person->get($person_id);
        if (!$person) {
            return array();
        }
        
        $timeline = array();
        
        // Add birth event
        if ($person->birth_date) {
            $timeline[] = array(
                'type' => 'birth',
                'date' => $person->birth_date,
                'date_calendar' => $person->birth_date_calendar,
                'label' => __('Birth', 'family-tree-connect'),
                'location' => $person->birth_location,
                'description' => '',
                'is_vital' => true,
            );
        }
        
        // Get all events
        $events = $this->get_by_person($person_id);
        foreach ($events as $event) {
            $event_types = self::get_event_types();
            $timeline[] = array(
                'type' => $event->event_type,
                'date' => $event->event_date,
                'date_calendar' => $event->event_date_calendar,
                'label' => isset($event_types[$event->event_type]) ? $event_types[$event->event_type]['label'] : $event->event_type,
                'location' => $event->location,
                'description' => $event->description,
                'event_id' => $event->id,
                'is_vital' => false,
            );
        }
        
        // Add death event
        if ($person->death_date) {
            $timeline[] = array(
                'type' => 'death',
                'date' => $person->death_date,
                'date_calendar' => $person->death_date_calendar,
                'label' => __('Death', 'family-tree-connect'),
                'location' => $person->death_location,
                'description' => '',
                'is_vital' => true,
            );
        }
        
        // Sort by date
        usort($timeline, function($a, $b) {
            $date_a = FTC_Calendar::to_gregorian($a['date'], $a['date_calendar']);
            $date_b = FTC_Calendar::to_gregorian($b['date'], $b['date_calendar']);
            return strcmp($date_a, $date_b);
        });
        
        return $timeline;
    }
    
    /**
     * Enrich event object
     */
    private function enrich_event($event) {
        // Get event type info
        $event_types = self::get_event_types();
        if (isset($event_types[$event->event_type])) {
            $event->type_info = $event_types[$event->event_type];
        }
        
        // Get associated media
        $event->media = FTC_Core::get_instance()->media->get_by_event($event->id);
        
        // Get custom field values
        $event->custom_fields = FTC_Core::get_instance()->custom_fields->get_values('event', $event->id);
        
        // Format date for display
        $event->formatted_date = FTC_Calendar::format_date(
            $event->event_date,
            $event->event_date_calendar,
            $event->event_date_approximate
        );
        
        return $event;
    }
    
    /**
     * Sanitize event data
     */
    private function sanitize_event_data($data) {
        $sanitized = array();
        
        // Text fields
        if (isset($data['location'])) {
            $sanitized['location'] = sanitize_text_field($data['location']);
        }
        
        if (isset($data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['notes'])) {
            $sanitized['notes'] = sanitize_textarea_field($data['notes']);
        }
        
        // Event type
        if (isset($data['event_type'])) {
            $sanitized['event_type'] = sanitize_key($data['event_type']);
        }
        
        // Integer fields
        $int_fields = array('person_id', 'family_id', 'place_id', 'created_by');
        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field] ? absint($data[$field]) : null;
            }
        }
        
        // Date fields
        if (isset($data['event_date'])) {
            $sanitized['event_date'] = $data['event_date'] ? sanitize_text_field($data['event_date']) : null;
        }
        
        if (isset($data['event_date_end'])) {
            $sanitized['event_date_end'] = $data['event_date_end'] ? sanitize_text_field($data['event_date_end']) : null;
        }
        
        // Calendar
        if (isset($data['event_date_calendar'])) {
            $valid_calendars = array_keys(FTC_Calendar::get_calendar_systems());
            $sanitized['event_date_calendar'] = in_array($data['event_date_calendar'], $valid_calendars) 
                ? $data['event_date_calendar'] : 'gregorian';
        }
        
        // Boolean
        if (isset($data['event_date_approximate'])) {
            $sanitized['event_date_approximate'] = (int) (bool) $data['event_date_approximate'];
        }
        
        // UUID
        if (isset($data['uuid'])) {
            $sanitized['uuid'] = sanitize_text_field($data['uuid']);
        }
        
        return $sanitized;
    }
    
    /**
     * Index event place
     */
    private function index_event_place($event_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $event = $this->get($event_id);
        if (!$event || !$event->person_id || !$event->place_id) {
            return;
        }
        
        // Delete existing
        $wpdb->delete($tables['person_places'], array('event_id' => $event_id));
        
        // Add new
        $wpdb->insert($tables['person_places'], array(
            'person_id' => $event->person_id,
            'place_id' => $event->place_id,
            'context' => $event->event_type,
            'event_id' => $event_id,
        ));
    }
}
