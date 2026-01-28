<?php
/**
 * Search functionality class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Search {
    
    /**
     * Search for people
     */
    public function search_persons($query, $args = array()) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $defaults = array(
            'tree_id' => null,
            'user_id' => null,
            'limit' => 20,
            'offset' => 0,
            'include_maiden_name' => true,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query = sanitize_text_field($query);
        
        if (strlen($query) < 2) {
            return array();
        }
        
        // Build search query
        $search_term = '%' . $wpdb->esc_like($query) . '%';
        
        $sql = "SELECT DISTINCT p.* FROM {$tables['persons']} p";
        $where = array();
        $params = array();
        
        // Join with tree if specified
        if ($args['tree_id']) {
            $sql .= " INNER JOIN {$tables['tree_persons']} tp ON p.id = tp.person_id";
            $where[] = "tp.tree_id = %d";
            $params[] = $args['tree_id'];
        }
        
        // Name search conditions
        $name_conditions = array(
            "p.first_name LIKE %s",
            "p.middle_name LIKE %s",
            "p.surname LIKE %s",
            "p.nickname LIKE %s",
        );
        
        if ($args['include_maiden_name']) {
            $name_conditions[] = "p.maiden_name LIKE %s";
        }
        
        // Build full name search
        $name_conditions[] = "CONCAT_WS(' ', p.first_name, p.middle_name, p.surname) LIKE %s";
        $name_conditions[] = "CONCAT_WS(' ', p.first_name, p.surname) LIKE %s";
        
        $where[] = '(' . implode(' OR ', $name_conditions) . ')';
        
        // Add search term params
        $param_count = substr_count(implode('', $name_conditions), '%s');
        for ($i = 0; $i < $param_count; $i++) {
            $params[] = $search_term;
        }
        
        // User filter
        if ($args['user_id']) {
            $where[] = "p.created_by = %d";
            $params[] = $args['user_id'];
        }
        
        $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY p.surname ASC, p.first_name ASC";
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        
        foreach ($results as &$person) {
            $person->display_name = FTC_Core::get_instance()->person->get_display_name($person);
            $person->url = add_query_arg('ftc_person', $person->uuid, home_url('family-tree/person/'));
        }
        
        return $results;
    }
    
    /**
     * Search for places
     */
    public function search_places($query, $args = array()) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query = sanitize_text_field($query);
        
        if (strlen($query) < 2) {
            return array();
        }
        
        $search_term = '%' . $wpdb->esc_like($query) . '%';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$tables['places']} 
             WHERE name LIKE %s OR normalized_name LIKE %s
             ORDER BY name ASC
             LIMIT %d OFFSET %d",
            $search_term,
            $search_term,
            $args['limit'],
            $args['offset']
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Advanced search with multiple criteria
     */
    public function advanced_search($criteria) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $where = array('1=1');
        $params = array();
        
        // Name criteria
        if (!empty($criteria['first_name'])) {
            $where[] = "p.first_name LIKE %s";
            $params[] = '%' . $wpdb->esc_like($criteria['first_name']) . '%';
        }
        
        if (!empty($criteria['surname'])) {
            $where[] = "(p.surname LIKE %s OR p.maiden_name LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($criteria['surname']) . '%';
            $params[] = '%' . $wpdb->esc_like($criteria['surname']) . '%';
        }
        
        // Gender
        if (!empty($criteria['gender']) && $criteria['gender'] !== 'any') {
            $where[] = "p.gender = %s";
            $params[] = $criteria['gender'];
        }
        
        // Living status
        if (isset($criteria['living']) && $criteria['living'] !== '') {
            $where[] = "p.living = %d";
            $params[] = (int) $criteria['living'];
        }
        
        // Birth date range
        if (!empty($criteria['birth_date_from'])) {
            $where[] = "p.birth_date >= %s";
            $params[] = $criteria['birth_date_from'];
        }
        
        if (!empty($criteria['birth_date_to'])) {
            $where[] = "p.birth_date <= %s";
            $params[] = $criteria['birth_date_to'];
        }
        
        // Birth place
        if (!empty($criteria['birth_place'])) {
            $where[] = "p.birth_location LIKE %s";
            $params[] = '%' . $wpdb->esc_like($criteria['birth_place']) . '%';
        }
        
        // Death date range
        if (!empty($criteria['death_date_from'])) {
            $where[] = "p.death_date >= %s";
            $params[] = $criteria['death_date_from'];
        }
        
        if (!empty($criteria['death_date_to'])) {
            $where[] = "p.death_date <= %s";
            $params[] = $criteria['death_date_to'];
        }
        
        // Death place
        if (!empty($criteria['death_place'])) {
            $where[] = "p.death_location LIKE %s";
            $params[] = '%' . $wpdb->esc_like($criteria['death_place']) . '%';
        }
        
        // Occupation
        if (!empty($criteria['occupation'])) {
            $where[] = "p.occupation LIKE %s";
            $params[] = '%' . $wpdb->esc_like($criteria['occupation']) . '%';
        }
        
        // Tree filter
        $join = '';
        if (!empty($criteria['tree_id'])) {
            $join = "INNER JOIN {$tables['tree_persons']} tp ON p.id = tp.person_id";
            $where[] = "tp.tree_id = %d";
            $params[] = $criteria['tree_id'];
        }
        
        // Pagination
        $limit = isset($criteria['limit']) ? absint($criteria['limit']) : 50;
        $offset = isset($criteria['offset']) ? absint($criteria['offset']) : 0;
        
        $params[] = $limit;
        $params[] = $offset;
        
        $sql = $wpdb->prepare(
            "SELECT DISTINCT p.* FROM {$tables['persons']} p
             $join
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.surname ASC, p.first_name ASC
             LIMIT %d OFFSET %d",
            ...$params
        );
        
        $results = $wpdb->get_results($sql);
        
        foreach ($results as &$person) {
            $person->display_name = FTC_Core::get_instance()->person->get_display_name($person);
        }
        
        return $results;
    }
    
    /**
     * Get search suggestions (autocomplete)
     */
    public function get_suggestions($query, $type = 'person', $limit = 10) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $query = sanitize_text_field($query);
        
        if (strlen($query) < 2) {
            return array();
        }
        
        $search_term = $wpdb->esc_like($query) . '%';
        
        $suggestions = array();
        
        switch ($type) {
            case 'person':
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, first_name, surname, uuid FROM {$tables['persons']}
                     WHERE first_name LIKE %s OR surname LIKE %s
                     ORDER BY surname, first_name
                     LIMIT %d",
                    $search_term,
                    $search_term,
                    $limit
                ));
                
                foreach ($results as $person) {
                    $suggestions[] = array(
                        'id' => $person->id,
                        'value' => trim($person->first_name . ' ' . $person->surname),
                        'uuid' => $person->uuid,
                    );
                }
                break;
                
            case 'place':
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, name FROM {$tables['places']}
                     WHERE name LIKE %s
                     ORDER BY name
                     LIMIT %d",
                    $search_term,
                    $limit
                ));
                
                foreach ($results as $place) {
                    $suggestions[] = array(
                        'id' => $place->id,
                        'value' => $place->name,
                    );
                }
                break;
                
            case 'surname':
                $results = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT surname FROM {$tables['persons']}
                     WHERE surname LIKE %s
                     ORDER BY surname
                     LIMIT %d",
                    $search_term,
                    $limit
                ));
                
                foreach ($results as $surname) {
                    $suggestions[] = array(
                        'value' => $surname,
                    );
                }
                break;
        }
        
        return $suggestions;
    }
    
    /**
     * Get people index (alphabetical listing)
     */
    public function get_person_index($args = array()) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $defaults = array(
            'tree_id' => null,
            'letter' => null,
            'limit' => 100,
            'offset' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $params = array();
        $join = '';
        
        if ($args['tree_id']) {
            $join = "INNER JOIN {$tables['tree_persons']} tp ON p.id = tp.person_id";
            $where[] = "tp.tree_id = %d";
            $params[] = $args['tree_id'];
        }
        
        if ($args['letter']) {
            $where[] = "p.surname LIKE %s";
            $params[] = $wpdb->esc_like($args['letter']) . '%';
        }
        
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        $sql = $wpdb->prepare(
            "SELECT DISTINCT p.* FROM {$tables['persons']} p
             $join
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.surname ASC, p.first_name ASC
             LIMIT %d OFFSET %d",
            ...$params
        );
        
        $results = $wpdb->get_results($sql);
        
        foreach ($results as &$person) {
            $person->display_name = FTC_Core::get_instance()->person->get_display_name($person);
        }
        
        return $results;
    }
    
    /**
     * Get available letters for index
     */
    public function get_index_letters($tree_id = null) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $join = '';
        $where = '';
        $params = array();
        
        if ($tree_id) {
            $join = "INNER JOIN {$tables['tree_persons']} tp ON p.id = tp.person_id";
            $where = "WHERE tp.tree_id = %d";
            $params[] = $tree_id;
        }
        
        if ($params) {
            $sql = $wpdb->prepare(
                "SELECT DISTINCT UPPER(LEFT(p.surname, 1)) as letter 
                 FROM {$tables['persons']} p
                 $join
                 $where
                 ORDER BY letter",
                ...$params
            );
        } else {
            $sql = "SELECT DISTINCT UPPER(LEFT(p.surname, 1)) as letter 
                    FROM {$tables['persons']} p
                    ORDER BY letter";
        }
        
        return $wpdb->get_col($sql);
    }
}
