<?php
/**
 * Shortcodes class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Shortcodes {
    
    public function __construct() {
        add_shortcode('ftc_tree', array($this, 'tree_shortcode'));
        add_shortcode('ftc_person', array($this, 'person_shortcode'));
        add_shortcode('ftc_family', array($this, 'family_shortcode'));
        add_shortcode('ftc_chart', array($this, 'chart_shortcode'));
        add_shortcode('ftc_search', array($this, 'search_shortcode'));
        add_shortcode('ftc_index', array($this, 'index_shortcode'));
        add_shortcode('ftc_place', array($this, 'place_shortcode'));
        add_shortcode('ftc_dashboard', array($this, 'dashboard_shortcode'));
    }
    
    /**
     * Family tree shortcode
     * [ftc_tree id="1" show_chart="1"]
     */
    public function tree_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'show_chart' => 1,
            'show_index' => 1,
        ), $atts);
        
        if (!$atts['id']) {
            return '<p>' . __('Please specify a tree ID.', 'family-tree-connect') . '</p>';
        }
        
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $tree = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['trees']} WHERE id = %d",
            $atts['id']
        ));
        
        if (!$tree) {
            return '<p>' . __('Tree not found.', 'family-tree-connect') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="ftc-tree-container" data-tree-id="<?php echo esc_attr($tree->id); ?>">
            <h2><?php echo esc_html($tree->name); ?></h2>
            
            <?php if ($tree->description) : ?>
                <p class="ftc-tree-description"><?php echo esc_html($tree->description); ?></p>
            <?php endif; ?>
            
            <?php if ($atts['show_chart'] && $tree->source_person_id) : ?>
                <div class="ftc-tree-chart">
                    <?php echo $this->chart_shortcode(array('person_id' => $tree->source_person_id)); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_index']) : ?>
                <div class="ftc-tree-index">
                    <?php echo $this->index_shortcode(array('tree_id' => $tree->id)); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Person shortcode
     * [ftc_person id="1"] or [ftc_person uuid="xxx"]
     */
    public function person_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'uuid' => '',
            'show_media' => 1,
            'show_timeline' => 1,
            'show_family' => 1,
        ), $atts);
        
        if ($atts['uuid']) {
            $person = FTC_Core::get_instance()->person->get_by_uuid($atts['uuid']);
        } elseif ($atts['id']) {
            $person = FTC_Core::get_instance()->person->get($atts['id']);
        } else {
            return '<p>' . __('Please specify a person ID or UUID.', 'family-tree-connect') . '</p>';
        }
        
        if (!$person) {
            return '<p>' . __('Person not found.', 'family-tree-connect') . '</p>';
        }
        
        $parents = FTC_Core::get_instance()->person->get_parents($person->id);
        $spouses = FTC_Core::get_instance()->person->get_spouses($person->id);
        $children = FTC_Core::get_instance()->person->get_children($person->id);
        $siblings = FTC_Core::get_instance()->person->get_siblings($person->id);
        
        ob_start();
        include FTC_PLUGIN_DIR . 'templates/person-profile.php';
        return ob_get_clean();
    }
    
    /**
     * Family shortcode
     * [ftc_family id="1"]
     */
    public function family_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'uuid' => '',
        ), $atts);
        
        if ($atts['uuid']) {
            $family = FTC_Core::get_instance()->family->get_by_uuid($atts['uuid']);
        } elseif ($atts['id']) {
            $family = FTC_Core::get_instance()->family->get($atts['id']);
        } else {
            return '<p>' . __('Please specify a family ID.', 'family-tree-connect') . '</p>';
        }
        
        if (!$family) {
            return '<p>' . __('Family not found.', 'family-tree-connect') . '</p>';
        }
        
        $family_view = FTC_Core::get_instance()->family->get_family_view($family->id);
        
        ob_start();
        include FTC_PLUGIN_DIR . 'templates/family-view.php';
        return ob_get_clean();
    }
    
    /**
     * Chart shortcode
     * [ftc_chart person_id="1" type="ancestor" direction="TB" generations="4"]
     */
    public function chart_shortcode($atts) {
        $atts = shortcode_atts(array(
            'person_id' => 0,
            'type' => 'ancestor',
            'direction' => 'TB',
            'generations' => 4,
            'show_photos' => 1,
            'show_dates' => 1,
        ), $atts);
        
        if (!$atts['person_id']) {
            return '<p>' . __('Please specify a person ID.', 'family-tree-connect') . '</p>';
        }
        
        $chart_data = FTC_Core::get_instance()->chart->generate($atts['person_id'], array(
            'type' => $atts['type'],
            'direction' => $atts['direction'],
            'generations' => (int) $atts['generations'],
            'show_photos' => (bool) $atts['show_photos'],
            'show_dates' => (bool) $atts['show_dates'],
        ));
        
        if (!$chart_data) {
            return '<p>' . __('Could not generate chart.', 'family-tree-connect') . '</p>';
        }
        
        return FTC_Core::get_instance()->chart->render_html($chart_data);
    }
    
    /**
     * Search shortcode
     * [ftc_search tree_id="1"]
     */
    public function search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'tree_id' => 0,
            'placeholder' => __('Search for people...', 'family-tree-connect'),
        ), $atts);
        
        ob_start();
        ?>
        <div class="ftc-search-container" data-tree-id="<?php echo esc_attr($atts['tree_id']); ?>">
            <form class="ftc-search-form" role="search">
                <input type="search" class="ftc-search-input" placeholder="<?php echo esc_attr($atts['placeholder']); ?>" autocomplete="off">
                <button type="submit" class="ftc-search-button"><?php _e('Search', 'family-tree-connect'); ?></button>
            </form>
            <div class="ftc-search-suggestions" style="display:none;"></div>
            <div class="ftc-search-results"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Index shortcode
     * [ftc_index tree_id="1"]
     */
    public function index_shortcode($atts) {
        $atts = shortcode_atts(array(
            'tree_id' => 0,
            'letter' => '',
        ), $atts);
        
        $letters = FTC_Core::get_instance()->search->get_index_letters($atts['tree_id'] ?: null);
        $current_letter = $atts['letter'] ?: (isset($_GET['letter']) ? sanitize_text_field($_GET['letter']) : '');
        
        $persons = array();
        if ($current_letter) {
            $persons = FTC_Core::get_instance()->search->get_person_index(array(
                'tree_id' => $atts['tree_id'] ?: null,
                'letter' => $current_letter,
            ));
        }
        
        ob_start();
        ?>
        <div class="ftc-index-container">
            <div class="ftc-index-letters">
                <?php foreach ($letters as $letter) : ?>
                    <a href="<?php echo esc_url(add_query_arg('letter', $letter)); ?>" 
                       class="ftc-index-letter <?php echo $letter === $current_letter ? 'active' : ''; ?>">
                        <?php echo esc_html($letter); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if ($current_letter && !empty($persons)) : ?>
                <div class="ftc-index-list">
                    <h3><?php echo esc_html($current_letter); ?></h3>
                    <ul>
                        <?php foreach ($persons as $person) : ?>
                            <li>
                                <a href="<?php echo esc_url($person->url); ?>">
                                    <?php echo esc_html($person->display_name); ?>
                                    <?php if ($person->birth_date || $person->death_date) : ?>
                                        <span class="ftc-dates">
                                            (<?php echo esc_html($person->birth_date ?: '?'); ?> - <?php echo esc_html($person->death_date ?: ''); ?>)
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif ($current_letter) : ?>
                <p><?php _e('No people found for this letter.', 'family-tree-connect'); ?></p>
            <?php else : ?>
                <p><?php _e('Select a letter to view people.', 'family-tree-connect'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Place shortcode
     * [ftc_place id="1"]
     */
    public function place_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts);
        
        if (!$atts['id']) {
            return '<p>' . __('Please specify a place ID.', 'family-tree-connect') . '</p>';
        }
        
        $place = FTC_Core::get_instance()->places->get($atts['id']);
        
        if (!$place) {
            return '<p>' . __('Place not found.', 'family-tree-connect') . '</p>';
        }
        
        $stats = FTC_Core::get_instance()->places->get_place_stats($place->id);
        $people = FTC_Core::get_instance()->places->get_people_at_place($place->id);
        
        ob_start();
        ?>
        <div class="ftc-place-container">
            <h2><?php echo esc_html($place->name); ?></h2>
            
            <div class="ftc-place-stats">
                <span><?php printf(__('%d people associated', 'family-tree-connect'), $stats['total_people']); ?></span>
                <?php if ($stats['births']) : ?>
                    <span><?php printf(__('%d births', 'family-tree-connect'), $stats['births']); ?></span>
                <?php endif; ?>
                <?php if ($stats['deaths']) : ?>
                    <span><?php printf(__('%d deaths', 'family-tree-connect'), $stats['deaths']); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($people)) : ?>
                <h3><?php _e('People', 'family-tree-connect'); ?></h3>
                <ul class="ftc-place-people">
                    <?php foreach ($people as $person) : ?>
                        <li>
                            <a href="<?php echo esc_url(add_query_arg('ftc_person', $person->uuid, home_url('family-tree/person/'))); ?>">
                                <?php echo esc_html($person->display_name); ?>
                            </a>
                            <span class="ftc-context">(<?php echo esc_html($person->context); ?>)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * User dashboard shortcode
     * [ftc_dashboard]
     */
    public function dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your dashboard.', 'family-tree-connect') . '</p>';
        }
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        // Get user's trees
        $trees = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tables['trees']} WHERE user_id = %d ORDER BY name",
            $user_id
        ));
        
        // Get pending merge requests
        $merge_requests = FTC_Core::get_instance()->merge->get_pending_for_user($user_id);
        
        // Get recent notifications
        $notifications = FTC_Core::get_instance()->notification->get_for_user($user_id, array('limit' => 10));
        
        // Get face suggestions
        $face_suggestions = FTC_Core::get_instance()->facial_recognition->get_pending_suggestions($user_id, 5);
        
        ob_start();
        include FTC_PLUGIN_DIR . 'templates/dashboard.php';
        return ob_get_clean();
    }
}
