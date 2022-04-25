<?php
$id_and_name     = $args['id_and_name'];
$label           = $args['label'];
$multiple        = empty($args['multiple']) ? '' : 'multiple';
$selected_values = ($args['selected_values']) ? $args['selected_values'] : [];
$values          = $args['values'];
$description     = empty($args['description']) ? '' : $args['description'];
?>
<tr valign="top">
	<th scope="row">
		<label for="<?php esc_attr_e($id_and_name); ?>"><?php esc_html_e($label, 'wc_colissimo'); ?>
			<span class="woocommerce-help-tip" data-tip="<?php esc_html_e($description, 'wc_colissimo'); ?>"></span>
		</label>
	</th>
	<td>
		<select style="width: auto; max-width: 10rem"
            <?php echo esc_attr($multiple); ?>
				id="<?php esc_attr_e($id_and_name); ?>"
				class="lpc__shipping_rates__shipping_class__select select2-hidden-accessible"
				name="<?php esc_attr_e($id_and_name); ?>">
            <?php
            foreach ($args['values'] as $oneClass) {
                echo '<option value="' . $oneClass->term_id . '" ' . (
                    isset($selected_values) && in_array(
                        $oneClass->term_id,
                        $selected_values
                    ) ? 'selected="selected"' : ''
                    )
                     . '>' . $oneClass->name . '</option>';
            }
            ?>
		</select>
	</td>
</tr>

