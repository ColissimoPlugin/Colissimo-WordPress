<tr valign="top">
	<th scope="row">
		<label for="<?php esc_attr_e($args['id_and_name']); ?>">
            <?php echo __($args['label'], 'wc_colissimo'); ?>
		</label>
	</th>
	<td>
		<input name="<?php esc_attr_e($args['id_and_name']); ?>" id="<?php esc_attr_e($args['id_and_name']); ?>"
			   type="text"
			   value="<?php echo !empty($args['value']) ? $args['value'] : ''; ?>" />
		<p class="description"><?php esc_html_e($args['desc'], 'wc_colissimo'); ?></p>
	</td>
</tr>

