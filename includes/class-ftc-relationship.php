<?php
/**
 * Relationship management class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Relationship {
    
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
        $this->table = $tables['relationships'];
    }
    
    /**
     * Create a spouse relationship
     */
    public function create_spouse_relationship($person1_id, $person2_id, $family_id = null) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} 
             WHERE ((person1_id = %d AND person2_id = %d) OR (person1_id = %d AND person2_id = %d))
             AND relationship_type = 'spouse'",
            $person1_id, $person2_id, $person2_id, $person1_id
        ));
        
        if ($exists) {
            return $exists;
        }
        
        $wpdb->insert($this->table, array(
            'person1_id' => $person1_id,
            'person2_id' => $person2_id,
            'relationship_type' => 'spouse',
            'family_id' => $family_id,
        ));
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create a parent-child relationship
     */
    public function create_parent_child_relationship($parent_id, $child_id, $family_id = null) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} 
             WHERE person1_id = %d AND person2_id = %d AND relationship_type = 'parent_child'",
            $parent_id, $child_id
        ));
        
        if ($exists) {
            return $exists;
        }
        
        $wpdb->insert($this->table, array(
            'person1_id' => $parent_id,
            'person2_id' => $child_id,
            'relationship_type' => 'parent_child',
            'family_id' => $family_id,
        ));
        
        return $wpdb->insert_id;
    }
    
    /**
     * Calculate relationship between two people
     */
    public function calculate_relationship($person1_id, $person2_id) {
        if ($person1_id == $person2_id) {
            return __('Self', 'family-tree-connect');
        }
        
        $path = $this->find_relationship_path($person1_id, $person2_id);
        
        if (!$path) {
            return __('Not related', 'family-tree-connect');
        }
        
        return $this->path_to_relationship_name($path, $person1_id, $person2_id);
    }
    
    /**
     * Find relationship path using BFS
     */
    private function find_relationship_path($start_id, $end_id, $max_depth = 15) {
        global $wpdb;
        
        $queue = array(array(
            'path' => array(),
            'current' => $start_id,
        ));
        $visited = array($start_id => true);
        
        while (!empty($queue)) {
            $item = array_shift($queue);
            $path = $item['path'];
            $current = $item['current'];
            
            if (count($path) > $max_depth) {
                continue;
            }
            
            $relationships = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table} 
                 WHERE person1_id = %d OR person2_id = %d",
                $current, $current
            ));
            
            foreach ($relationships as $rel) {
                $other_id = ($rel->person1_id == $current) ? $rel->person2_id : $rel->person1_id;
                
                if (isset($visited[$other_id])) {
                    continue;
                }
                
                $step = array(
                    'from' => $current,
                    'to' => $other_id,
                    'type' => $rel->relationship_type,
                    'direction' => ($rel->person1_id == $current) ? 'child' : 'parent',
                );
                
                if ($rel->relationship_type === 'spouse') {
                    $step['direction'] = 'spouse';
                }
                
                $new_path = array_merge($path, array($step));
                
                if ($other_id == $end_id) {
                    return $new_path;
                }
                
                $visited[$other_id] = true;
                $queue[] = array(
                    'path' => $new_path,
                    'current' => $other_id,
                );
            }
        }
        
        return null;
    }
    
    /**
     * Convert path to relationship name
     */
    private function path_to_relationship_name($path, $person1_id, $person2_id) {
        if (empty($path)) {
            return __('Unknown', 'family-tree-connect');
        }
        
        // Count generations up and down
        $ups = 0;
        $downs = 0;
        $spouse_steps = 0;
        
        foreach ($path as $step) {
            if ($step['direction'] === 'parent') {
                $ups++;
            } elseif ($step['direction'] === 'child') {
                $downs++;
            } elseif ($step['direction'] === 'spouse') {
                $spouse_steps++;
            }
        }
        
        // Get gender of target person
        $person2 = FTC_Core::get_instance()->person->get($person2_id);
        $gender = $person2 ? $person2->gender : 'unknown';
        
        // Direct relationships
        if ($spouse_steps === 1 && $ups === 0 && $downs === 0) {
            return $gender === 'female' ? __('Wife', 'family-tree-connect') : __('Husband', 'family-tree-connect');
        }
        
        if ($ups === 1 && $downs === 0 && $spouse_steps === 0) {
            return $gender === 'female' ? __('Mother', 'family-tree-connect') : __('Father', 'family-tree-connect');
        }
        
        if ($ups === 0 && $downs === 1 && $spouse_steps === 0) {
            return $gender === 'female' ? __('Daughter', 'family-tree-connect') : __('Son', 'family-tree-connect');
        }
        
        if ($ups === 2 && $downs === 0 && $spouse_steps === 0) {
            return $gender === 'female' ? __('Grandmother', 'family-tree-connect') : __('Grandfather', 'family-tree-connect');
        }
        
        if ($ups === 0 && $downs === 2 && $spouse_steps === 0) {
            return $gender === 'female' ? __('Granddaughter', 'family-tree-connect') : __('Grandson', 'family-tree-connect');
        }
        
        if ($ups === 3 && $downs === 0 && $spouse_steps === 0) {
            return $gender === 'female' ? __('Great-grandmother', 'family-tree-connect') : __('Great-grandfather', 'family-tree-connect');
        }
        
        if ($ups === 0 && $downs === 3 && $spouse_steps === 0) {
            return $gender === 'female' ? __('Great-granddaughter', 'family-tree-connect') : __('Great-grandson', 'family-tree-connect');
        }
        
        // Siblings
        if ($ups === 1 && $downs === 1 && $spouse_steps === 0) {
            return $gender === 'female' ? __('Sister', 'family-tree-connect') : __('Brother', 'family-tree-connect');
        }
        
        // Aunts/Uncles
        if ($ups === 2 && $downs === 1 && $spouse_steps === 0) {
            return $gender === 'female' ? __('Aunt', 'family-tree-connect') : __('Uncle', 'family-tree-connect');
        }
        
        // Nieces/Nephews
        if ($ups === 1 && $downs === 2 && $spouse_steps === 0) {
            return $gender === 'female' ? __('Niece', 'family-tree-connect') : __('Nephew', 'family-tree-connect');
        }
        
        // Cousins
        if ($ups === 2 && $downs === 2 && $spouse_steps === 0) {
            return __('First Cousin', 'family-tree-connect');
        }
        
        if ($ups === 3 && $downs === 3 && $spouse_steps === 0) {
            return __('Second Cousin', 'family-tree-connect');
        }
        
        // In-laws (spouse relationships)
        if ($spouse_steps === 1) {
            if ($ups === 1 && $downs === 0) {
                return $gender === 'female' ? __('Mother-in-law', 'family-tree-connect') : __('Father-in-law', 'family-tree-connect');
            }
            if ($ups === 0 && $downs === 1) {
                return $gender === 'female' ? __('Daughter-in-law', 'family-tree-connect') : __('Son-in-law', 'family-tree-connect');
            }
            if ($ups === 1 && $downs === 1) {
                return $gender === 'female' ? __('Sister-in-law', 'family-tree-connect') : __('Brother-in-law', 'family-tree-connect');
            }
        }
        
        // Cousins with removal
        if ($ups > 2 && $downs > 2 && $spouse_steps === 0) {
            $min = min($ups, $downs);
            $diff = abs($ups - $downs);
            
            $cousin_num = $this->get_cousin_ordinal($min - 1);
            
            if ($diff === 0) {
                return sprintf(__('%s Cousin', 'family-tree-connect'), $cousin_num);
            } else {
                $removed = $diff === 1 ? __('once removed', 'family-tree-connect') : 
                           sprintf(__('%d times removed', 'family-tree-connect'), $diff);
                return sprintf(__('%s Cousin, %s', 'family-tree-connect'), $cousin_num, $removed);
            }
        }
        
        // Great-great ancestors/descendants
        if ($ups > 3 && $downs === 0 && $spouse_steps === 0) {
            $greats = str_repeat('Great-', $ups - 2);
            return $gender === 'female' 
                ? sprintf(__('%sGrandmother', 'family-tree-connect'), $greats)
                : sprintf(__('%sGrandfather', 'family-tree-connect'), $greats);
        }
        
        if ($ups === 0 && $downs > 3 && $spouse_steps === 0) {
            $greats = str_repeat('Great-', $downs - 2);
            return $gender === 'female' 
                ? sprintf(__('%sGranddaughter', 'family-tree-connect'), $greats)
                : sprintf(__('%sGrandson', 'family-tree-connect'), $greats);
        }
        
        // Generic distant relative
        return __('Distant relative', 'family-tree-connect');
    }
    
    /**
     * Get ordinal for cousin number
     */
    private function get_cousin_ordinal($num) {
        $ordinals = array(
            1 => __('First', 'family-tree-connect'),
            2 => __('Second', 'family-tree-connect'),
            3 => __('Third', 'family-tree-connect'),
            4 => __('Fourth', 'family-tree-connect'),
            5 => __('Fifth', 'family-tree-connect'),
            6 => __('Sixth', 'family-tree-connect'),
            7 => __('Seventh', 'family-tree-connect'),
            8 => __('Eighth', 'family-tree-connect'),
        );
        
        return isset($ordinals[$num]) ? $ordinals[$num] : sprintf('%dth', $num);
    }
    
    /**
     * Get all direct relationships for a person
     */
    public function get_direct_relationships($person_id) {
        global $wpdb;
        
        $relationships = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, 
                    CASE WHEN r.person1_id = %d THEN r.person2_id ELSE r.person1_id END as other_person_id,
                    CASE WHEN r.person1_id = %d THEN 'from' ELSE 'to' END as direction
             FROM {$this->table} r
             WHERE r.person1_id = %d OR r.person2_id = %d",
            $person_id, $person_id, $person_id, $person_id
        ));
        
        foreach ($relationships as &$rel) {
            $rel->other_person = FTC_Core::get_instance()->person->get($rel->other_person_id);
        }
        
        return $relationships;
    }
    
    /**
     * Delete relationship
     */
    public function delete($relationship_id) {
        global $wpdb;
        
        return $wpdb->delete($this->table, array('id' => $relationship_id));
    }
    
    /**
     * Get ancestors of a person
     */
    public function get_ancestors($person_id, $max_generations = 10) {
        $ancestors = array();
        $this->collect_ancestors($person_id, 0, $max_generations, $ancestors);
        return $ancestors;
    }
    
    /**
     * Recursively collect ancestors
     */
    private function collect_ancestors($person_id, $generation, $max_generations, &$ancestors) {
        if ($generation >= $max_generations) {
            return;
        }
        
        $parents = FTC_Core::get_instance()->person->get_parents($person_id);
        
        foreach (array('father', 'mother') as $parent_type) {
            if ($parents[$parent_type]) {
                if (!isset($ancestors[$generation + 1])) {
                    $ancestors[$generation + 1] = array();
                }
                $ancestors[$generation + 1][] = $parents[$parent_type];
                $this->collect_ancestors($parents[$parent_type]->id, $generation + 1, $max_generations, $ancestors);
            }
        }
    }
    
    /**
     * Get descendants of a person
     */
    public function get_descendants($person_id, $max_generations = 10) {
        $descendants = array();
        $this->collect_descendants($person_id, 0, $max_generations, $descendants);
        return $descendants;
    }
    
    /**
     * Recursively collect descendants
     */
    private function collect_descendants($person_id, $generation, $max_generations, &$descendants) {
        if ($generation >= $max_generations) {
            return;
        }
        
        $children = FTC_Core::get_instance()->person->get_children($person_id);
        
        foreach ($children as $child) {
            if (!isset($descendants[$generation + 1])) {
                $descendants[$generation + 1] = array();
            }
            $descendants[$generation + 1][] = $child;
            $this->collect_descendants($child->id, $generation + 1, $max_generations, $descendants);
        }
    }
}
