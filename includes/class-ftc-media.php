<?php
/**
 * Media management class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Media {
    
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
        $this->table = $tables['media'];
    }
    
    /**
     * Upload media
     */
    public function upload($file, $data = array()) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', __('You must be logged in to upload media.', 'family-tree-connect'));
        }
        
        // Use WordPress media handling
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $upload = wp_handle_upload($file, array('test_form' => false));
        
        if (isset($upload['error'])) {
            return new WP_Error('upload_error', $upload['error']);
        }
        
        // Create attachment
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        );
        
        $attachment_id = wp_insert_attachment($attachment, $upload['file']);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // Generate metadata
        $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attach_data);
        
        // Determine media category
        $category = 'other';
        if (strpos($upload['type'], 'image/') === 0) {
            $category = 'photo';
        } elseif (in_array($upload['type'], array('application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))) {
            $category = 'document';
        } elseif (strpos($upload['type'], 'audio/') === 0) {
            $category = 'audio';
        } elseif (strpos($upload['type'], 'video/') === 0) {
            $category = 'video';
        }
        
        $defaults = array(
            'uuid' => FTC_Core::generate_uuid(),
            'attachment_id' => $attachment_id,
            'original_filename' => $file['name'],
            'file_path' => $upload['file'],
            'file_type' => pathinfo($file['name'], PATHINFO_EXTENSION),
            'mime_type' => $upload['type'],
            'title' => isset($data['title']) ? $data['title'] : pathinfo($file['name'], PATHINFO_FILENAME),
            'description' => isset($data['description']) ? $data['description'] : '',
            'media_date' => isset($data['media_date']) ? $data['media_date'] : null,
            'media_date_calendar' => isset($data['media_date_calendar']) ? $data['media_date_calendar'] : 'gregorian',
            'location' => isset($data['location']) ? $data['location'] : '',
            'place_id' => isset($data['place_id']) ? $data['place_id'] : null,
            'event_id' => isset($data['event_id']) ? $data['event_id'] : null,
            'media_category' => $category,
            'faces_processed' => 0,
            'uploaded_by' => $user_id,
        );
        
        $insert_data = $this->sanitize_media_data($defaults);
        
        $result = $wpdb->insert($this->table, $insert_data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to save media record.', 'family-tree-connect'));
        }
        
        $media_id = $wpdb->insert_id;
        
        // Link to persons if provided
        if (!empty($data['person_ids'])) {
            foreach ((array) $data['person_ids'] as $person_id) {
                $this->link_to_person($media_id, $person_id);
            }
        }
        
        do_action('ftc_media_uploaded', $media_id, $attachment_id);
        
        return $media_id;
    }
    
    /**
     * Get media by ID
     */
    public function get($media_id) {
        global $wpdb;
        
        $media = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $media_id
        ));
        
        if ($media) {
            $media = $this->enrich_media($media);
        }
        
        return $media;
    }
    
    /**
     * Get media by UUID
     */
    public function get_by_uuid($uuid) {
        global $wpdb;
        
        $media = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE uuid = %s",
            $uuid
        ));
        
        if ($media) {
            $media = $this->enrich_media($media);
        }
        
        return $media;
    }
    
    /**
     * Update media
     */
    public function update($media_id, $data) {
        global $wpdb;
        
        $media = $this->get($media_id);
        if (!$media) {
            return new WP_Error('not_found', __('Media not found.', 'family-tree-connect'));
        }
        
        // Check permissions
        if ($media->uploaded_by != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('permission_denied', __('You do not have permission to edit this media.', 'family-tree-connect'));
        }
        
        unset($data['id'], $data['uuid'], $data['attachment_id'], $data['file_path'], $data['uploaded_by'], $data['created_at']);
        $data = $this->sanitize_media_data($data);
        
        $result = $wpdb->update($this->table, $data, array('id' => $media_id));
        
        do_action('ftc_media_updated', $media_id, $data);
        
        return $result !== false;
    }
    
    /**
     * Delete media
     */
    public function delete($media_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $media = $this->get($media_id);
        if (!$media) {
            return new WP_Error('not_found', __('Media not found.', 'family-tree-connect'));
        }
        
        // Check permissions
        if ($media->uploaded_by != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('permission_denied', __('You do not have permission to delete this media.', 'family-tree-connect'));
        }
        
        do_action('ftc_before_media_delete', $media_id);
        
        // Delete WordPress attachment
        if ($media->attachment_id) {
            wp_delete_attachment($media->attachment_id, true);
        }
        
        // Delete related records
        $wpdb->delete($tables['media_persons'], array('media_id' => $media_id));
        $wpdb->delete($tables['media_crops'], array('media_id' => $media_id));
        $wpdb->delete($tables['faces'], array('media_id' => $media_id));
        
        // Update persons with this as default photo
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tables['persons']} SET default_photo_id = NULL WHERE default_photo_id = %d",
            $media_id
        ));
        
        // Update families with this as default photo
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tables['families']} SET default_photo_id = NULL WHERE default_photo_id = %d",
            $media_id
        ));
        
        $result = $wpdb->delete($this->table, array('id' => $media_id));
        
        do_action('ftc_media_deleted', $media_id);
        
        return $result !== false;
    }
    
    /**
     * Link media to a person
     */
    public function link_to_person($media_id, $person_id, $is_default = false) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Check if already linked
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tables['media_persons']} WHERE media_id = %d AND person_id = %d",
            $media_id, $person_id
        ));
        
        if ($exists) {
            return $exists;
        }
        
        $wpdb->insert($tables['media_persons'], array(
            'media_id' => $media_id,
            'person_id' => $person_id,
            'is_default' => $is_default ? 1 : 0,
        ));
        
        // Set as default photo if requested
        if ($is_default) {
            $this->set_as_default_photo($media_id, $person_id);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Unlink media from a person
     */
    public function unlink_from_person($media_id, $person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Remove default photo if this was it
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tables['persons']} SET default_photo_id = NULL 
             WHERE id = %d AND default_photo_id = %d",
            $person_id, $media_id
        ));
        
        // Delete crops for this person
        $wpdb->delete($tables['media_crops'], array('media_id' => $media_id, 'person_id' => $person_id));
        
        return $wpdb->delete($tables['media_persons'], array('media_id' => $media_id, 'person_id' => $person_id));
    }
    
    /**
     * Set media as default photo for a person
     */
    public function set_as_default_photo($media_id, $person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Make sure they're linked
        $this->link_to_person($media_id, $person_id, true);
        
        // Update person's default photo
        $wpdb->update(
            $tables['persons'],
            array('default_photo_id' => $media_id),
            array('id' => $person_id)
        );
        
        return true;
    }
    
    /**
     * Create a non-destructive crop
     */
    public function create_crop($media_id, $crop_data) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', __('You must be logged in.', 'family-tree-connect'));
        }
        
        $defaults = array(
            'media_id' => $media_id,
            'person_id' => null,
            'crop_x' => 0,
            'crop_y' => 0,
            'crop_width' => 100,
            'crop_height' => 100,
            'rotation' => 0,
            'is_primary' => 0,
            'created_by' => $user_id,
        );
        
        $data = wp_parse_args($crop_data, $defaults);
        
        // Sanitize
        $data['media_id'] = absint($data['media_id']);
        $data['person_id'] = $data['person_id'] ? absint($data['person_id']) : null;
        $data['crop_x'] = intval($data['crop_x']);
        $data['crop_y'] = intval($data['crop_y']);
        $data['crop_width'] = absint($data['crop_width']);
        $data['crop_height'] = absint($data['crop_height']);
        $data['rotation'] = floatval($data['rotation']);
        $data['is_primary'] = (int) (bool) $data['is_primary'];
        $data['created_by'] = absint($data['created_by']);
        
        // If this is primary, remove primary from others for this person
        if ($data['is_primary'] && $data['person_id']) {
            $wpdb->update(
                $tables['media_crops'],
                array('is_primary' => 0),
                array('person_id' => $data['person_id'])
            );
        }
        
        $result = $wpdb->insert($tables['media_crops'], $data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to save crop.', 'family-tree-connect'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update a crop
     */
    public function update_crop($crop_id, $data) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $update_data = array();
        
        if (isset($data['crop_x'])) {
            $update_data['crop_x'] = intval($data['crop_x']);
        }
        if (isset($data['crop_y'])) {
            $update_data['crop_y'] = intval($data['crop_y']);
        }
        if (isset($data['crop_width'])) {
            $update_data['crop_width'] = absint($data['crop_width']);
        }
        if (isset($data['crop_height'])) {
            $update_data['crop_height'] = absint($data['crop_height']);
        }
        if (isset($data['rotation'])) {
            $update_data['rotation'] = floatval($data['rotation']);
        }
        if (isset($data['is_primary'])) {
            $update_data['is_primary'] = (int) (bool) $data['is_primary'];
        }
        
        return $wpdb->update($tables['media_crops'], $update_data, array('id' => $crop_id));
    }
    
    /**
     * Delete a crop
     */
    public function delete_crop($crop_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        return $wpdb->delete($tables['media_crops'], array('id' => $crop_id));
    }
    
    /**
     * Get crops for media
     */
    public function get_crops($media_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['media_crops']} WHERE media_id = %d ORDER BY is_primary DESC",
            $media_id
        ));
    }
    
    /**
     * Get primary crop for a person's photo
     */
    public function get_primary_crop($media_id, $person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['media_crops']} 
             WHERE media_id = %d AND person_id = %d AND is_primary = 1",
            $media_id, $person_id
        ));
    }
    
    /**
     * Get cropped image URL
     */
    public function get_cropped_url($media_id, $crop_id = null) {
        $media = $this->get($media_id);
        if (!$media) {
            return '';
        }
        
        // If no specific crop, return original
        if (!$crop_id) {
            return $media->url;
        }
        
        // Return URL with crop parameters (server-side crop generation)
        return add_query_arg(array(
            'ftc_media' => $media_id,
            'crop' => $crop_id,
        ), home_url());
    }
    
    /**
     * Get media for a person
     */
    public function get_by_person($person_id, $category = null) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $sql = $wpdb->prepare(
            "SELECT m.* FROM {$this->table} m
             INNER JOIN {$tables['media_persons']} mp ON m.id = mp.media_id
             WHERE mp.person_id = %d",
            $person_id
        );
        
        if ($category) {
            $sql .= $wpdb->prepare(" AND m.media_category = %s", $category);
        }
        
        $sql .= " ORDER BY m.media_date ASC, m.created_at ASC";
        
        $media = $wpdb->get_results($sql);
        
        foreach ($media as &$item) {
            $item = $this->enrich_media($item);
        }
        
        return $media;
    }
    
    /**
     * Get media for an event
     */
    public function get_by_event($event_id) {
        global $wpdb;
        
        $media = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE event_id = %d ORDER BY media_date ASC, created_at ASC",
            $event_id
        ));
        
        foreach ($media as &$item) {
            $item = $this->enrich_media($item);
        }
        
        return $media;
    }
    
    /**
     * Get media page for a person (organized by events)
     */
    public function get_media_page($person_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $result = array(
            'events' => array(),
            'timeline' => array(),
        );
        
        // Get all media for person
        $all_media = $this->get_by_person($person_id);
        
        // Get person's events
        $events = FTC_Core::get_instance()->event->get_by_person($person_id);
        
        // Organize by events
        foreach ($events as $event) {
            $event_media = array_filter($all_media, function($m) use ($event) {
                return $m->event_id == $event->id;
            });
            
            if (!empty($event_media)) {
                $result['events'][$event->id] = array(
                    'event' => $event,
                    'media' => array_values($event_media),
                );
            }
        }
        
        // Get media not linked to events (timeline)
        $result['timeline'] = array_filter($all_media, function($m) {
            return empty($m->event_id);
        });
        $result['timeline'] = array_values($result['timeline']);
        
        return $result;
    }
    
    /**
     * Assign existing media to a person
     */
    public function assign_to_person($media_id, $person_id, $crop_data = null) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Link to person
        $this->link_to_person($media_id, $person_id);
        
        // Create crop if provided
        if ($crop_data) {
            $crop_data['media_id'] = $media_id;
            $crop_data['person_id'] = $person_id;
            $this->create_crop($media_id, $crop_data);
        }
        
        return true;
    }
    
    /**
     * Enrich media object
     */
    private function enrich_media($media) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Get URL
        if ($media->attachment_id) {
            $media->url = wp_get_attachment_url($media->attachment_id);
            $media->thumbnail_url = wp_get_attachment_image_url($media->attachment_id, 'thumbnail');
            $media->medium_url = wp_get_attachment_image_url($media->attachment_id, 'medium');
            $media->large_url = wp_get_attachment_image_url($media->attachment_id, 'large');
        } else {
            $media->url = '';
            $media->thumbnail_url = '';
            $media->medium_url = '';
            $media->large_url = '';
        }
        
        // Get linked persons
        $media->persons = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, mp.is_default FROM {$tables['persons']} p
             INNER JOIN {$tables['media_persons']} mp ON p.id = mp.person_id
             WHERE mp.media_id = %d",
            $media->id
        ));
        
        foreach ($media->persons as &$person) {
            $person->display_name = FTC_Core::get_instance()->person->get_display_name($person);
        }
        
        // Get crops
        $media->crops = $this->get_crops($media->id);
        
        // Get faces
        $media->faces = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['faces']} WHERE media_id = %d",
            $media->id
        ));
        
        return $media;
    }
    
    /**
     * Sanitize media data
     */
    private function sanitize_media_data($data) {
        $sanitized = array();
        
        // Text fields
        $text_fields = array('original_filename', 'file_path', 'file_type', 'mime_type', 'title', 'location');
        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        // Textarea
        if (isset($data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($data['description']);
        }
        
        // Integer fields
        $int_fields = array('attachment_id', 'place_id', 'event_id', 'uploaded_by');
        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field] ? absint($data[$field]) : null;
            }
        }
        
        // Date
        if (isset($data['media_date'])) {
            $sanitized['media_date'] = $data['media_date'] ? sanitize_text_field($data['media_date']) : null;
        }
        
        // Calendar
        if (isset($data['media_date_calendar'])) {
            $valid_calendars = array_keys(FTC_Calendar::get_calendar_systems());
            $sanitized['media_date_calendar'] = in_array($data['media_date_calendar'], $valid_calendars) 
                ? $data['media_date_calendar'] : 'gregorian';
        }
        
        // Category
        if (isset($data['media_category'])) {
            $valid_categories = array('photo', 'document', 'audio', 'video', 'other');
            $sanitized['media_category'] = in_array($data['media_category'], $valid_categories) 
                ? $data['media_category'] : 'other';
        }
        
        // Boolean
        if (isset($data['faces_processed'])) {
            $sanitized['faces_processed'] = (int) (bool) $data['faces_processed'];
        }
        
        // UUID
        if (isset($data['uuid'])) {
            $sanitized['uuid'] = sanitize_text_field($data['uuid']);
        }
        
        return $sanitized;
    }
}
