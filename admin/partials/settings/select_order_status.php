<?php
$id_and_name     = $args['id_and_name'];
$label           = $args['label'];
$multiple        = $args['multiple'];
$selected_values = ($args['selected_values']) ? $args['selected_values'] : [];
$order_statuses  = $args['order_statuses'];
?>
<tr valign="top">
	<th scope="row">
		<label for="<?php echo esc_attr_e($id_and_name); ?>"><?php esc_html_e($label, 'wc_colissimo'); ?></label>
	</th>
	<td>
		<select <?php echo $multiple; ?> name="<?php echo $id_and_name . (('multiple' === $multiple) ? '[]' : ''); ?>" id="<?php echo $id_and_name; ?>" style="height:100%;">
            <?php
            foreach (
                $order_statuses

                as $name => $label
            ) { ?>
				<option value="<?php echo esc_attr($name); ?>"
                    <?php echo (('multiple' === $multiple && in_array(
                                $name,
                                $selected_values
                            )) || (('' === $multiple && $name === $selected_values))) ? 'selected' : ''; ?>><?php echo esc_attr($label); ?>
				</option>
            <?php } ?>
		</select>
	</td>
</tr>

