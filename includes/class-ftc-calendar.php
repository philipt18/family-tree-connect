<?php
/**
 * Calendar management class with multi-calendar support
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Calendar {
    
    /**
     * Get supported calendar systems
     */
    public static function get_calendar_systems() {
        return apply_filters('ftc_calendar_systems', array(
            'gregorian' => array(
                'name' => __('Gregorian', 'family-tree-connect'),
                'direction' => 'ltr',
            ),
            'jewish' => array(
                'name' => __('Jewish (Hebrew)', 'family-tree-connect'),
                'direction' => 'rtl',
            ),
            'julian' => array(
                'name' => __('Julian', 'family-tree-connect'),
                'direction' => 'ltr',
            ),
            'islamic' => array(
                'name' => __('Islamic (Hijri)', 'family-tree-connect'),
                'direction' => 'rtl',
            ),
        ));
    }
    
    /**
     * Get Jewish month names
     */
    public static function get_jewish_months() {
        return array(
            1 => array('name' => 'Nisan', 'hebrew' => 'ניסן'),
            2 => array('name' => 'Iyar', 'hebrew' => 'אייר'),
            3 => array('name' => 'Sivan', 'hebrew' => 'סיון'),
            4 => array('name' => 'Tammuz', 'hebrew' => 'תמוז'),
            5 => array('name' => 'Av', 'hebrew' => 'אב'),
            6 => array('name' => 'Elul', 'hebrew' => 'אלול'),
            7 => array('name' => 'Tishrei', 'hebrew' => 'תשרי'),
            8 => array('name' => 'Cheshvan', 'hebrew' => 'חשון'),
            9 => array('name' => 'Kislev', 'hebrew' => 'כסלו'),
            10 => array('name' => 'Tevet', 'hebrew' => 'טבת'),
            11 => array('name' => 'Shevat', 'hebrew' => 'שבט'),
            12 => array('name' => 'Adar', 'hebrew' => 'אדר'),
            13 => array('name' => 'Adar II', 'hebrew' => 'אדר ב׳'),
        );
    }
    
    /**
     * Convert date to Gregorian
     */
    public static function to_gregorian($date, $calendar = 'gregorian') {
        if (empty($date)) {
            return null;
        }
        
        if ($calendar === 'gregorian') {
            return $date;
        }
        
        switch ($calendar) {
            case 'jewish':
                return self::jewish_to_gregorian($date);
            case 'julian':
                return self::julian_to_gregorian($date);
            case 'islamic':
                return self::islamic_to_gregorian($date);
            default:
                return $date;
        }
    }
    
    /**
     * Convert Gregorian date to another calendar
     */
    public static function from_gregorian($date, $calendar = 'gregorian') {
        if (empty($date)) {
            return null;
        }
        
        if ($calendar === 'gregorian') {
            return $date;
        }
        
        switch ($calendar) {
            case 'jewish':
                return self::gregorian_to_jewish($date);
            case 'julian':
                return self::gregorian_to_julian($date);
            case 'islamic':
                return self::gregorian_to_islamic($date);
            default:
                return $date;
        }
    }
    
    /**
     * Convert Jewish date to Gregorian
     */
    public static function jewish_to_gregorian($date) {
        // Parse Jewish date (format: day month year, e.g., "15 Nisan 5784")
        if (!preg_match('/^(\d+)\s+(\w+)\s+(\d+)$/i', trim($date), $matches)) {
            // Try numeric format: YYYY-MM-DD
            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', trim($date), $matches)) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];
            } else {
                return $date;
            }
        } else {
            $day = (int) $matches[1];
            $month_name = $matches[2];
            $year = (int) $matches[3];
            
            $month = self::jewish_month_to_number($month_name);
            if (!$month) {
                return $date;
            }
        }
        
        // Use algorithm to convert Jewish to Julian Day Number
        $jd = self::jewish_to_jd($year, $month, $day);
        
        // Convert Julian Day to Gregorian
        return self::jd_to_gregorian($jd);
    }
    
    /**
     * Convert Gregorian to Jewish date
     */
    public static function gregorian_to_jewish($date) {
        // Parse Gregorian date
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return $date;
        }
        
        $year = (int) date('Y', $timestamp);
        $month = (int) date('n', $timestamp);
        $day = (int) date('j', $timestamp);
        
        // Convert to Julian Day
        $jd = self::gregorian_to_jd($year, $month, $day);
        
        // Convert Julian Day to Jewish
        $jewish = self::jd_to_jewish($jd);
        
        $months = self::get_jewish_months();
        $month_name = isset($months[$jewish['month']]) ? $months[$jewish['month']]['name'] : $jewish['month'];
        
        return sprintf('%d %s %d', $jewish['day'], $month_name, $jewish['year']);
    }
    
    /**
     * Convert Jewish month name to number
     */
    private static function jewish_month_to_number($name) {
        $months = array(
            'nisan' => 1, 'iyar' => 2, 'sivan' => 3, 'tammuz' => 4,
            'av' => 5, 'elul' => 6, 'tishrei' => 7, 'tishri' => 7,
            'cheshvan' => 8, 'heshvan' => 8, 'marcheshvan' => 8,
            'kislev' => 9, 'tevet' => 10, 'teves' => 10,
            'shevat' => 11, 'shvat' => 11, 'adar' => 12,
            'adar i' => 12, 'adar ii' => 13, 'adar 2' => 13,
        );
        
        return isset($months[strtolower($name)]) ? $months[strtolower($name)] : null;
    }
    
    /**
     * Check if Jewish year is leap year
     */
    private static function is_jewish_leap_year($year) {
        return ((7 * $year + 1) % 19) < 7;
    }
    
    /**
     * Get number of days in Jewish month
     */
    private static function jewish_month_days($year, $month) {
        // Months with 30 days: Nisan, Sivan, Av, Tishrei, Shevat, Adar I (in leap years)
        // Months with 29 days: Iyar, Tammuz, Elul, Tevet, Adar (or Adar II in leap years)
        // Cheshvan: 29 or 30, Kislev: 29 or 30
        
        $long_months = array(1, 3, 5, 7, 11);
        $short_months = array(2, 4, 6, 10);
        
        if (in_array($month, $long_months)) {
            return 30;
        }
        
        if (in_array($month, $short_months)) {
            return 29;
        }
        
        if ($month === 12) {
            return self::is_jewish_leap_year($year) ? 30 : 29;
        }
        
        if ($month === 13) {
            return 29;
        }
        
        // Cheshvan (8) and Kislev (9) vary
        $year_type = self::get_jewish_year_type($year);
        
        if ($month === 8) {
            return $year_type === 'complete' ? 30 : 29;
        }
        
        if ($month === 9) {
            return $year_type === 'deficient' ? 29 : 30;
        }
        
        return 30;
    }
    
    /**
     * Get Jewish year type (deficient, regular, complete)
     */
    private static function get_jewish_year_type($year) {
        $days = self::jewish_year_days($year);
        $leap = self::is_jewish_leap_year($year);
        
        if ($leap) {
            if ($days === 383) return 'deficient';
            if ($days === 384) return 'regular';
            return 'complete';
        } else {
            if ($days === 353) return 'deficient';
            if ($days === 354) return 'regular';
            return 'complete';
        }
    }
    
    /**
     * Calculate days in Jewish year
     */
    private static function jewish_year_days($year) {
        return self::jewish_to_jd($year + 1, 7, 1) - self::jewish_to_jd($year, 7, 1);
    }
    
    /**
     * Convert Jewish date to Julian Day Number
     */
    private static function jewish_to_jd($year, $month, $day) {
        // Epoch: Julian day of 1 Tishrei 1 (year 1)
        $epoch = 347995.5;
        
        // Calculate the number of months from 1 Tishrei year 1 to this date
        $months = 0;
        
        // Years from epoch
        for ($y = 1; $y < $year; $y++) {
            $months += self::is_jewish_leap_year($y) ? 13 : 12;
        }
        
        // Months in current year (from Tishrei to current month)
        if ($month >= 7) {
            // Tishrei (7) to current month
            for ($m = 7; $m < $month; $m++) {
                $months++;
            }
        } else {
            // Tishrei to Adar/Adar II, then Nisan to current
            for ($m = 7; $m <= (self::is_jewish_leap_year($year) ? 13 : 12); $m++) {
                $months++;
            }
            for ($m = 1; $m < $month; $m++) {
                $months++;
            }
        }
        
        // Calculate total days
        $days = 0;
        
        // Years
        for ($y = 1; $y < $year; $y++) {
            $days += self::jewish_year_days($y);
        }
        
        // Months in current year from Tishrei
        $month_order = array(7, 8, 9, 10, 11, 12);
        if (self::is_jewish_leap_year($year)) {
            $month_order[] = 13;
        }
        $month_order = array_merge($month_order, array(1, 2, 3, 4, 5, 6));
        
        foreach ($month_order as $m) {
            if ($m === $month) {
                break;
            }
            $days += self::jewish_month_days($year, $m);
        }
        
        $days += $day - 1;
        
        return $epoch + $days;
    }
    
    /**
     * Convert Julian Day Number to Jewish date
     */
    private static function jd_to_jewish($jd) {
        $epoch = 347995.5;
        $days = floor($jd - $epoch);
        
        // Approximate year
        $year = (int) floor(($days * 19) / 6939.6) + 1;
        
        // Adjust year
        while (self::jewish_to_jd($year + 1, 7, 1) <= $jd) {
            $year++;
        }
        
        // Find month
        $month_order = array(7, 8, 9, 10, 11, 12);
        if (self::is_jewish_leap_year($year)) {
            $month_order[] = 13;
        }
        $month_order = array_merge($month_order, array(1, 2, 3, 4, 5, 6));
        
        $month = 7;
        foreach ($month_order as $m) {
            $month_start = self::jewish_to_jd($year, $m, 1);
            $month_days = self::jewish_month_days($year, $m);
            
            if ($jd >= $month_start && $jd < $month_start + $month_days) {
                $month = $m;
                break;
            }
        }
        
        $day = floor($jd - self::jewish_to_jd($year, $month, 1)) + 1;
        
        return array('year' => $year, 'month' => $month, 'day' => (int) $day);
    }
    
    /**
     * Convert Gregorian to Julian Day
     */
    private static function gregorian_to_jd($year, $month, $day) {
        $a = floor((14 - $month) / 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;
        
        return $day + floor((153 * $m + 2) / 5) + 365 * $y + floor($y / 4) - floor($y / 100) + floor($y / 400) - 32045;
    }
    
    /**
     * Convert Julian Day to Gregorian
     */
    private static function jd_to_gregorian($jd) {
        $jd = floor($jd) + 0.5;
        
        $a = floor($jd + 32044);
        $b = floor((4 * $a + 3) / 146097);
        $c = $a - floor(146097 * $b / 4);
        $d = floor((4 * $c + 3) / 1461);
        $e = $c - floor(1461 * $d / 4);
        $m = floor((5 * $e + 2) / 153);
        
        $day = $e - floor((153 * $m + 2) / 5) + 1;
        $month = $m + 3 - 12 * floor($m / 10);
        $year = 100 * $b + $d - 4800 + floor($m / 10);
        
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
    
    /**
     * Convert Julian to Gregorian
     */
    public static function julian_to_gregorian($date) {
        $timestamp = strtotime($date);
        if (!$timestamp) return $date;
        
        $year = (int) date('Y', $timestamp);
        $month = (int) date('n', $timestamp);
        $day = (int) date('j', $timestamp);
        
        // Julian to JD
        $a = floor((14 - $month) / 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;
        $jd = $day + floor((153 * $m + 2) / 5) + 365 * $y + floor($y / 4) - 32083;
        
        return self::jd_to_gregorian($jd);
    }
    
    /**
     * Convert Gregorian to Julian
     */
    public static function gregorian_to_julian($date) {
        $timestamp = strtotime($date);
        if (!$timestamp) return $date;
        
        $year = (int) date('Y', $timestamp);
        $month = (int) date('n', $timestamp);
        $day = (int) date('j', $timestamp);
        
        $jd = self::gregorian_to_jd($year, $month, $day);
        
        // JD to Julian
        $b = 0;
        $c = floor($jd + 32082);
        $d = floor((4 * $c + 3) / 1461);
        $e = $c - floor(1461 * $d / 4);
        $m = floor((5 * $e + 2) / 153);
        
        $day = $e - floor((153 * $m + 2) / 5) + 1;
        $month = $m + 3 - 12 * floor($m / 10);
        $year = $d - 4800 + floor($m / 10);
        
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
    
    /**
     * Islamic/Hijri calendar conversions (simplified)
     */
    public static function islamic_to_gregorian($date) {
        // Basic implementation - more accurate algorithm would be needed for production
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', trim($date), $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            
            // Approximate conversion
            $jd = floor((11 * $year + 3) / 30) + 354 * $year + 30 * $month
                - floor(($month - 1) / 2) + $day + 1948440 - 385;
            
            return self::jd_to_gregorian($jd);
        }
        
        return $date;
    }
    
    public static function gregorian_to_islamic($date) {
        $timestamp = strtotime($date);
        if (!$timestamp) return $date;
        
        $year = (int) date('Y', $timestamp);
        $month = (int) date('n', $timestamp);
        $day = (int) date('j', $timestamp);
        
        $jd = self::gregorian_to_jd($year, $month, $day);
        
        // Approximate conversion
        $l = floor($jd - 1948440 + 10632);
        $n = floor(($l - 1) / 10631);
        $l = $l - 10631 * $n + 354;
        $j = floor((10985 - $l) / 5316) * floor((50 * $l) / 17719) 
           + floor($l / 5670) * floor((43 * $l) / 15238);
        $l = $l - floor((30 - $j) / 15) * floor((17719 * $j) / 50) 
           - floor($j / 16) * floor((15238 * $j) / 43) + 29;
        $m = floor((24 * $l) / 709);
        $d = $l - floor((709 * $m) / 24);
        $y = 30 * $n + $j - 30;
        
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
    
    /**
     * Format date for display
     */
    public static function format_date($date, $calendar = 'gregorian', $approximate = false) {
        if (empty($date)) {
            return '';
        }
        
        $prefix = $approximate ? __('about ', 'family-tree-connect') : '';
        
        if ($calendar === 'gregorian') {
            $timestamp = strtotime($date);
            if ($timestamp) {
                return $prefix . date_i18n(get_option('date_format'), $timestamp);
            }
            return $prefix . $date;
        }
        
        // For other calendars, return the date as stored
        $calendars = self::get_calendar_systems();
        $calendar_name = isset($calendars[$calendar]) ? $calendars[$calendar]['name'] : $calendar;
        
        return $prefix . $date . ' (' . $calendar_name . ')';
    }
    
    /**
     * Parse date input
     */
    public static function parse_date($input, $calendar = 'gregorian') {
        if (empty($input)) {
            return null;
        }
        
        // Clean input
        $input = trim($input);
        
        // Check for "about" prefix
        $approximate = false;
        if (preg_match('/^(about|circa|ca\.?|c\.?|~|≈)\s*/i', $input, $matches)) {
            $approximate = true;
            $input = substr($input, strlen($matches[0]));
        }
        
        // Try to parse various date formats
        $date = null;
        
        // ISO format: YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $input)) {
            $date = $input;
        }
        // Year only
        elseif (preg_match('/^(\d{4})$/', $input)) {
            $date = $input . '-01-01';
            $approximate = true;
        }
        // Month and year: January 1990
        elseif ($timestamp = strtotime($input)) {
            $date = date('Y-m-d', $timestamp);
        }
        
        return array(
            'date' => $date ?: $input,
            'approximate' => $approximate,
        );
    }
}
