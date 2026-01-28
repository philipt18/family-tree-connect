<?php
/**
 * AJAX handler class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Ajax {
    
    public function __construct() {
        // Person actions
        add_action('wp_ajax_ftc_create_person', array($this, 'create_person'));
        add_action('wp_ajax_ftc_update_person', array($this, 'update_person'));
        add_action('wp_ajax_ftc_delete_person', array($this, 'delete_person'));
        add_action('wp_ajax_ftc_get_person', array($this, 'get_person'));
        
        // Family actions
        add_action('wp_ajax_ftc_create_family', array($this, 'create_family'));
        add_action('wp_ajax_ftc_update_family', array($this, 'update_family'));
        add_action('wp_ajax_ftc_add_child', array($this, 'add_child'));
        add_action('wp_ajax_ftc_remove_child', array($this, 'remove_child'));
        
        // Event actions
        add_action('wp_ajax_ftc_create_event', array($this, 'create_event'));
        add_action('wp_ajax_ftc_update_event', array($this, 'update_event'));
        add_action('wp_ajax_ftc_delete_event', array($this, 'delete_event'));
        
        // Media actions
        add_action('wp_ajax_ftc_upload_media', array($this, 'upload_media'));
        add_action('wp_ajax_ftc_update_media', array($this, 'update_media'));
        add_action('wp_ajax_ftc_delete_media', array($this, 'delete_media'));
        add_action('wp_ajax_ftc_link_media_person', array($this, 'link_media_person'));
        add_action('wp_ajax_ftc_save_crop', array($this, 'save_crop'));
        add_action('wp_ajax_ftc_set_default_photo', array($this, 'set_default_photo'));
        
        // Merge actions
        add_action('wp_ajax_ftc_create_merge_request', array($this, 'create_merge_request'));
        add_action('wp_ajax_ftc_approve_merge', array($this, 'approve_merge'));
        add_action('wp_ajax_ftc_reject_merge', array($this, 'reject_merge'));
        add_action('wp_ajax_ftc_find_matches', array($this, 'find_matches'));
        
        // Search actions
        add_action('wp_ajax_ftc_search', array($this, 'search'));
        add_action('wp_ajax_nopriv_ftc_search', array($this, 'search'));
        add_action('wp_ajax_ftc_suggestions', array($this, 'suggestions'));
        
        // Chart actions
        add_action('wp_ajax_ftc_get_chart', array($this, 'get_chart'));
        add_action('wp_ajax_nopriv_ftc_get_chart', array($this, 'get_chart'));
        
        // Notification actions
        add_action('wp_ajax_ftc_mark_notification_read', array($this, 'mark_notification_read'));
        add_action('wp_ajax_ftc_get_notifications', array($this, 'get_notifications'));
        
        // Face recognition
        add_action('wp_ajax_ftc_store_face', array($this, 'store_face'));
        add_action('wp_ajax_ftc_identify_face', array($this, 'identify_face'));
        add_action('wp_ajax_ftc_confirm_face', array($this, 'confirm_face'));
        
        // Custom fields
        add_action('wp_ajax_ftc_create_custom_field', array($this, 'create_custom_field'));
        add_action('wp_ajax_ftc_save_custom_values', array($this, 'save_custom_values'));
    }
    
    /**
     * Verify nonce and return error if invalid
     */
    private function verify_nonce() {
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', 'ftc_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'family-tree-connect')));
        }
    }
    
    /**
     * Create person
     */
    public function create_person() {
        $this->verify_nonce();
        
        $data = $_POST['person'] ?? array();
        $result = FTC_Core::get_instance()->person->create($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        $person = FTC_Core::get_instance()->person->get($result);
        wp_send_json_success(array('person' => $person));
    }
    
    /**
     * Update person
     */
    public function update_person() {
        $this->verify_nonce();
        
        $person_id = absint($_POST['person_id'] ?? 0);
        $data = $_POST['person'] ?? array();
        
        $result = FTC_Core::get_instance()->person->update($person_id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        $person = FTC_Core::get_instance()->person->get($person_id);
        wp_send_json_success(array('person' => $person));
    }
    
    /**
     * Delete person
     */
    public function delete_person() {
        $this->verify_nonce();
        
        $person_id = absint($_POST['person_id'] ?? 0);
        $result = FTC_Core::get_instance()->person->delete($person_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success();
    }
    
    /**
     * Get person
     */
    public function get_person() {
        $this->verify_nonce();
        
        $person_id = absint($_GET['person_id'] ?? 0);
        $person = FTC_Core::get_instance()->person->get($person_id);
        
        if (!$person) {
            wp_send_json_error(array('message' => __('Person not found.', 'family-tree-connect')));
        }
        
        wp_send_json_success(array('person' => $person));
    }
    
    /**
     * Create family
     */
    public function create_family() {
        $this->verify_nonce();
        
        $data = $_POST['family'] ?? array();
        $result = FTC_Core::get_instance()->family->create($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        $family = FTC_Core::get_instance()->family->get($result);
        wp_send_json_success(array('family' => $family));
    }
    
    /**
     * Update family
     */
    public function update_family() {
        $this->verify_nonce();
        
        $family_id = absint($_POST['family_id'] ?? 0);
        $data = $_POST['family'] ?? array();
        
        $result = FTC_Core::get_instance()->family->update($family_id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        $family = FTC_Core::get_instance()->family->get($family_id);
        wp_send_json_success(array('family' => $family));
    }
    
    /**
     * Add child to family
     */
    public function add_child() {
        $this->verify_nonce();
        
        $family_id = absint($_POST['family_id'] ?? 0);
        $person_id = absint($_POST['person_id'] ?? 0);
        $data = $_POST['data'] ?? array();
        
        $result = FTC_Core::get_instance()->family->add_child($family_id, $person_id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success();
    }
    
    /**
     * Remove child from family
     */
    public function remove_child() {
        $this->verify_nonce();
        
        $family_id = absint($_POST['family_id'] ?? 0);
        $person_id = absint($_POST['person_id'] ?? 0);
        
        $result = FTC_Core::get_instance()->family->remove_child($family_id, $person_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success();
    }
    
    /**
     * Create event
     */
    public function create_event() {
        $this->verify_nonce();
        
        $data = $_POST['event'] ?? array();
        $result = FTC_Core::get_instance()->event->create($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        $event = FTC_Core::get_instance()->event->get($result);
        wp_send_json_success(array('event' => $event));
    }
    
    /**
     * Update event
     */
    public function update_event() {
        $this->verify_nonce();
        
        $event_id = absint($_POST['event_id'] ?? 0);
        $data = $_POST['event'] ?? array();
        
        $result = FTC_Core::get_instance()->event->update($event_id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        $event = FTC_Core::get_instance()->event->get($event_id);
        wp_send_json_success(array('event' => $event));
    }
    
    /**
     * Delete event
     */
    public function delete_event() {
        $this->verify_nonce();
        
        $event_id = absint($_POST['event_id'] ?? 0);
        $result = FTC_Core::get_instance()->event->delete($event_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success();
    }
    
    /**
     * Upload media
     */
    public function upload_media() {
        $this->verify_nonce();
        
        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file uploaded.', 'family-tree-connect')));
        }
        
        $data = array(
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'person_ids' => array_map('absint', $_POST['person_ids'] ?? array()),
            'event_id' => absint($_POST['event_id'] ?? 0),
        );
        
        $result = FTC_Core::get_instance()->media->upload($_FILES['file'], $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        $media = FTC_Core::get_instance()->media->get($result);
        wp_send_json_success(array('media' => $media));
    }
    
    /**
     * Save crop
     */
    public function save_crop() {
        $this->verify_nonce();
        
        $media_id = absint($_POST['media_id'] ?? 0);
        $crop_data = array(
            'person_id' => absint($_POST['person_id'] ?? 0),
            'crop_x' => intval($_POST['crop_x'] ?? 0),
            'crop_y' => intval($_POST['crop_y'] ?? 0),
            'crop_width' => absint($_POST['crop_width'] ?? 100),
            'crop_height' => absint($_POST['crop_height'] ?? 100),
            'rotation' => floatval($_POST['rotation'] ?? 0),
            'is_primary' => (bool) ($_POST['is_primary'] ?? false),
        );
        
        $result = FTC_Core::get_instance()->media->create_crop($media_id, $crop_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('crop_id' => $result));
    }
    
    /**
     * Set default photo
     */
    public function set_default_photo() {
        $this->verify_nonce();
        
        $media_id = absint($_POST['media_id'] ?? 0);
        $person_id = absint($_POST['person_id'] ?? 0);
        
        $result = FTC_Core::get_instance()->media->set_as_default_photo($media_id, $person_id);
        
        wp_send_json_success();
    }
    
    /**
     * Create merge request
     */
    public function create_merge_request() {
        $this->verify_nonce();
        
        $data = array(
            'target_user_id' => absint($_POST['target_user_id'] ?? 0),
            'source_person_id' => absint($_POST['source_person_id'] ?? 0),
            'target_person_id' => absint($_POST['target_person_id'] ?? 0),
            'message' => sanitize_textarea_field($_POST['message'] ?? ''),
        );
        
        $result = FTC_Core::get_instance()->merge->create_request($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('request_id' => $result));
    }
    
    /**
     * Approve merge
     */
    public function approve_merge() {
        $this->verify_nonce();
        
        $request_id = absint($_POST['request_id'] ?? 0);
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        $result = FTC_Core::get_instance()->merge->approve($request_id, $message);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success();
    }
    
    /**
     * Reject merge
     */
    public function reject_merge() {
        $this->verify_nonce();
        
        $request_id = absint($_POST['request_id'] ?? 0);
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        $result = FTC_Core::get_instance()->merge->reject($request_id, $message);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success();
    }
    
    /**
     * Find potential matches
     */
    public function find_matches() {
        $this->verify_nonce();
        
        $person_id = absint($_POST['person_id'] ?? 0);
        $matches = FTC_Core::get_instance()->merge->find_potential_matches($person_id);
        
        wp_send_json_success(array('matches' => $matches));
    }
    
    /**
     * Search
     */
    public function search() {
        $query = sanitize_text_field($_GET['q'] ?? '');
        $type = sanitize_key($_GET['type'] ?? 'person');
        $tree_id = absint($_GET['tree_id'] ?? 0);
        
        $results = array();
        
        switch ($type) {
            case 'person':
                $results = FTC_Core::get_instance()->search->search_persons($query, array(
                    'tree_id' => $tree_id,
                    'limit' => 20,
                ));
                break;
            case 'place':
                $results = FTC_Core::get_instance()->search->search_places($query);
                break;
        }
        
        wp_send_json_success(array('results' => $results));
    }
    
    /**
     * Get suggestions
     */
    public function suggestions() {
        $query = sanitize_text_field($_GET['q'] ?? '');
        $type = sanitize_key($_GET['type'] ?? 'person');
        
        $suggestions = FTC_Core::get_instance()->search->get_suggestions($query, $type);
        
        wp_send_json_success(array('suggestions' => $suggestions));
    }
    
    /**
     * Get chart data
     */
    public function get_chart() {
        $person_id = absint($_GET['person_id'] ?? 0);
        $type = sanitize_key($_GET['type'] ?? 'ancestor');
        $direction = sanitize_key($_GET['direction'] ?? 'TB');
        $generations = absint($_GET['generations'] ?? 4);
        
        $chart_data = FTC_Core::get_instance()->chart->generate($person_id, array(
            'type' => $type,
            'direction' => $direction,
            'generations' => $generations,
        ));
        
        if (!$chart_data) {
            wp_send_json_error(array('message' => __('Could not generate chart.', 'family-tree-connect')));
        }
        
        wp_send_json_success(array('chart' => $chart_data));
    }
    
    /**
     * Mark notification read
     */
    public function mark_notification_read() {
        $this->verify_nonce();
        
        $notification_id = absint($_POST['notification_id'] ?? 0);
        FTC_Core::get_instance()->notification->mark_read($notification_id);
        
        wp_send_json_success();
    }
    
    /**
     * Get notifications
     */
    public function get_notifications() {
        $this->verify_nonce();
        
        $user_id = get_current_user_id();
        $notifications = FTC_Core::get_instance()->notification->get_for_user($user_id, array(
            'limit' => 20,
        ));
        
        $unread_count = FTC_Core::get_instance()->notification->get_unread_count($user_id);
        
        wp_send_json_success(array(
            'notifications' => $notifications,
            'unread_count' => $unread_count,
        ));
    }
    
    /**
     * Store face detection result
     */
    public function store_face() {
        $this->verify_nonce();
        
        $data = array(
            'media_id' => absint($_POST['media_id'] ?? 0),
            'face_x' => intval($_POST['face_x'] ?? 0),
            'face_y' => intval($_POST['face_y'] ?? 0),
            'face_width' => absint($_POST['face_width'] ?? 0),
            'face_height' => absint($_POST['face_height'] ?? 0),
            'face_encoding' => $_POST['face_encoding'] ?? null,
        );
        
        $result = FTC_Core::get_instance()->facial_recognition->store_face($data);
        
        wp_send_json_success(array('face_id' => $result));
    }
    
    /**
     * Identify a face
     */
    public function identify_face() {
        $this->verify_nonce();
        
        $face_id = absint($_POST['face_id'] ?? 0);
        $person_id = absint($_POST['person_id'] ?? 0);
        
        $result = FTC_Core::get_instance()->facial_recognition->identify_face($face_id, $person_id);
        
        wp_send_json_success();
    }
    
    /**
     * Confirm face suggestion
     */
    public function confirm_face() {
        $this->verify_nonce();
        
        $face_id = absint($_POST['face_id'] ?? 0);
        $confirm = (bool) ($_POST['confirm'] ?? true);
        
        if ($confirm) {
            FTC_Core::get_instance()->facial_recognition->confirm_suggestion($face_id);
        } else {
            FTC_Core::get_instance()->facial_recognition->reject_suggestion($face_id);
        }
        
        wp_send_json_success();
    }
    
    /**
     * Create custom field
     */
    public function create_custom_field() {
        $this->verify_nonce();
        
        $data = array(
            'field_label' => sanitize_text_field($_POST['field_label'] ?? ''),
            'field_type' => sanitize_key($_POST['field_type'] ?? 'text'),
            'applies_to' => sanitize_key($_POST['applies_to'] ?? 'person'),
            'field_options' => $_POST['field_options'] ?? '',
        );
        
        $result = FTC_Core::get_instance()->custom_fields->create($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('field_id' => $result));
    }
    
    /**
     * Save custom field values
     */
    public function save_custom_values() {
        $this->verify_nonce();
        
        $entity_type = sanitize_key($_POST['entity_type'] ?? 'person');
        $entity_id = absint($_POST['entity_id'] ?? 0);
        $values = $_POST['values'] ?? array();
        
        foreach ($values as $field_id => $value) {
            FTC_Core::get_instance()->custom_fields->set_value(absint($field_id), $entity_type, $entity_id, $value);
        }
        
        wp_send_json_success();
    }
}
