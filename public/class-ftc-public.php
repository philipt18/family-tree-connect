<?php
/**
 * Public-facing functionality
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Public {
    
    public function __construct() {
        add_action('template_redirect', array($this, 'handle_routes'));
        add_filter('document_title_parts', array($this, 'filter_title'));
        add_action('wp_head', array($this, 'add_meta_tags'));
    }
    
    public function handle_routes() {
        // Handle person route
        $person_uuid = get_query_var('ftc_person');
        if ($person_uuid) {
            $this->render_person_page($person_uuid);
            exit;
        }
        
        // Handle family route
        $family_uuid = get_query_var('ftc_family');
        if ($family_uuid) {
            $this->render_family_page($family_uuid);
            exit;
        }
        
        // Handle chart route
        $chart_uuid = get_query_var('ftc_chart');
        if ($chart_uuid) {
            $this->render_chart_page($chart_uuid);
            exit;
        }
        
        // Handle place route
        $place_id = get_query_var('ftc_place');
        if ($place_id) {
            $this->render_place_page($place_id);
            exit;
        }
    }
    
    private function render_person_page($uuid) {
        $person = FTC_Core::get_instance()->person->get_by_uuid($uuid);
        
        if (!$person) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            get_template_part(404);
            return;
        }
        
        $parents = FTC_Core::get_instance()->person->get_parents($person->id);
        $spouses = FTC_Core::get_instance()->person->get_spouses($person->id);
        $children = FTC_Core::get_instance()->person->get_children($person->id);
        $siblings = FTC_Core::get_instance()->person->get_siblings($person->id);
        $events = FTC_Core::get_instance()->event->get_by_person($person->id);
        $timeline = FTC_Core::get_instance()->event->get_timeline($person->id);
        $media_page = FTC_Core::get_instance()->media->get_media_page($person->id);
        
        $can_edit = FTC_Core::can_edit_person($person->id);
        
        // Load template
        get_header();
        include FTC_PLUGIN_DIR . 'templates/person-profile.php';
        get_footer();
    }
    
    private function render_family_page($uuid) {
        $family = FTC_Core::get_instance()->family->get_by_uuid($uuid);
        
        if (!$family) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            get_template_part(404);
            return;
        }
        
        $family_view = FTC_Core::get_instance()->family->get_family_view($family->id);
        
        get_header();
        include FTC_PLUGIN_DIR . 'templates/family-view.php';
        get_footer();
    }
    
    private function render_chart_page($uuid) {
        $person = FTC_Core::get_instance()->person->get_by_uuid($uuid);
        
        if (!$person) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            get_template_part(404);
            return;
        }
        
        $type = isset($_GET['type']) ? sanitize_key($_GET['type']) : 'ancestor';
        $direction = isset($_GET['direction']) ? sanitize_key($_GET['direction']) : 'TB';
        $generations = isset($_GET['generations']) ? absint($_GET['generations']) : 4;
        
        $chart_data = FTC_Core::get_instance()->chart->generate($person->id, array(
            'type' => $type,
            'direction' => $direction,
            'generations' => $generations,
        ));
        
        get_header();
        include FTC_PLUGIN_DIR . 'templates/chart-page.php';
        get_footer();
    }
    
    private function render_place_page($place_id) {
        $place = FTC_Core::get_instance()->places->get($place_id);
        
        if (!$place) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            get_template_part(404);
            return;
        }
        
        $stats = FTC_Core::get_instance()->places->get_place_stats($place->id);
        $people = FTC_Core::get_instance()->places->get_people_at_place($place->id);
        
        get_header();
        include FTC_PLUGIN_DIR . 'templates/place-page.php';
        get_footer();
    }
    
    public function filter_title($title_parts) {
        $person_uuid = get_query_var('ftc_person');
        if ($person_uuid) {
            $person = FTC_Core::get_instance()->person->get_by_uuid($person_uuid);
            if ($person) {
                $title_parts['title'] = $person->display_name;
            }
        }
        
        $family_uuid = get_query_var('ftc_family');
        if ($family_uuid) {
            $family = FTC_Core::get_instance()->family->get_by_uuid($family_uuid);
            if ($family) {
                $title_parts['title'] = $family->display_title;
            }
        }
        
        return $title_parts;
    }
    
    public function add_meta_tags() {
        $person_uuid = get_query_var('ftc_person');
        if ($person_uuid) {
            $person = FTC_Core::get_instance()->person->get_by_uuid($person_uuid);
            if ($person) {
                echo '<meta name="description" content="' . esc_attr($person->display_name) . ' - ' . __('Family Tree', 'family-tree-connect') . '">' . "\n";
                if ($person->default_photo_url) {
                    echo '<meta property="og:image" content="' . esc_url($person->default_photo_url) . '">' . "\n";
                }
            }
        }
    }
}
