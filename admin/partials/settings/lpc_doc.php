<tr valign="top">
	<th scope="row" class="titledesc">
		<label><?php esc_html_e($field['title'], 'wc_colissimo'); ?></label>
	</th>
	<td class="forminp forminp-<?php echo esc_attr(sanitize_title($field['type'])); ?>">
		<button id="lpc_doc_download" class="button">
			<?php esc_html_e('Download FR', 'wc_colissimo'); ?>
		</button>
		<button id="lpc_doc_EN_download" class="button">
			<?php esc_html_e('Download EN', 'wc_colissimo'); ?>
		</button>
		<input type="hidden" id="lpc_doc_url" value="<?php echo esc_url($field['downloadUrl']); ?>" />
		<input type="hidden" id="lpc_doc_EN_url" value="<?php echo esc_url($field['downloadUrlEN']); ?>" />
	</td>
</tr>
