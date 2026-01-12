<?php
defined('ABSPATH') || die('Restricted Access');
?>
<div class="notice notice-<?php echo esc_attr($args['type']); ?> is-dismissible">
    <?php echo esc_html($args['message']); ?>
</div>
