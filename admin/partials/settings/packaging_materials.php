<?php
defined('ABSPATH') || die('Restricted Access');
?>
</tbody>
</table>
<div class="forminp-lpc_products">
    <?php
    echo wp_kses_post(
        sprintf(
            __(
                'For your postage needs, order your labels in rolls or boxes directly from your dedicated space %s, and enjoy the best prices and quality standards on the market.',
                'wc_colissimo'
            ),
            '<a href="' . esc_url($args['url']) . '" target="_blank">' . __('Colissimo Box / Labels and packagings', 'wc_colissimo') . '</a>'
        )
    );
    ?>
</div>
<table class="form-table">
	<tbody>
