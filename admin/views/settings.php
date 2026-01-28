<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Family Tree Connect Settings', 'family-tree-connect'); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields('ftc_options');
        do_settings_sections('ftc-settings');
        submit_button();
        ?>
    </form>
</div>
