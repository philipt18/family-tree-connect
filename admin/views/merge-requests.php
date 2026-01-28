<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Merge Requests', 'family-tree-connect'); ?></h1>

    <?php if (empty($requests)) : ?>
        <p><?php esc_html_e('No merge requests found.', 'family-tree-connect'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Requester', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Target User', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Message', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Status', 'family-tree-connect'); ?></th>
                    <th><?php esc_html_e('Date', 'family-tree-connect'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request) : ?>
                    <tr>
                        <td><?php echo esc_html($request->requester_name ?? __('Unknown', 'family-tree-connect')); ?></td>
                        <td><?php echo esc_html($request->target_name ?? __('Unknown', 'family-tree-connect')); ?></td>
                        <td><?php echo esc_html($request->message ? wp_trim_words($request->message, 15) : '—'); ?></td>
                        <td>
                            <?php
                            $status_labels = array(
                                'pending'   => __('Pending', 'family-tree-connect'),
                                'approved'  => __('Approved', 'family-tree-connect'),
                                'rejected'  => __('Rejected', 'family-tree-connect'),
                                'cancelled' => __('Cancelled', 'family-tree-connect'),
                            );
                            $status = $request->status ?? 'pending';
                            echo esc_html($status_labels[$status] ?? ucfirst($status));
                            ?>
                        </td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($request->created_at))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
