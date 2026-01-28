<?php
/**
 * Merge management class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Merge {
    
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
        $this->table = $tables['merge_requests'];
    }
    
    /**
     * Create a merge request
     */
    public function create_request($data) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', __('You must be logged in to create a merge request.', 'family-tree-connect'));
        }
        
        $required = array('target_user_id', 'source_person_id', 'target_person_id');
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Missing required field: %s', 'family-tree-connect'), $field));
            }
        }
        
        // Verify the requesting user can edit the source person
        if (!FTC_Core::can_edit_person($data['source_person_id'])) {
            return new WP_Error('permission_denied', __('You do not have permission to merge this person.', 'family-tree-connect'));
        }
        
        // Check if a pending request already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} 
             WHERE source_person_id = %d AND target_person_id = %d AND status = 'pending'",
            $data['source_person_id'], $data['target_person_id']
        ));
        
        if ($existing) {
            return new WP_Error('duplicate', __('A merge request already exists for these profiles.', 'family-tree-connect'));
        }
        
        $insert_data = array(
            'uuid' => FTC_Core::generate_uuid(),
            'requesting_user_id' => $user_id,
            'target_user_id' => absint($data['target_user_id']),
            'source_person_id' => absint($data['source_person_id']),
            'target_person_id' => absint($data['target_person_id']),
            'status' => 'pending',
            'message' => isset($data['message']) ? sanitize_textarea_field($data['message']) : '',
        );
        
        $result = $wpdb->insert($this->table, $insert_data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create merge request.', 'family-tree-connect'));
        }
        
        $request_id = $wpdb->insert_id;
        
        // Send notification to target user
        $this->send_merge_notification($request_id);
        
        do_action('ftc_merge_request_created', $request_id);
        
        return $request_id;
    }
    
    /**
     * Get a merge request
     */
    public function get($request_id) {
        global $wpdb;
        
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $request_id
        ));
        
        if ($request) {
            $request = $this->enrich_request($request);
        }
        
        return $request;
    }
    
    /**
     * Get merge request by UUID
     */
    public function get_by_uuid($uuid) {
        global $wpdb;
        
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE uuid = %s",
            $uuid
        ));
        
        if ($request) {
            $request = $this->enrich_request($request);
        }
        
        return $request;
    }
    
    /**
     * Approve a merge request
     */
    public function approve($request_id, $response_message = '') {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $request = $this->get($request_id);
        if (!$request) {
            return new WP_Error('not_found', __('Merge request not found.', 'family-tree-connect'));
        }
        
        // Verify current user is the target
        if ($request->target_user_id != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('permission_denied', __('You do not have permission to approve this request.', 'family-tree-connect'));
        }
        
        if ($request->status !== 'pending') {
            return new WP_Error('invalid_status', __('This request has already been processed.', 'family-tree-connect'));
        }
        
        // Perform the merge
        $merge_result = $this->perform_merge($request->source_person_id, $request->target_person_id);
        
        if (is_wp_error($merge_result)) {
            return $merge_result;
        }
        
        // Update request status
        $wpdb->update(
            $this->table,
            array(
                'status' => 'approved',
                'response_message' => sanitize_textarea_field($response_message),
            ),
            array('id' => $request_id)
        );
        
        // Add requesting user as manager of the merged profile
        $wpdb->insert($tables['person_managers'], array(
            'person_id' => $request->target_person_id,
            'user_id' => $request->requesting_user_id,
            'role' => 'manager',
        ));
        
        // Send approval notification
        FTC_Core::get_instance()->notification->create(array(
            'user_id' => $request->requesting_user_id,
            'type' => 'merge_approved',
            'title' => __('Merge Request Approved', 'family-tree-connect'),
            'message' => sprintf(
                __('Your merge request for %s has been approved.', 'family-tree-connect'),
                $request->target_person->display_name
            ),
            'related_id' => $request_id,
            'related_type' => 'merge_request',
        ));
        
        do_action('ftc_merge_approved', $request_id, $request->target_person_id);
        
        return true;
    }
    
    /**
     * Reject a merge request
     */
    public function reject($request_id, $response_message = '') {
        global $wpdb;
        
        $request = $this->get($request_id);
        if (!$request) {
            return new WP_Error('not_found', __('Merge request not found.', 'family-tree-connect'));
        }
        
        // Verify current user is the target
        if ($request->target_user_id != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('permission_denied', __('You do not have permission to reject this request.', 'family-tree-connect'));
        }
        
        if ($request->status !== 'pending') {
            return new WP_Error('invalid_status', __('This request has already been processed.', 'family-tree-connect'));
        }
        
        // Update status
        $wpdb->update(
            $this->table,
            array(
                'status' => 'rejected',
                'response_message' => sanitize_textarea_field($response_message),
            ),
            array('id' => $request_id)
        );
        
        // Send rejection notification
        FTC_Core::get_instance()->notification->create(array(
            'user_id' => $request->requesting_user_id,
            'type' => 'merge_rejected',
            'title' => __('Merge Request Rejected', 'family-tree-connect'),
            'message' => sprintf(
                __('Your merge request for %s has been rejected.', 'family-tree-connect'),
                $request->target_person->display_name
            ),
            'related_id' => $request_id,
            'related_type' => 'merge_request',
        ));
        
        do_action('ftc_merge_rejected', $request_id);
        
        return true;
    }
    
    /**
     * Cancel a merge request
     */
    public function cancel($request_id) {
        global $wpdb;
        
        $request = $this->get($request_id);
        if (!$request) {
            return new WP_Error('not_found', __('Merge request not found.', 'family-tree-connect'));
        }
        
        // Verify current user is the requester
        if ($request->requesting_user_id != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('permission_denied', __('You do not have permission to cancel this request.', 'family-tree-connect'));
        }
        
        if ($request->status !== 'pending') {
            return new WP_Error('invalid_status', __('This request has already been processed.', 'family-tree-connect'));
        }
        
        $wpdb->update(
            $this->table,
            array('status' => 'cancelled'),
            array('id' => $request_id)
        );
        
        do_action('ftc_merge_cancelled', $request_id);
        
        return true;
    }
    
    /**
     * Perform the actual merge
     */
    private function perform_merge($source_person_id, $target_person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $source = FTC_Core::get_instance()->person->get($source_person_id);
        $target = FTC_Core::get_instance()->person->get($target_person_id);
        
        if (!$source || !$target) {
            return new WP_Error('not_found', __('One or both persons not found.', 'family-tree-connect'));
        }
        
        // Merge data - target takes priority, but fill in blanks from source
        $merge_fields = array(
            'first_name', 'middle_name', 'surname', 'maiden_name', 'nickname',
            'birth_date', 'birth_location', 'death_date', 'death_location',
            'occupation', 'biography', 'notes'
        );
        
        $update_data = array();
        foreach ($merge_fields as $field) {
            if (empty($target->$field) && !empty($source->$field)) {
                $update_data[$field] = $source->$field;
            }
        }
        
        if (!empty($update_data)) {
            $wpdb->update($tables['persons'], $update_data, array('id' => $target_person_id));
        }
        
        // Merge events - move source events to target
        $wpdb->update(
            $tables['events'],
            array('person_id' => $target_person_id),
            array('person_id' => $source_person_id)
        );
        
        // Merge media - add source media links to target
        $source_media = $wpdb->get_col($wpdb->prepare(
            "SELECT media_id FROM {$tables['media_persons']} WHERE person_id = %d",
            $source_person_id
        ));
        
        foreach ($source_media as $media_id) {
            // Check if not already linked
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tables['media_persons']} WHERE media_id = %d AND person_id = %d",
                $media_id, $target_person_id
            ));
            
            if (!$exists) {
                $wpdb->insert($tables['media_persons'], array(
                    'media_id' => $media_id,
                    'person_id' => $target_person_id,
                ));
            }
        }
        
        // Merge crops
        $wpdb->update(
            $tables['media_crops'],
            array('person_id' => $target_person_id),
            array('person_id' => $source_person_id)
        );
        
        // Merge faces
        $wpdb->update(
            $tables['faces'],
            array('person_id' => $target_person_id),
            array('person_id' => $source_person_id)
        );
        
        // Update relationships - point to target instead of source
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tables['relationships']} SET person1_id = %d WHERE person1_id = %d",
            $target_person_id, $source_person_id
        ));
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tables['relationships']} SET person2_id = %d WHERE person2_id = %d",
            $target_person_id, $source_person_id
        ));
        
        // Update families
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tables['families']} SET spouse1_id = %d WHERE spouse1_id = %d",
            $target_person_id, $source_person_id
        ));
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tables['families']} SET spouse2_id = %d WHERE spouse2_id = %d",
            $target_person_id, $source_person_id
        ));
        
        // Update family children
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tables['family_children']} SET person_id = %d WHERE person_id = %d",
            $target_person_id, $source_person_id
        ));
        
        // Merge custom field values
        $source_fields = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['custom_field_values']} 
             WHERE entity_type = 'person' AND entity_id = %d",
            $source_person_id
        ));
        
        foreach ($source_fields as $field) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tables['custom_field_values']} 
                 WHERE custom_field_id = %d AND entity_type = 'person' AND entity_id = %d",
                $field->custom_field_id, $target_person_id
            ));
            
            if (!$exists) {
                $wpdb->insert($tables['custom_field_values'], array(
                    'custom_field_id' => $field->custom_field_id,
                    'entity_type' => 'person',
                    'entity_id' => $target_person_id,
                    'field_value' => $field->field_value,
                ));
            }
        }
        
        // Merge tree links
        $source_trees = $wpdb->get_col($wpdb->prepare(
            "SELECT tree_id FROM {$tables['tree_persons']} WHERE person_id = %d",
            $source_person_id
        ));
        
        foreach ($source_trees as $tree_id) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tables['tree_persons']} WHERE tree_id = %d AND person_id = %d",
                $tree_id, $target_person_id
            ));
            
            if (!$exists) {
                $wpdb->insert($tables['tree_persons'], array(
                    'tree_id' => $tree_id,
                    'person_id' => $target_person_id,
                ));
            }
        }
        
        // Update source tree references
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tables['trees']} SET source_person_id = %d WHERE source_person_id = %d",
            $target_person_id, $source_person_id
        ));
        
        // Merge managers
        $source_managers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['person_managers']} WHERE person_id = %d",
            $source_person_id
        ));
        
        foreach ($source_managers as $manager) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tables['person_managers']} WHERE person_id = %d AND user_id = %d",
                $target_person_id, $manager->user_id
            ));
            
            if (!$exists) {
                $wpdb->insert($tables['person_managers'], array(
                    'person_id' => $target_person_id,
                    'user_id' => $manager->user_id,
                    'role' => $manager->role,
                ));
            }
        }
        
        // Delete source person and related records
        $wpdb->delete($tables['person_managers'], array('person_id' => $source_person_id));
        $wpdb->delete($tables['tree_persons'], array('person_id' => $source_person_id));
        $wpdb->delete($tables['media_persons'], array('person_id' => $source_person_id));
        $wpdb->delete($tables['person_places'], array('person_id' => $source_person_id));
        $wpdb->delete($tables['custom_field_values'], array('entity_type' => 'person', 'entity_id' => $source_person_id));
        $wpdb->delete($tables['persons'], array('id' => $source_person_id));
        
        return $target_person_id;
    }
    
    /**
     * Send merge notification
     */
    private function send_merge_notification($request_id) {
        $request = $this->get($request_id);
        if (!$request) {
            return;
        }
        
        // Create in-app notification
        FTC_Core::get_instance()->notification->create(array(
            'user_id' => $request->target_user_id,
            'type' => 'merge_request',
            'title' => __('New Merge Request', 'family-tree-connect'),
            'message' => sprintf(
                __('%s wants to merge a profile with %s. Review and respond to this request.', 'family-tree-connect'),
                $request->requesting_user->display_name,
                $request->target_person->display_name
            ),
            'link' => add_query_arg(array(
                'page' => 'ftc-merge-requests',
                'request' => $request_id,
            ), admin_url('admin.php')),
            'related_id' => $request_id,
            'related_type' => 'merge_request',
            'send_email' => true,
        ));
    }
    
    /**
     * Get pending requests for a user
     */
    public function get_pending_for_user($user_id) {
        global $wpdb;
        
        $requests = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE target_user_id = %d AND status = 'pending' ORDER BY created_at DESC",
            $user_id
        ));
        
        foreach ($requests as &$request) {
            $request = $this->enrich_request($request);
        }
        
        return $requests;
    }
    
    /**
     * Get requests created by a user
     */
    public function get_by_requester($user_id) {
        global $wpdb;
        
        $requests = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE requesting_user_id = %d ORDER BY created_at DESC",
            $user_id
        ));
        
        foreach ($requests as &$request) {
            $request = $this->enrich_request($request);
        }
        
        return $requests;
    }
    
    /**
     * Find potential matches for a person
     */
    public function find_potential_matches($person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $person = FTC_Core::get_instance()->person->get($person_id);
        if (!$person) {
            return array();
        }
        
        // Search for similar people not created by the same user
        $conditions = array();
        $params = array();
        
        if ($person->first_name) {
            $conditions[] = "first_name = %s";
            $params[] = $person->first_name;
        }
        
        if ($person->surname) {
            $conditions[] = "(surname = %s OR maiden_name = %s)";
            $params[] = $person->surname;
            $params[] = $person->surname;
        }
        
        if ($person->birth_date) {
            $conditions[] = "birth_date = %s";
            $params[] = $person->birth_date;
        }
        
        if (empty($conditions)) {
            return array();
        }
        
        $where = implode(' AND ', $conditions);
        $params[] = $person->id;
        $params[] = $person->created_by;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$tables['persons']} 
             WHERE ($where) AND id != %d AND created_by != %d
             LIMIT 20",
            ...$params
        );
        
        $matches = $wpdb->get_results($sql);
        
        foreach ($matches as &$match) {
            $match->display_name = FTC_Core::get_instance()->person->get_display_name($match);
            $match->match_score = $this->calculate_match_score($person, $match);
        }
        
        // Sort by match score
        usort($matches, function($a, $b) {
            return $b->match_score - $a->match_score;
        });
        
        return $matches;
    }
    
    /**
     * Calculate match score between two people
     */
    private function calculate_match_score($person1, $person2) {
        $score = 0;
        
        // Name matching
        if ($person1->first_name && $person1->first_name === $person2->first_name) {
            $score += 25;
        }
        
        if ($person1->surname && $person1->surname === $person2->surname) {
            $score += 25;
        } elseif ($person1->surname && $person1->surname === $person2->maiden_name) {
            $score += 20;
        }
        
        if ($person1->middle_name && $person1->middle_name === $person2->middle_name) {
            $score += 10;
        }
        
        // Birth matching
        if ($person1->birth_date && $person1->birth_date === $person2->birth_date) {
            $score += 30;
        }
        
        if ($person1->birth_location && $person1->birth_location === $person2->birth_location) {
            $score += 10;
        }
        
        return $score;
    }
    
    /**
     * Enrich request object
     */
    private function enrich_request($request) {
        $request->requesting_user = get_userdata($request->requesting_user_id);
        $request->target_user = get_userdata($request->target_user_id);
        $request->source_person = FTC_Core::get_instance()->person->get($request->source_person_id);
        $request->target_person = FTC_Core::get_instance()->person->get($request->target_person_id);
        
        return $request;
    }
}
