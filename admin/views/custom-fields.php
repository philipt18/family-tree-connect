<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Custom Fields', 'family-tree-connect'); ?></h1>

    <?php if (empty($fields)) : ?>
        <p><?php esc_html_e('No custom fields have been created yet.', 'family-tree-connect'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Label', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Field Name', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Type', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Applies To', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Global', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Order', 'family-tree-connect'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fields as $field) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($field->field_label); ?></strong></td>
                        <td><code><?php echo esc_html($field->field_name); ?></code></td>
                        <td><?php echo esc_html(ucfirst($field->field_type)); ?></td>
                        <td><?php echo esc_html(ucfirst($field->applies_to)); ?></td>
                        <td><?php echo $field->is_global ? esc_html__('Yes', 'family-tree-connect') : esc_html__('No', 'family-tree-connect'); ?></td>
                        <td><?php echo esc_html($field->sort_order); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
