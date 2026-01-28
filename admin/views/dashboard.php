<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Family Tree Connect', 'family-tree-connect'); ?></h1>

    <div id="ftc-dashboard-widgets" style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">

        <div class="card" style="max-width: 300px; padding: 20px;">
            <h2><?php esc_html_e('Persons', 'family-tree-connect'); ?></h2>
            <p class="ftc-stat" style="font-size: 36px; font-weight: bold; margin: 10px 0;">
                <?php echo esc_html(number_format_i18n($stats['persons'])); ?>
            </p>
        </div>

        <div class="card" style="max-width: 300px; padding: 20px;">
            <h2><?php esc_html_e('Families', 'family-tree-connect'); ?></h2>
            <p class="ftc-stat" style="font-size: 36px; font-weight: bold; margin: 10px 0;">
                <?php echo esc_html(number_format_i18n($stats['families'])); ?>
            </p>
        </div>

        <div class="card" style="max-width: 300px; padding: 20px;">
            <h2><?php esc_html_e('Trees', 'family-tree-connect'); ?></h2>
            <p class="ftc-stat" style="font-size: 36px; font-weight: bold; margin: 10px 0;">
                <?php echo esc_html(number_format_i18n($stats['trees'])); ?>
            </p>
        </div>

        <div class="card" style="max-width: 300px; padding: 20px;">
            <h2><?php esc_html_e('Media', 'family-tree-connect'); ?></h2>
            <p class="ftc-stat" style="font-size: 36px; font-weight: bold; margin: 10px 0;">
                <?php echo esc_html(number_format_i18n($stats['media'])); ?>
            </p>
        </div>

        <div class="card" style="max-width: 300px; padding: 20px;">
            <h2><?php esc_html_e('Places', 'family-tree-connect'); ?></h2>
            <p class="ftc-stat" style="font-size: 36px; font-weight: bold; margin: 10px 0;">
                <?php echo esc_html(number_format_i18n($stats['places'])); ?>
            </p>
        </div>

        <div class="card" style="max-width: 300px; padding: 20px;">
            <h2><?php esc_html_e('Pending Merges', 'family-tree-connect'); ?></h2>
            <p class="ftc-stat" style="font-size: 36px; font-weight: bold; margin: 10px 0;">
                <?php echo esc_html(number_format_i18n($stats['pending_merges'])); ?>
            </p>
            <?php if ($stats['pending_merges'] > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ftc-merge-requests')); ?>">
                    <?php esc_html_e('Review merge requests', 'family-tree-connect'); ?> &rarr;
                </a>
            <?php endif; ?>
        </div>

    </div>
</div>
