<?php
$id_and_name     = $args['id_and_name'];
$label           = $args['label'];
$multiple        = empty($args['multiple']) ? '' : 'multiple';
$selected_values = $args['selected_values'] ? $args['selected_values'] : [];
$values          = $args['values'];
$tips            = empty($args['tips']) ? '' : $args['tips'];
?>
<tr valign="top">
	<th scope="row">
		<label for="<?php esc_attr_e($id_and_name); ?>">
            <?php esc_html_e($label, 'wc_colissimo');
            if (!empty($tips)) {
                echo wc_help_tip($tips);
            } ?>
		</label>
	</th>
	<td>
		<select <?php echo $multiple; ?> name="<?php echo $id_and_name . (('multiple' === $multiple) ? '[]' : ''); ?>" id="<?php echo $id_and_name; ?>" style="height:100%;">
            <?php
            if (!empty($args['optgroup'])) {
                foreach ($values as $oneGroup) { ?>
					<optgroup label="<?php echo esc_attr($oneGroup['label']); ?>">
                        <?php foreach ($oneGroup['values'] as $name => $label) { ?>
							<option value="<?php echo esc_attr($name); ?>"
                                <?php echo (('multiple' === $multiple && in_array($name, $selected_values))
                                            || (('' === $multiple && $name === $selected_values))) ? 'selected' : ''; ?>>
                                <?php echo esc_attr($label) . ' (' . esc_attr($name) . ')'; ?>
							</option>
                        <?php } ?>
					</optgroup>
                <?php }
            } else {
                foreach ($values as $name => $label) { ?>
					<option value="<?php echo esc_attr($name); ?>"
                        <?php echo (('multiple' === $multiple && in_array($name, $selected_values))
                                    || (('' === $multiple && $name === $selected_values))) ? 'selected' : ''; ?>><?php echo esc_attr($label); ?>
					</option>
                <?php }
            } ?>
		</select>
	</td>
</tr>

