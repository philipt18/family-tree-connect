<?php
/**
 * Export functionality class
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Export {
    
    /**
     * Export chart as PDF
     */
    public function export_chart_pdf($person_id, $args = array()) {
        $chart = FTC_Core::get_instance()->chart;
        $chart_data = $chart->generate($person_id, $args);
        
        if (!$chart_data) {
            return new WP_Error('no_data', __('No chart data available.', 'family-tree-connect'));
        }
        
        // Generate SVG
        $svg = $chart->render_svg($chart_data, array(
            'width' => 1200,
            'height' => 800,
        ));
        
        // Convert SVG to PDF using either:
        // 1. Client-side JavaScript (jsPDF + svg2pdf.js)
        // 2. Server-side library (if available)
        
        // For server-side, we'll return the SVG and let the client handle PDF conversion
        return array(
            'svg' => $svg,
            'chart_data' => $chart_data,
            'filename' => $this->generate_filename($person_id, 'pdf'),
        );
    }
    
    /**
     * Export person data as JSON
     */
    public function export_person_json($person_id) {
        $person = FTC_Core::get_instance()->person->get($person_id);
        if (!$person) {
            return new WP_Error('not_found', __('Person not found.', 'family-tree-connect'));
        }
        
        $data = array(
            'person' => $person,
            'parents' => FTC_Core::get_instance()->person->get_parents($person_id),
            'siblings' => FTC_Core::get_instance()->person->get_siblings($person_id),
            'spouses' => FTC_Core::get_instance()->person->get_spouses($person_id),
            'children' => FTC_Core::get_instance()->person->get_children($person_id),
            'events' => FTC_Core::get_instance()->event->get_by_person($person_id),
            'media' => FTC_Core::get_instance()->media->get_by_person($person_id),
        );
        
        return $data;
    }
    
    /**
     * Export tree as GEDCOM
     */
    public function export_gedcom($tree_id) {
        global $wpdb;
        $tables = FTC_Database::get_table_names();
        
        $tree = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['trees']} WHERE id = %d",
            $tree_id
        ));
        
        if (!$tree) {
            return new WP_Error('not_found', __('Tree not found.', 'family-tree-connect'));
        }
        
        $persons = FTC_Core::get_instance()->person->get_by_tree($tree_id, array('limit' => 10000));
        
        // Get all families
        $family_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT f.id FROM {$tables['families']} f
             INNER JOIN {$tables['tree_persons']} tp ON (f.spouse1_id = tp.person_id OR f.spouse2_id = tp.person_id)
             WHERE tp.tree_id = %d",
            $tree_id
        ));
        
        $families = array();
        foreach ($family_ids as $fid) {
            $families[] = FTC_Core::get_instance()->family->get($fid);
        }
        
        // Build GEDCOM
        $gedcom = $this->build_gedcom($tree, $persons, $families);
        
        return array(
            'content' => $gedcom,
            'filename' => sanitize_file_name($tree->name) . '.ged',
        );
    }
    
    /**
     * Build GEDCOM content
     */
    private function build_gedcom($tree, $persons, $families) {
        $lines = array();
        
        // Header
        $lines[] = '0 HEAD';
        $lines[] = '1 SOUR FamilyTreeConnect';
        $lines[] = '2 VERS ' . FTC_VERSION;
        $lines[] = '2 NAME Family Tree Connect';
        $lines[] = '1 DEST ANY';
        $lines[] = '1 DATE ' . date('d M Y');
        $lines[] = '1 SUBM @SUBM@';
        $lines[] = '1 GEDC';
        $lines[] = '2 VERS 5.5.1';
        $lines[] = '2 FORM LINEAGE-LINKED';
        $lines[] = '1 CHAR UTF-8';
        
        // Submitter
        $user = wp_get_current_user();
        $lines[] = '0 @SUBM@ SUBM';
        $lines[] = '1 NAME ' . $user->display_name;
        
        // Individuals
        foreach ($persons as $person) {
            $lines[] = "0 @I{$person->id}@ INDI";
            
            // Name
            $name = trim($person->first_name . ' /' . $person->surname . '/');
            $lines[] = "1 NAME $name";
            if ($person->first_name) {
                $lines[] = "2 GIVN {$person->first_name}";
            }
            if ($person->surname) {
                $lines[] = "2 SURN {$person->surname}";
            }
            if ($person->nickname) {
                $lines[] = "2 NICK {$person->nickname}";
            }
            
            // Gender
            $sex = 'U';
            if ($person->gender === 'male') $sex = 'M';
            elseif ($person->gender === 'female') $sex = 'F';
            $lines[] = "1 SEX $sex";
            
            // Birth
            if ($person->birth_date || $person->birth_location) {
                $lines[] = "1 BIRT";
                if ($person->birth_date) {
                    $date = $this->format_gedcom_date($person->birth_date, $person->birth_date_approximate);
                    $lines[] = "2 DATE $date";
                }
                if ($person->birth_location) {
                    $lines[] = "2 PLAC {$person->birth_location}";
                }
            }
            
            // Death
            if ($person->death_date || $person->death_location) {
                $lines[] = "1 DEAT";
                if ($person->death_date) {
                    $date = $this->format_gedcom_date($person->death_date, $person->death_date_approximate);
                    $lines[] = "2 DATE $date";
                }
                if ($person->death_location) {
                    $lines[] = "2 PLAC {$person->death_location}";
                }
            }
            
            // Occupation
            if ($person->occupation) {
                $lines[] = "1 OCCU {$person->occupation}";
            }
            
            // Notes
            if ($person->notes) {
                $lines[] = "1 NOTE " . $this->wrap_gedcom_text($person->notes);
            }
            
            // Family links will be added when processing families
        }
        
        // Families
        foreach ($families as $family) {
            if (!$family) continue;
            
            $lines[] = "0 @F{$family->id}@ FAM";
            
            if ($family->spouse1_id) {
                // Determine husband/wife based on gender
                $spouse1 = FTC_Core::get_instance()->person->get($family->spouse1_id);
                if ($spouse1 && $spouse1->gender === 'male') {
                    $lines[] = "1 HUSB @I{$family->spouse1_id}@";
                } else {
                    $lines[] = "1 WIFE @I{$family->spouse1_id}@";
                }
            }
            
            if ($family->spouse2_id) {
                $spouse2 = FTC_Core::get_instance()->person->get($family->spouse2_id);
                if ($spouse2 && $spouse2->gender === 'male') {
                    $lines[] = "1 HUSB @I{$family->spouse2_id}@";
                } else {
                    $lines[] = "1 WIFE @I{$family->spouse2_id}@";
                }
            }
            
            // Marriage
            if ($family->marriage_date || $family->marriage_location) {
                $lines[] = "1 MARR";
                if ($family->marriage_date) {
                    $date = $this->format_gedcom_date($family->marriage_date, $family->marriage_date_approximate);
                    $lines[] = "2 DATE $date";
                }
                if ($family->marriage_location) {
                    $lines[] = "2 PLAC {$family->marriage_location}";
                }
            }
            
            // Children
            if (!empty($family->children)) {
                foreach ($family->children as $child) {
                    $lines[] = "1 CHIL @I{$child->id}@";
                }
            }
        }
        
        // Trailer
        $lines[] = '0 TRLR';
        
        return implode("\r\n", $lines);
    }
    
    /**
     * Format date for GEDCOM
     */
    private function format_gedcom_date($date, $approximate = false) {
        if (empty($date)) return '';
        
        $prefix = $approximate ? 'ABT ' : '';
        
        // Try to parse the date
        $timestamp = strtotime($date);
        if ($timestamp) {
            return $prefix . strtoupper(date('d M Y', $timestamp));
        }
        
        return $prefix . $date;
    }
    
    /**
     * Wrap long text for GEDCOM
     */
    private function wrap_gedcom_text($text, $line_length = 248) {
        $text = str_replace(array("\r\n", "\r"), "\n", $text);
        $lines = explode("\n", $text);
        $result = array();
        
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                $result[] = $line;
            } else {
                $result[] = "\n2 CONT " . $line;
            }
        }
        
        return implode('', $result);
    }
    
    /**
     * Generate filename
     */
    private function generate_filename($person_id, $extension) {
        $person = FTC_Core::get_instance()->person->get($person_id);
        $name = $person ? sanitize_file_name($person->display_name) : 'family-tree';
        return $name . '-' . date('Y-m-d') . '.' . $extension;
    }
    
    /**
     * Import GEDCOM file
     */
    public function import_gedcom($file_path, $tree_id) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('File not found.', 'family-tree-connect'));
        }
        
        $content = file_get_contents($file_path);
        
        // Parse GEDCOM
        $parsed = $this->parse_gedcom($content);
        
        if (is_wp_error($parsed)) {
            return $parsed;
        }
        
        // Import individuals
        $person_map = array(); // GEDCOM ID => our ID
        
        foreach ($parsed['individuals'] as $ged_id => $data) {
            $person_data = array(
                'tree_id' => $tree_id,
                'first_name' => $data['first_name'] ?? '',
                'surname' => $data['surname'] ?? '',
                'nickname' => $data['nickname'] ?? '',
                'gender' => $data['gender'] ?? 'unknown',
                'birth_date' => $data['birth_date'] ?? null,
                'birth_location' => $data['birth_place'] ?? '',
                'death_date' => $data['death_date'] ?? null,
                'death_location' => $data['death_place'] ?? '',
                'occupation' => $data['occupation'] ?? '',
                'notes' => $data['notes'] ?? '',
            );
            
            $person_id = FTC_Core::get_instance()->person->create($person_data);
            
            if (!is_wp_error($person_id)) {
                $person_map[$ged_id] = $person_id;
            }
        }
        
        // Import families
        foreach ($parsed['families'] as $fam_data) {
            $spouse1_id = isset($fam_data['husband']) && isset($person_map[$fam_data['husband']]) 
                ? $person_map[$fam_data['husband']] : null;
            $spouse2_id = isset($fam_data['wife']) && isset($person_map[$fam_data['wife']]) 
                ? $person_map[$fam_data['wife']] : null;
            
            if (!$spouse1_id && !$spouse2_id) {
                continue;
            }
            
            $family_id = FTC_Core::get_instance()->family->create(array(
                'spouse1_id' => $spouse1_id,
                'spouse2_id' => $spouse2_id,
                'marriage_date' => $fam_data['marriage_date'] ?? null,
                'marriage_location' => $fam_data['marriage_place'] ?? '',
            ));
            
            if (!is_wp_error($family_id) && !empty($fam_data['children'])) {
                foreach ($fam_data['children'] as $child_ged_id) {
                    if (isset($person_map[$child_ged_id])) {
                        FTC_Core::get_instance()->family->add_child($family_id, $person_map[$child_ged_id]);
                    }
                }
            }
        }
        
        return array(
            'persons_imported' => count($person_map),
            'families_imported' => count($parsed['families']),
        );
    }
    
    /**
     * Parse GEDCOM content
     */
    private function parse_gedcom($content) {
        $lines = preg_split('/\r?\n/', $content);
        
        $individuals = array();
        $families = array();
        $current_record = null;
        $current_id = null;
        $current_type = null;
        $current_event = null;
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            if (!preg_match('/^(\d+)\s+(@\w+@\s+)?(\w+)(.*)$/', $line, $matches)) {
                continue;
            }
            
            $level = (int) $matches[1];
            $xref = trim($matches[2] ?? '');
            $tag = $matches[3];
            $value = trim($matches[4] ?? '');
            
            if ($level === 0) {
                // New record
                if ($xref && $tag === 'INDI') {
                    $current_type = 'INDI';
                    $current_id = $xref;
                    $individuals[$current_id] = array();
                } elseif ($xref && $tag === 'FAM') {
                    $current_type = 'FAM';
                    $current_id = $xref;
                    $families[$current_id] = array('children' => array());
                } else {
                    $current_type = null;
                    $current_id = null;
                }
                $current_event = null;
            } elseif ($current_type === 'INDI' && $current_id) {
                switch ($tag) {
                    case 'NAME':
                        if (preg_match('/^(.+?)\s*\/(.+?)\/$/', $value, $m)) {
                            $individuals[$current_id]['first_name'] = trim($m[1]);
                            $individuals[$current_id]['surname'] = trim($m[2]);
                        }
                        break;
                    case 'GIVN':
                        $individuals[$current_id]['first_name'] = $value;
                        break;
                    case 'SURN':
                        $individuals[$current_id]['surname'] = $value;
                        break;
                    case 'NICK':
                        $individuals[$current_id]['nickname'] = $value;
                        break;
                    case 'SEX':
                        $individuals[$current_id]['gender'] = $value === 'M' ? 'male' : ($value === 'F' ? 'female' : 'unknown');
                        break;
                    case 'BIRT':
                        $current_event = 'birth';
                        break;
                    case 'DEAT':
                        $current_event = 'death';
                        break;
                    case 'DATE':
                        if ($current_event) {
                            $individuals[$current_id][$current_event . '_date'] = $value;
                        }
                        break;
                    case 'PLAC':
                        if ($current_event) {
                            $individuals[$current_id][$current_event . '_place'] = $value;
                        }
                        break;
                    case 'OCCU':
                        $individuals[$current_id]['occupation'] = $value;
                        break;
                    case 'NOTE':
                        $individuals[$current_id]['notes'] = $value;
                        break;
                }
            } elseif ($current_type === 'FAM' && $current_id) {
                switch ($tag) {
                    case 'HUSB':
                        $families[$current_id]['husband'] = $value;
                        break;
                    case 'WIFE':
                        $families[$current_id]['wife'] = $value;
                        break;
                    case 'CHIL':
                        $families[$current_id]['children'][] = $value;
                        break;
                    case 'MARR':
                        $current_event = 'marriage';
                        break;
                    case 'DATE':
                        if ($current_event === 'marriage') {
                            $families[$current_id]['marriage_date'] = $value;
                        }
                        break;
                    case 'PLAC':
                        if ($current_event === 'marriage') {
                            $families[$current_id]['marriage_place'] = $value;
                        }
                        break;
                }
            }
        }
        
        return array(
            'individuals' => $individuals,
            'families' => $families,
        );
    }
}
