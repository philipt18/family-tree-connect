<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Places', 'family-tree-connect'); ?></h1>

    <?php if (empty($places)) : ?>
        <p><?php esc_html_e('No places have been added yet.', 'family-tree-connect'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Type', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('City', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('State/Province', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Country', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Coordinates', 'family-tree-connect'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($places as $place) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($place->name); ?></strong></td>
                        <td><?php echo esc_html($place->place_type ? ucfirst($place->place_type) : '—'); ?></td>
                        <td><?php echo esc_html($place->city ?? '—'); ?></td>
                        <td><?php echo esc_html($place->state_province ?? '—'); ?></td>
                        <td><?php echo esc_html($place->country ?? '—'); ?></td>
                        <td>
                            <?php if ($place->latitude && $place->longitude) : ?>
                                <?php echo esc_html($place->latitude . ', ' . $place->longitude); ?>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
