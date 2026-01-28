<?php
/**
 * Custom Fields management class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Custom_Fields {
    
    private $table;
    private $values_table;
    
    public function __construct() {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        $this->table = $tables['custom_fields'];
        $this->values_table = $tables['custom_field_values'];
    }
    
    public function create($data) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', __('You must be logged in.', 'family-tree-connect'));
        }
        
        $insert_data = array(
            'user_id' => $user_id,
            'field_name' => sanitize_key($data['field_name'] ?? $data['field_label'] ?? ''),
            'field_label' => sanitize_text_field($data['field_label'] ?? ''),
            'field_type' => $this->validate_field_type($data['field_type'] ?? 'text'),
            'field_options' => $this->sanitize_options($data['field_options'] ?? ''),
            'applies_to' => in_array($data['applies_to'] ?? '', array('person', 'family', 'event')) ? $data['applies_to'] : 'person',
            'is_global' => (int) (bool) ($data['is_global'] ?? 0),
            'sort_order' => absint($data['sort_order'] ?? 0),
        );
        
        $wpdb->insert($this->table, $insert_data);
        return $wpdb->insert_id;
    }
    
    public function get($field_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $field_id));
    }
    
    public function update($field_id, $data) {
        global $wpdb;
        
        $update = array();
        if (isset($data['field_label'])) $update['field_label'] = sanitize_text_field($data['field_label']);
        if (isset($data['field_type'])) $update['field_type'] = $this->validate_field_type($data['field_type']);
        if (isset($data['field_options'])) $update['field_options'] = $this->sanitize_options($data['field_options']);
        if (isset($data['sort_order'])) $update['sort_order'] = absint($data['sort_order']);
        
        return $wpdb->update($this->table, $update, array('id' => $field_id));
    }
    
    public function delete($field_id) {
        global $wpdb;
        $wpdb->delete($this->values_table, array('custom_field_id' => $field_id));
        return $wpdb->delete($this->table, array('id' => $field_id));
    }
    
    public function get_for_user($user_id, $applies_to = null) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$this->table} WHERE (user_id = %d OR is_global = 1)";
        $params = array($user_id);
        
        if ($applies_to) {
            $sql .= " AND applies_to = %s";
            $params[] = $applies_to;
        }
        
        $sql .= " ORDER BY sort_order ASC";
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }
    
    public function set_value($field_id, $entity_type, $entity_id, $value) {
        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->values_table} WHERE custom_field_id = %d AND entity_type = %s AND entity_id = %d",
            $field_id, $entity_type, $entity_id
        ));
        
        if ($existing) {
            return $wpdb->update($this->values_table, array('field_value' => $value), array('id' => $existing));
        }
        
        return $wpdb->insert($this->values_table, array(
            'custom_field_id' => $field_id,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'field_value' => $value,
        ));
    }
    
    public function get_value($field_id, $entity_type, $entity_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT field_value FROM {$this->values_table} WHERE custom_field_id = %d AND entity_type = %s AND entity_id = %d",
            $field_id, $entity_type, $entity_id
        ));
    }
    
    public function get_values($entity_type, $entity_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT cf.*, cfv.field_value FROM {$this->table} cf
             INNER JOIN {$this->values_table} cfv ON cf.id = cfv.custom_field_id
             WHERE cfv.entity_type = %s AND cfv.entity_id = %d ORDER BY cf.sort_order",
            $entity_type, $entity_id
        ));
        
        $values = array();
        foreach ($results as $row) {
            $values[$row->field_name] = array(
                'field_id' => $row->id,
                'label' => $row->field_label,
                'type' => $row->field_type,
                'value' => $row->field_value,
            );
        }
        return $values;
    }
    
    private function validate_field_type($type) {
        $valid = array('text', 'textarea', 'date', 'number', 'select', 'checkbox', 'url');
        return in_array($type, $valid) ? $type : 'text';
    }
    
    private function sanitize_options($options) {
        if (is_array($options)) {
            return wp_json_encode(array_map('sanitize_text_field', $options));
        }
        return sanitize_textarea_field($options);
    }
}
