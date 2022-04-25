<tr valign="top">
	<th scope="row" class="titledesc">
		<label><?php esc_html_e($field['title'], 'wc_colissimo'); ?></label>
	</th>
	<td class="forminp forminp-<?php echo esc_attr(sanitize_title($field['type'])); ?>">
		<a class="button" href="mailto:<?php esc_attr_e($field['email']); ?>">
            <?php echo __($field['text'], 'wc_colissimo'); ?>
		</a>
	</td>
</tr>

