<?php
/**
 * Admin settings page
 *
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTC_Admin_Settings {

    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings() {
        // General section
        add_settings_section(
            'ftc_general_section',
            __('General Settings', 'family-tree-connect'),
            array($this, 'general_section_callback'),
            'ftc-settings'
        );

        add_settings_field(
            'default_calendar',
            __('Default Calendar', 'family-tree-connect'),
            array($this, 'calendar_field_callback'),
            'ftc-settings',
            'ftc_general_section'
        );

        add_settings_field(
            'default_privacy',
            __('Default Privacy', 'family-tree-connect'),
            array($this, 'privacy_field_callback'),
            'ftc-settings',
            'ftc_general_section'
        );

        // Chart section
        add_settings_section(
            'ftc_chart_section',
            __('Chart Settings', 'family-tree-connect'),
            array($this, 'chart_section_callback'),
            'ftc-settings'
        );

        add_settings_field(
            'default_chart_type',
            __('Default Chart Type', 'family-tree-connect'),
            array($this, 'chart_type_field_callback'),
            'ftc-settings',
            'ftc_chart_section'
        );

        add_settings_field(
            'default_chart_generations',
            __('Default Generations', 'family-tree-connect'),
            array($this, 'chart_generations_field_callback'),
            'ftc-settings',
            'ftc_chart_section'
        );

        // Features section
        add_settings_section(
            'ftc_features_section',
            __('Features', 'family-tree-connect'),
            array($this, 'features_section_callback'),
            'ftc-settings'
        );

        add_settings_field(
            'enable_facial_recognition',
            __('Facial Recognition', 'family-tree-connect'),
            array($this, 'facial_recognition_field_callback'),
            'ftc-settings',
            'ftc_features_section'
        );

        add_settings_field(
            'enable_email_notifications',
            __('Email Notifications', 'family-tree-connect'),
            array($this, 'email_notifications_field_callback'),
            'ftc-settings',
            'ftc_features_section'
        );
    }

    public function general_section_callback() {
        echo '<p>' . esc_html__('Configure general plugin settings.', 'family-tree-connect') . '</p>';
    }

    public function chart_section_callback() {
        echo '<p>' . esc_html__('Configure default chart display options.', 'family-tree-connect') . '</p>';
    }

    public function features_section_callback() {
        echo '<p>' . esc_html__('Enable or disable plugin features.', 'family-tree-connect') . '</p>';
    }

    public function calendar_field_callback() {
        $options = get_option('ftc_options', array());
        $current = $options['default_calendar'] ?? 'gregorian';
        $calendars = FTC_Calendar::get_calendar_systems();
        echo '<select name="ftc_options[default_calendar]">';
        foreach ($calendars as $key => $calendar) {
            $label = is_array($calendar) ? $calendar['name'] : $calendar;
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($key),
                selected($current, $key, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    public function privacy_field_callback() {
        $options = get_option('ftc_options', array());
        $current = $options['default_privacy'] ?? 'private';
        $choices = array(
            'public'  => __('Public', 'family-tree-connect'),
            'private' => __('Private', 'family-tree-connect'),
        );
        echo '<select name="ftc_options[default_privacy]">';
        foreach ($choices as $key => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($key),
                selected($current, $key, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    public function chart_type_field_callback() {
        $options = get_option('ftc_options', array());
        $current = $options['default_chart_type'] ?? 'ancestor';
        $chart_types = FTC_Chart::get_chart_types();
        echo '<select name="ftc_options[default_chart_type]">';
        foreach ($chart_types as $key => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($key),
                selected($current, $key, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    public function chart_generations_field_callback() {
        $options = get_option('ftc_options', array());
        $current = $options['default_chart_generations'] ?? 4;
        printf(
            '<input type="number" name="ftc_options[default_chart_generations]" value="%d" min="1" max="20" />',
            absint($current)
        );
    }

    public function facial_recognition_field_callback() {
        $options = get_option('ftc_options', array());
        $checked = !empty($options['enable_facial_recognition']);
        printf(
            '<label><input type="checkbox" name="ftc_options[enable_facial_recognition]" value="1" %s /> %s</label>',
            checked($checked, true, false),
            esc_html__('Enable client-side facial recognition for photo tagging', 'family-tree-connect')
        );
    }

    public function email_notifications_field_callback() {
        $options = get_option('ftc_options', array());
        $checked = !empty($options['enable_email_notifications']);
        printf(
            '<label><input type="checkbox" name="ftc_options[enable_email_notifications]" value="1" %s /> %s</label>',
            checked($checked, true, false),
            esc_html__('Send email notifications for merge requests and updates', 'family-tree-connect')
        );
    }
}
