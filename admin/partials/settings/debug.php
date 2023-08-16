<tr valign="top">
	<th scope="row" class="titledesc">
        <?php esc_html_e($field['title'], 'wc_colissimo'); ?>
	</th>
	<td class="forminp forminp-<?php echo esc_attr(sanitize_title($field['type'])); ?>">
        <?php $modal->echo_modalAndButton(__($field['text'], 'wc_colissimo')); ?>
	</td>
</tr>
