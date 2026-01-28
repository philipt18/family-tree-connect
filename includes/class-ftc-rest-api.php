<?php
/**
 * REST API class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_REST_API {
    
    private $namespace = 'ftc/v1';
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    public function register_routes() {
        // Persons
        register_rest_route($this->namespace, '/persons', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_persons'),
            'permission_callback' => array($this, 'check_read_permission'),
        ));
        
        register_rest_route($this->namespace, '/persons/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_person'),
            'permission_callback' => array($this, 'check_read_permission'),
        ));
        
        register_rest_route($this->namespace, '/persons', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_person'),
            'permission_callback' => array($this, 'check_write_permission'),
        ));
        
        register_rest_route($this->namespace, '/persons/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_person'),
            'permission_callback' => array($this, 'check_edit_person_permission'),
        ));
        
        register_rest_route($this->namespace, '/persons/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_person'),
            'permission_callback' => array($this, 'check_edit_person_permission'),
        ));
        
        // Families
        register_rest_route($this->namespace, '/families/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_family'),
            'permission_callback' => array($this, 'check_read_permission'),
        ));
        
        register_rest_route($this->namespace, '/families', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_family'),
            'permission_callback' => array($this, 'check_write_permission'),
        ));
        
        // Trees
        register_rest_route($this->namespace, '/trees', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_trees'),
            'permission_callback' => array($this, 'check_write_permission'),
        ));
        
        register_rest_route($this->namespace, '/trees/(?P<id>\d+)/persons', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_tree_persons'),
            'permission_callback' => array($this, 'check_read_permission'),
        ));
        
        // Search
        register_rest_route($this->namespace, '/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'search'),
            'permission_callback' => '__return_true',
        ));
        
        // Chart
        register_rest_route($this->namespace, '/chart/(?P<person_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_chart'),
            'permission_callback' => '__return_true',
        ));
        
        // Relationship
        register_rest_route($this->namespace, '/relationship/(?P<person1_id>\d+)/(?P<person2_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_relationship'),
            'permission_callback' => '__return_true',
        ));
        
        // Places
        register_rest_route($this->namespace, '/places', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_places'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route($this->namespace, '/places/(?P<id>\d+)/people', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_place_people'),
            'permission_callback' => '__return_true',
        ));
    }
    
    // Permission callbacks
    public function check_read_permission() {
        return true; // Public trees are readable
    }
    
    public function check_write_permission() {
        return is_user_logged_in();
    }
    
    public function check_edit_person_permission($request) {
        return FTC_Core::can_edit_person($request['id']);
    }
    
    // Endpoints
    public function get_persons($request) {
        $tree_id = $request->get_param('tree_id');
        $limit = $request->get_param('limit') ?: 100;
        $offset = $request->get_param('offset') ?: 0;
        
        if ($tree_id) {
            $persons = FTC_Core::get_instance()->person->get_by_tree($tree_id, array(
                'limit' => $limit,
                'offset' => $offset,
            ));
        } else {
            return new WP_Error('missing_tree', __('Tree ID required.', 'family-tree-connect'), array('status' => 400));
        }
        
        return rest_ensure_response($persons);
    }
    
    public function get_person($request) {
        $person = FTC_Core::get_instance()->person->get($request['id']);
        
        if (!$person) {
            return new WP_Error('not_found', __('Person not found.', 'family-tree-connect'), array('status' => 404));
        }
        
        return rest_ensure_response($person);
    }
    
    public function create_person($request) {
        $data = $request->get_json_params();
        $result = FTC_Core::get_instance()->person->create($data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $person = FTC_Core::get_instance()->person->get($result);
        return rest_ensure_response($person);
    }
    
    public function update_person($request) {
        $data = $request->get_json_params();
        $result = FTC_Core::get_instance()->person->update($request['id'], $data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $person = FTC_Core::get_instance()->person->get($request['id']);
        return rest_ensure_response($person);
    }
    
    public function delete_person($request) {
        $result = FTC_Core::get_instance()->person->delete($request['id']);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array('deleted' => true));
    }
    
    public function get_family($request) {
        $family = FTC_Core::get_instance()->family->get($request['id']);
        
        if (!$family) {
            return new WP_Error('not_found', __('Family not found.', 'family-tree-connect'), array('status' => 404));
        }
        
        return rest_ensure_response($family);
    }
    
    public function create_family($request) {
        $data = $request->get_json_params();
        $result = FTC_Core::get_instance()->family->create($data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $family = FTC_Core::get_instance()->family->get($result);
        return rest_ensure_response($family);
    }
    
    public function get_trees($request) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $user_id = get_current_user_id();
        $trees = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['trees']} WHERE user_id = %d ORDER BY name",
            $user_id
        ));
        
        return rest_ensure_response($trees);
    }
    
    public function get_tree_persons($request) {
        $persons = FTC_Core::get_instance()->person->get_by_tree($request['id']);
        return rest_ensure_response($persons);
    }
    
    public function search($request) {
        $query = $request->get_param('q');
        $type = $request->get_param('type') ?: 'person';
        $tree_id = $request->get_param('tree_id');
        
        if ($type === 'person') {
            $results = FTC_Core::get_instance()->search->search_persons($query, array(
                'tree_id' => $tree_id,
            ));
        } else {
            $results = FTC_Core::get_instance()->search->search_places($query);
        }
        
        return rest_ensure_response($results);
    }
    
    public function get_chart($request) {
        $type = $request->get_param('type') ?: 'ancestor';
        $direction = $request->get_param('direction') ?: 'TB';
        $generations = $request->get_param('generations') ?: 4;
        
        $chart_data = FTC_Core::get_instance()->chart->generate($request['person_id'], array(
            'type' => $type,
            'direction' => $direction,
            'generations' => (int) $generations,
        ));
        
        if (!$chart_data) {
            return new WP_Error('chart_error', __('Could not generate chart.', 'family-tree-connect'), array('status' => 500));
        }
        
        return rest_ensure_response($chart_data);
    }
    
    public function get_relationship($request) {
        $relationship = FTC_Core::get_instance()->relationship->calculate_relationship(
            $request['person1_id'],
            $request['person2_id']
        );
        
        return rest_ensure_response(array('relationship' => $relationship));
    }
    
    public function get_places($request) {
        $query = $request->get_param('q');
        
        if ($query) {
            $places = FTC_Core::get_instance()->search->search_places($query);
        } else {
            $places = FTC_Core::get_instance()->places->get_all();
        }
        
        return rest_ensure_response($places);
    }
    
    public function get_place_people($request) {
        $people = FTC_Core::get_instance()->places->get_people_at_place($request['id']);
        return rest_ensure_response($people);
    }
}
