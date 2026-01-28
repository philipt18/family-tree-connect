<?php
/**
 * Chart generation class for family tree visualization
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Chart {
    
    /**
     * Chart types
     */
    const TYPE_ANCESTOR = 'ancestor';
    const TYPE_DESCENDANT = 'descendant';
    const TYPE_HOURGLASS = 'hourglass';
    const TYPE_FAMILY = 'family';
    
    /**
     * Layout directions
     */
    const DIRECTION_TOP_BOTTOM = 'TB';
    const DIRECTION_LEFT_RIGHT = 'LR';
    const DIRECTION_BOTTOM_TOP = 'BT';
    const DIRECTION_RIGHT_LEFT = 'RL';
    
    /**
     * Default colors for chart boxes
     */
    private static $colors = array(
        'male' => '#a8d5e5',
        'female' => '#f5c6d6',
        'other' => '#d4c4e8',
        'unknown' => '#e0e0e0',
        'selected' => '#fff3cd',
        'border' => '#333333',
    );
    
    /**
     * Get chart types
     */
    public static function get_chart_types() {
        return array(
            self::TYPE_ANCESTOR => __('Ancestor Chart', 'family-tree-connect'),
            self::TYPE_DESCENDANT => __('Descendant Chart', 'family-tree-connect'),
            self::TYPE_HOURGLASS => __('Hourglass Chart', 'family-tree-connect'),
            self::TYPE_FAMILY => __('Family Group Chart', 'family-tree-connect'),
        );
    }
    
    /**
     * Get layout directions
     */
    public static function get_directions() {
        return array(
            self::DIRECTION_TOP_BOTTOM => __('Top to Bottom', 'family-tree-connect'),
            self::DIRECTION_LEFT_RIGHT => __('Left to Right', 'family-tree-connect'),
            self::DIRECTION_BOTTOM_TOP => __('Bottom to Top', 'family-tree-connect'),
            self::DIRECTION_RIGHT_LEFT => __('Right to Left', 'family-tree-connect'),
        );
    }
    
    /**
     * Generate chart data for a person
     */
    public function generate($person_id, $args = array()) {
        $defaults = array(
            'type' => self::TYPE_ANCESTOR,
            'direction' => self::DIRECTION_TOP_BOTTOM,
            'generations' => 4,
            'show_photos' => true,
            'show_dates' => true,
            'show_places' => false,
            'colors' => self::$colors,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $person = FTC_Core::get_instance()->person->get($person_id);
        if (!$person) {
            return null;
        }
        
        $chart_data = array(
            'root' => $person_id,
            'type' => $args['type'],
            'direction' => $args['direction'],
            'nodes' => array(),
            'edges' => array(),
            'config' => $args,
        );
        
        switch ($args['type']) {
            case self::TYPE_ANCESTOR:
                $this->build_ancestor_chart($person_id, $chart_data, $args, 0);
                break;
                
            case self::TYPE_DESCENDANT:
                $this->build_descendant_chart($person_id, $chart_data, $args, 0);
                break;
                
            case self::TYPE_HOURGLASS:
                $this->build_ancestor_chart($person_id, $chart_data, $args, 0);
                $this->build_descendant_chart($person_id, $chart_data, $args, 0);
                break;
                
            case self::TYPE_FAMILY:
                $this->build_family_chart($person_id, $chart_data, $args);
                break;
        }
        
        return $chart_data;
    }
    
    /**
     * Build ancestor chart data
     */
    private function build_ancestor_chart($person_id, &$chart_data, $args, $generation) {
        if ($generation >= $args['generations']) {
            return;
        }
        
        $person = FTC_Core::get_instance()->person->get($person_id);
        if (!$person) {
            return;
        }
        
        // Add person node
        if (!isset($chart_data['nodes'][$person_id])) {
            $chart_data['nodes'][$person_id] = $this->create_node($person, $args, $generation);
        }
        
        // Get parents
        $parents = FTC_Core::get_instance()->person->get_parents($person_id);
        
        foreach (array('father', 'mother') as $parent_type) {
            if ($parents[$parent_type]) {
                $parent = $parents[$parent_type];
                
                // Add edge
                $chart_data['edges'][] = array(
                    'from' => $parent->id,
                    'to' => $person_id,
                    'type' => 'parent_child',
                );
                
                // Recursively add ancestors
                $this->build_ancestor_chart($parent->id, $chart_data, $args, $generation + 1);
            }
        }
    }
    
    /**
     * Build descendant chart data
     */
    private function build_descendant_chart($person_id, &$chart_data, $args, $generation) {
        if ($generation >= $args['generations']) {
            return;
        }
        
        $person = FTC_Core::get_instance()->person->get($person_id);
        if (!$person) {
            return;
        }
        
        // Add person node
        if (!isset($chart_data['nodes'][$person_id])) {
            $chart_data['nodes'][$person_id] = $this->create_node($person, $args, -$generation);
        }
        
        // Get spouses and children
        $families = FTC_Core::get_instance()->person->get_families_as_spouse($person_id);
        
        foreach ($families as $family) {
            // Add spouse
            $spouse_id = ($family->spouse1_id == $person_id) ? $family->spouse2_id : $family->spouse1_id;
            if ($spouse_id) {
                $spouse = FTC_Core::get_instance()->person->get($spouse_id);
                if ($spouse && !isset($chart_data['nodes'][$spouse_id])) {
                    $chart_data['nodes'][$spouse_id] = $this->create_node($spouse, $args, -$generation);
                    
                    // Add spouse edge
                    $chart_data['edges'][] = array(
                        'from' => $person_id,
                        'to' => $spouse_id,
                        'type' => 'spouse',
                        'family_id' => $family->id,
                    );
                }
            }
            
            // Add children
            foreach ($family->children as $child) {
                $chart_data['edges'][] = array(
                    'from' => $person_id,
                    'to' => $child->id,
                    'type' => 'parent_child',
                );
                
                // Recursively add descendants
                $this->build_descendant_chart($child->id, $chart_data, $args, $generation + 1);
            }
        }
    }
    
    /**
     * Build family group chart
     */
    private function build_family_chart($person_id, &$chart_data, $args) {
        $person = FTC_Core::get_instance()->person->get($person_id);
        if (!$person) {
            return;
        }
        
        // Add focal person
        $chart_data['nodes'][$person_id] = $this->create_node($person, $args, 0);
        
        // Add parents
        $parents = FTC_Core::get_instance()->person->get_parents($person_id);
        foreach (array('father', 'mother') as $parent_type) {
            if ($parents[$parent_type]) {
                $chart_data['nodes'][$parents[$parent_type]->id] = $this->create_node($parents[$parent_type], $args, 1);
                $chart_data['edges'][] = array(
                    'from' => $parents[$parent_type]->id,
                    'to' => $person_id,
                    'type' => 'parent_child',
                );
                
                // Add grandparents
                $grandparents = FTC_Core::get_instance()->person->get_parents($parents[$parent_type]->id);
                foreach (array('father', 'mother') as $gp_type) {
                    if ($grandparents[$gp_type]) {
                        $chart_data['nodes'][$grandparents[$gp_type]->id] = $this->create_node($grandparents[$gp_type], $args, 2);
                        $chart_data['edges'][] = array(
                            'from' => $grandparents[$gp_type]->id,
                            'to' => $parents[$parent_type]->id,
                            'type' => 'parent_child',
                        );
                    }
                }
            }
        }
        
        // Add siblings
        $siblings = FTC_Core::get_instance()->person->get_siblings($person_id);
        foreach ($siblings as $sibling) {
            $chart_data['nodes'][$sibling->id] = $this->create_node($sibling, $args, 0);
        }
        
        // Add spouses and children
        $families = FTC_Core::get_instance()->person->get_families_as_spouse($person_id);
        foreach ($families as $family) {
            $spouse_id = ($family->spouse1_id == $person_id) ? $family->spouse2_id : $family->spouse1_id;
            if ($spouse_id) {
                $spouse = FTC_Core::get_instance()->person->get($spouse_id);
                if ($spouse) {
                    $chart_data['nodes'][$spouse_id] = $this->create_node($spouse, $args, 0);
                    $chart_data['edges'][] = array(
                        'from' => $person_id,
                        'to' => $spouse_id,
                        'type' => 'spouse',
                    );
                }
            }
            
            foreach ($family->children as $child) {
                $chart_data['nodes'][$child->id] = $this->create_node($child, $args, -1);
                $chart_data['edges'][] = array(
                    'from' => $person_id,
                    'to' => $child->id,
                    'type' => 'parent_child',
                );
            }
        }
    }
    
    /**
     * Create a chart node
     */
    private function create_node($person, $args, $generation) {
        $node = array(
            'id' => $person->id,
            'name' => FTC_Core::get_instance()->person->get_display_name($person, 'short'),
            'full_name' => FTC_Core::get_instance()->person->get_display_name($person),
            'gender' => $person->gender,
            'generation' => $generation,
            'color' => $args['colors'][$person->gender] ?? $args['colors']['unknown'],
        );
        
        if ($args['show_photos'] && $person->default_photo_url) {
            $node['photo'] = $person->default_photo_url;
            $node['photo_crop'] = $person->default_photo_crop;
        }
        
        if ($args['show_dates']) {
            $node['birth_date'] = $person->birth_date ? FTC_Calendar::format_date(
                $person->birth_date,
                $person->birth_date_calendar,
                $person->birth_date_approximate
            ) : '';
            
            $node['death_date'] = $person->death_date ? FTC_Calendar::format_date(
                $person->death_date,
                $person->death_date_calendar,
                $person->death_date_approximate
            ) : '';
            
            $node['living'] = $person->living;
        }
        
        if ($args['show_places']) {
            $node['birth_place'] = $person->birth_location;
            $node['death_place'] = $person->death_location;
        }
        
        $node['url'] = add_query_arg(array(
            'ftc_person' => $person->uuid,
        ), home_url('family-tree/person/'));
        
        return $node;
    }
    
    /**
     * Render chart as HTML
     */
    public function render_html($chart_data) {
        if (!$chart_data) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="ftc-chart" 
             data-type="<?php echo esc_attr($chart_data['type']); ?>"
             data-direction="<?php echo esc_attr($chart_data['direction']); ?>"
             data-chart='<?php echo esc_attr(json_encode($chart_data)); ?>'>
            <div class="ftc-chart-toolbar">
                <button type="button" class="ftc-chart-zoom-in" title="<?php esc_attr_e('Zoom In', 'family-tree-connect'); ?>">+</button>
                <button type="button" class="ftc-chart-zoom-out" title="<?php esc_attr_e('Zoom Out', 'family-tree-connect'); ?>">−</button>
                <button type="button" class="ftc-chart-fit" title="<?php esc_attr_e('Fit to Screen', 'family-tree-connect'); ?>">⤢</button>
                <button type="button" class="ftc-chart-export-pdf" title="<?php esc_attr_e('Export PDF', 'family-tree-connect'); ?>">PDF</button>
            </div>
            <div class="ftc-chart-container">
                <div class="ftc-chart-canvas">
                    <!-- Chart will be rendered here by JavaScript -->
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render chart as SVG
     */
    public function render_svg($chart_data, $args = array()) {
        if (!$chart_data) {
            return '';
        }
        
        $defaults = array(
            'width' => 1200,
            'height' => 800,
            'box_width' => 180,
            'box_height' => 100,
            'spacing_x' => 40,
            'spacing_y' => 60,
            'photo_size' => 50,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Calculate positions for nodes
        $positions = $this->calculate_positions($chart_data, $args);
        
        // Build SVG
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" ';
        $svg .= 'width="' . $args['width'] . '" height="' . $args['height'] . '" ';
        $svg .= 'viewBox="0 0 ' . $args['width'] . ' ' . $args['height'] . '">';
        
        // Add styles
        $svg .= '<defs><style>';
        $svg .= '.ftc-node rect { stroke: ' . self::$colors['border'] . '; stroke-width: 1; }';
        $svg .= '.ftc-node text { font-family: Arial, sans-serif; font-size: 12px; }';
        $svg .= '.ftc-edge { stroke: ' . self::$colors['border'] . '; stroke-width: 1; fill: none; }';
        $svg .= '.ftc-edge-spouse { stroke-dasharray: 5,5; }';
        $svg .= '</style></defs>';
        
        // Draw edges first (behind nodes)
        foreach ($chart_data['edges'] as $edge) {
            if (isset($positions[$edge['from']]) && isset($positions[$edge['to']])) {
                $from = $positions[$edge['from']];
                $to = $positions[$edge['to']];
                
                $class = 'ftc-edge';
                if ($edge['type'] === 'spouse') {
                    $class .= ' ftc-edge-spouse';
                }
                
                $svg .= $this->draw_edge($from, $to, $args, $class, $chart_data['direction']);
            }
        }
        
        // Draw nodes
        foreach ($chart_data['nodes'] as $id => $node) {
            if (isset($positions[$id])) {
                $svg .= $this->draw_node($node, $positions[$id], $args);
            }
        }
        
        $svg .= '</svg>';
        
        return $svg;
    }
    
    /**
     * Calculate node positions
     */
    private function calculate_positions($chart_data, $args) {
        $positions = array();
        
        // Group nodes by generation
        $generations = array();
        foreach ($chart_data['nodes'] as $id => $node) {
            $gen = $node['generation'];
            if (!isset($generations[$gen])) {
                $generations[$gen] = array();
            }
            $generations[$gen][] = $id;
        }
        
        // Sort generations
        ksort($generations);
        
        // Calculate positions based on direction
        $is_vertical = in_array($chart_data['direction'], array(self::DIRECTION_TOP_BOTTOM, self::DIRECTION_BOTTOM_TOP));
        $is_reversed = in_array($chart_data['direction'], array(self::DIRECTION_BOTTOM_TOP, self::DIRECTION_RIGHT_LEFT));
        
        $gen_index = 0;
        $gen_keys = array_keys($generations);
        
        if ($is_reversed) {
            $gen_keys = array_reverse($gen_keys);
        }
        
        foreach ($gen_keys as $gen) {
            $nodes = $generations[$gen];
            $count = count($nodes);
            
            foreach ($nodes as $i => $id) {
                if ($is_vertical) {
                    $x = ($args['width'] / 2) - (($count - 1) * ($args['box_width'] + $args['spacing_x']) / 2) + ($i * ($args['box_width'] + $args['spacing_x']));
                    $y = 50 + ($gen_index * ($args['box_height'] + $args['spacing_y']));
                } else {
                    $x = 50 + ($gen_index * ($args['box_width'] + $args['spacing_x']));
                    $y = ($args['height'] / 2) - (($count - 1) * ($args['box_height'] + $args['spacing_y']) / 2) + ($i * ($args['box_height'] + $args['spacing_y']));
                }
                
                $positions[$id] = array('x' => $x, 'y' => $y);
            }
            
            $gen_index++;
        }
        
        return $positions;
    }
    
    /**
     * Draw a node as SVG
     */
    private function draw_node($node, $position, $args) {
        $x = $position['x'];
        $y = $position['y'];
        $w = $args['box_width'];
        $h = $args['box_height'];
        
        $svg = '<g class="ftc-node" data-id="' . $node['id'] . '">';
        
        // Background rectangle
        $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" ';
        $svg .= 'fill="' . $node['color'] . '" rx="5" ry="5" />';
        
        // Photo
        $photo_offset = 0;
        if (!empty($node['photo'])) {
            $photo_x = $x + 5;
            $photo_y = $y + ($h - $args['photo_size']) / 2;
            $svg .= '<image href="' . esc_attr($node['photo']) . '" ';
            $svg .= 'x="' . $photo_x . '" y="' . $photo_y . '" ';
            $svg .= 'width="' . $args['photo_size'] . '" height="' . $args['photo_size'] . '" ';
            $svg .= 'preserveAspectRatio="xMidYMid slice" clip-path="inset(0 round 5px)" />';
            $photo_offset = $args['photo_size'] + 5;
        }
        
        // Name
        $text_x = $x + 10 + $photo_offset;
        $text_y = $y + 20;
        $svg .= '<text x="' . $text_x . '" y="' . $text_y . '" font-weight="bold">';
        $svg .= esc_html($node['name']);
        $svg .= '</text>';
        
        // Dates
        if (!empty($node['birth_date']) || !empty($node['death_date'])) {
            $text_y += 16;
            $dates = '';
            if (!empty($node['birth_date'])) {
                $dates .= 'b. ' . $node['birth_date'];
            }
            if (!empty($node['death_date'])) {
                $dates .= ($dates ? ' ' : '') . 'd. ' . $node['death_date'];
            }
            $svg .= '<text x="' . $text_x . '" y="' . $text_y . '" font-size="10">';
            $svg .= esc_html($dates);
            $svg .= '</text>';
        }
        
        $svg .= '</g>';
        
        return $svg;
    }
    
    /**
     * Draw an edge as SVG
     */
    private function draw_edge($from, $to, $args, $class, $direction) {
        $x1 = $from['x'] + $args['box_width'] / 2;
        $y1 = $from['y'] + $args['box_height'];
        $x2 = $to['x'] + $args['box_width'] / 2;
        $y2 = $to['y'];
        
        if (in_array($direction, array(self::DIRECTION_LEFT_RIGHT, self::DIRECTION_RIGHT_LEFT))) {
            $x1 = $from['x'] + $args['box_width'];
            $y1 = $from['y'] + $args['box_height'] / 2;
            $x2 = $to['x'];
            $y2 = $to['y'] + $args['box_height'] / 2;
        }
        
        // Draw as bezier curve
        $mid_x = ($x1 + $x2) / 2;
        $mid_y = ($y1 + $y2) / 2;
        
        return '<path class="' . $class . '" d="M' . $x1 . ',' . $y1 . ' Q' . $x1 . ',' . $mid_y . ' ' . $mid_x . ',' . $mid_y . ' T' . $x2 . ',' . $y2 . '" />';
    }
}
