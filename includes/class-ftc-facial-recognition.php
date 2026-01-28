<?php
/**
 * Facial Recognition class
 * Uses face-api.js for browser-based face detection
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Facial_Recognition {
    
    private $faces_table;
    
    public function __construct() {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        $this->faces_table = $tables['faces'];
    }
    
    /**
     * Store detected face from client-side processing
     */
    public function store_face($data) {
        global $wpdb;
        
        $insert_data = array(
            'media_id' => absint($data['media_id']),
            'person_id' => !empty($data['person_id']) ? absint($data['person_id']) : null,
            'suggested_person_id' => !empty($data['suggested_person_id']) ? absint($data['suggested_person_id']) : null,
            'face_x' => intval($data['face_x']),
            'face_y' => intval($data['face_y']),
            'face_width' => absint($data['face_width']),
            'face_height' => absint($data['face_height']),
            'face_encoding' => !empty($data['face_encoding']) ? $data['face_encoding'] : null,
            'confidence' => !empty($data['confidence']) ? floatval($data['confidence']) : null,
            'status' => 'unidentified',
        );
        
        $wpdb->insert($this->faces_table, $insert_data);
        return $wpdb->insert_id;
    }
    
    /**
     * Get faces for a media item
     */
    public function get_faces($media_id) {
        global $wpdb;
        
        $faces = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->faces_table} WHERE media_id = %d",
            $media_id
        ));
        
        foreach ($faces as &$face) {
            if ($face->person_id) {
                $face->person = FTC_Core::get_instance()->person->get($face->person_id);
            }
            if ($face->suggested_person_id) {
                $face->suggested_person = FTC_Core::get_instance()->person->get($face->suggested_person_id);
            }
        }
        
        return $faces;
    }
    
    /**
     * Identify a face (assign to person)
     */
    public function identify_face($face_id, $person_id) {
        global $wpdb;
        
        return $wpdb->update(
            $this->faces_table,
            array(
                'person_id' => $person_id,
                'status' => 'confirmed',
            ),
            array('id' => $face_id)
        );
    }
    
    /**
     * Confirm a suggested identification
     */
    public function confirm_suggestion($face_id) {
        global $wpdb;
        
        $face = $this->get_face($face_id);
        if (!$face || !$face->suggested_person_id) {
            return false;
        }
        
        return $wpdb->update(
            $this->faces_table,
            array(
                'person_id' => $face->suggested_person_id,
                'status' => 'confirmed',
            ),
            array('id' => $face_id)
        );
    }
    
    /**
     * Reject a suggested identification
     */
    public function reject_suggestion($face_id) {
        global $wpdb;
        
        return $wpdb->update(
            $this->faces_table,
            array(
                'suggested_person_id' => null,
                'status' => 'rejected',
            ),
            array('id' => $face_id)
        );
    }
    
    /**
     * Get a single face
     */
    public function get_face($face_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->faces_table} WHERE id = %d",
            $face_id
        ));
    }
    
    /**
     * Delete a face
     */
    public function delete_face($face_id) {
        global $wpdb;
        return $wpdb->delete($this->faces_table, array('id' => $face_id));
    }
    
    /**
     * Get all face encodings for a person (for matching)
     */
    public function get_person_encodings($person_id) {
        global $wpdb;
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT face_encoding FROM {$this->faces_table} 
             WHERE person_id = %d AND face_encoding IS NOT NULL AND status = 'confirmed'",
            $person_id
        ));
    }
    
    /**
     * Find potential matches for a face encoding
     */
    public function find_matches($face_encoding, $tree_id = null, $threshold = 0.6) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Get all confirmed face encodings
        $sql = "SELECT DISTINCT f.person_id, f.face_encoding, p.first_name, p.surname
                FROM {$this->faces_table} f
                INNER JOIN {$tables['persons']} p ON f.person_id = p.id
                WHERE f.status = 'confirmed' AND f.face_encoding IS NOT NULL";
        
        if ($tree_id) {
            $sql .= $wpdb->prepare(
                " AND p.id IN (SELECT person_id FROM {$tables['tree_persons']} WHERE tree_id = %d)",
                $tree_id
            );
        }
        
        $known_faces = $wpdb->get_results($sql);
        
        // The actual face comparison would be done client-side with face-api.js
        // This method returns potential candidates for the client to compare
        return $known_faces;
    }
    
    /**
     * Store a match suggestion
     */
    public function store_suggestion($face_id, $suggested_person_id, $confidence) {
        global $wpdb;
        
        return $wpdb->update(
            $this->faces_table,
            array(
                'suggested_person_id' => $suggested_person_id,
                'confidence' => $confidence,
                'status' => 'suggested',
            ),
            array('id' => $face_id)
        );
    }
    
    /**
     * Get unprocessed media
     */
    public function get_unprocessed_media($limit = 10) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['media']} 
             WHERE faces_processed = 0 AND media_category = 'photo'
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Mark media as processed
     */
    public function mark_processed($media_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        return $wpdb->update(
            $tables['media'],
            array('faces_processed' => 1),
            array('id' => $media_id)
        );
    }
    
    /**
     * Get faces by person
     */
    public function get_faces_by_person($person_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->faces_table} WHERE person_id = %d AND status = 'confirmed'",
            $person_id
        ));
    }
    
    /**
     * Get pending suggestions for user
     */
    public function get_pending_suggestions($user_id, $limit = 20) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT f.*, m.file_path, m.title as media_title
             FROM {$this->faces_table} f
             INNER JOIN {$tables['media']} m ON f.media_id = m.id
             WHERE f.status = 'suggested' AND m.uploaded_by = %d
             ORDER BY f.confidence DESC
             LIMIT %d",
            $user_id, $limit
        ));
    }
}
