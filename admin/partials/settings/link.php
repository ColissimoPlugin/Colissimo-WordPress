<tr>
	<th scope="row" class="titledesc">
        <?php if (!empty($args['label'])) { ?>
			<label><?php esc_html_e($args['label'], 'wc_colissimo'); ?></label>
        <?php } ?>
	</th>
	<td class="forminp forminp-<?php echo esc_attr(sanitize_title($args['type'])); ?>">
		<a class="<?php echo esc_attr($args['class']); ?>" href="<?php echo esc_url($args['url']); ?>" target="_blank">
			<?php esc_html_e($args['text'], 'wc_colissimo'); ?></a>
        <?php if (!empty($args['urlServices'])) { ?>
			|
			<a class="<?php echo esc_attr($args['class']); ?>" href="<?php echo esc_url($args['urlServices']); ?>" target="_blank">
                <?php esc_html_e('Services settings', 'wc_colissimo'); ?></a>
        <?php } ?>
        <?php if (!empty($args['urlMaterials'])) { ?>
			|
			<a class="<?php echo esc_attr($args['class']); ?>" href="<?php echo esc_url($args['urlMaterials']); ?>" target="_blank">
                <?php esc_html_e('Labels and packagings', 'wc_colissimo'); ?></a>
        <?php } ?>
	</td>
</tr>
