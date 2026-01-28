<?php
/**
 * Notification management class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Notification {
    
    private $table;
    
    public function __construct() {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        $this->table = $tables['notifications'];
    }
    
    public function create($data) {
        global $wpdb;
        
        $defaults = array(
            'user_id' => 0,
            'type' => 'general',
            'title' => '',
            'message' => '',
            'link' => '',
            'related_id' => null,
            'related_type' => null,
            'send_email' => true,
        );
        
        $data = wp_parse_args($data, $defaults);
        
        if (!$data['user_id']) {
            return new WP_Error('invalid_user', __('Invalid user ID.', 'family-tree-connect'));
        }
        
        $insert_data = array(
            'user_id' => absint($data['user_id']),
            'type' => sanitize_key($data['type']),
            'title' => sanitize_text_field($data['title']),
            'message' => sanitize_textarea_field($data['message']),
            'link' => esc_url_raw($data['link']),
            'related_id' => $data['related_id'] ? absint($data['related_id']) : null,
            'related_type' => $data['related_type'] ? sanitize_key($data['related_type']) : null,
            'is_read' => 0,
            'email_sent' => 0,
        );
        
        $result = $wpdb->insert($this->table, $insert_data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create notification.', 'family-tree-connect'));
        }
        
        $notification_id = $wpdb->insert_id;
        
        if ($data['send_email']) {
            $this->send_email($notification_id);
        }
        
        return $notification_id;
    }
    
    public function get($notification_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $notification_id
        ));
    }
    
    public function get_for_user($user_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'unread_only' => false,
            'type' => null,
            'limit' => 50,
            'offset' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('user_id = %d');
        $params = array($user_id);
        
        if ($args['unread_only']) {
            $where[] = 'is_read = 0';
        }
        
        if ($args['type']) {
            $where[] = 'type = %s';
            $params[] = $args['type'];
        }
        
        $where_sql = implode(' AND ', $where);
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d",
            ...$params
        ));
    }
    
    public function get_unread_count($user_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
    }
    
    public function mark_read($notification_id) {
        global $wpdb;
        return $wpdb->update($this->table, array('is_read' => 1), array('id' => $notification_id));
    }
    
    public function mark_all_read($user_id) {
        global $wpdb;
        return $wpdb->update($this->table, array('is_read' => 1), array('user_id' => $user_id, 'is_read' => 0));
    }
    
    public function delete($notification_id) {
        global $wpdb;
        return $wpdb->delete($this->table, array('id' => $notification_id));
    }
    
    public function send_email($notification_id) {
        global $wpdb;
        
        $notification = $this->get($notification_id);
        if (!$notification) return false;
        
        $user = get_userdata($notification->user_id);
        if (!$user || !$user->user_email) return false;
        
        $email_prefs = get_user_meta($notification->user_id, 'ftc_email_notifications', true);
        if ($email_prefs === 'disabled') return false;
        
        $subject = sprintf('[%s] %s', get_bloginfo('name'), $notification->title);
        $message = $this->get_email_template($notification);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $sent = wp_mail($user->user_email, $subject, $message, $headers);
        
        if ($sent) {
            $wpdb->update($this->table, array('email_sent' => 1), array('id' => $notification_id));
        }
        
        return $sent;
    }
    
    private function get_email_template($notification) {
        $user = get_userdata($notification->user_id);
        $site_name = get_bloginfo('name');
        
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">';
        $html .= '<div style="max-width:600px;margin:0 auto;padding:20px;">';
        $html .= '<div style="background:#4a90a4;color:white;padding:20px;text-align:center;"><h1>' . esc_html($site_name) . '</h1></div>';
        $html .= '<div style="padding:20px;background:#f9f9f9;">';
        $html .= '<h2>' . esc_html($notification->title) . '</h2>';
        $html .= '<p>' . __('Hello', 'family-tree-connect') . ' ' . esc_html($user->display_name) . ',</p>';
        $html .= '<p>' . esc_html($notification->message) . '</p>';
        if ($notification->link) {
            $html .= '<p><a href="' . esc_url($notification->link) . '" style="display:inline-block;padding:10px 20px;background:#4a90a4;color:white;text-decoration:none;border-radius:4px;">' . __('View Details', 'family-tree-connect') . '</a></p>';
        }
        $html .= '</div>';
        $html .= '<div style="padding:20px;text-align:center;font-size:12px;color:#666;">';
        $html .= '<p>' . __('You can manage your notification preferences in your profile settings.', 'family-tree-connect') . '</p>';
        $html .= '</div></div></body></html>';
        
        return $html;
    }
}
