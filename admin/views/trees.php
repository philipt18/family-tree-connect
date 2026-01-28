<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Trees', 'family-tree-connect'); ?></h1>

    <?php if (empty($trees)) : ?>
        <p><?php esc_html_e('No family trees have been created yet.', 'family-tree-connect'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Owner', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Persons', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Privacy', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Created', 'family-tree-connect'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trees as $tree) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($tree->name); ?></strong>
                            <?php if (!empty($tree->description)) : ?>
                                <br><span class="description"><?php echo esc_html($tree->description); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($tree->owner_name ?? __('Unknown', 'family-tree-connect')); ?></td>
                        <td><?php echo esc_html(number_format_i18n($tree->person_count)); ?></td>
                        <td><?php echo esc_html(ucfirst($tree->privacy)); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($tree->created_at))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
