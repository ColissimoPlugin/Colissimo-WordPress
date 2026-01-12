<?php
defined('ABSPATH') || die('Restricted Access');
$value = LpcHelper::get_option($args['field_name'], $args['default'] ?? '');
?>
<tr class="<?php echo esc_attr($args['row_class'] ?? ''); ?>">
	<th scope="row">
		<label for="<?php esc_attr_e($args['id']); ?>">
            <?php
            esc_html_e($args['title'], 'wc_colissimo');
            if (!empty($args['desc']) && !empty($args['desc_tip'])) {
                echo wc_help_tip(__($args['desc'], 'wc_colissimo'));
            }
            ?>
		</label>
	</th>
	<td>
		<input type="text"
			   placeholder="<?php esc_attr_e($args['placeholder'] ?? '', 'wc_colissimo'); ?>"
			   name="<?php echo esc_attr($args['id']); ?>"
			   id="<?php echo esc_attr($args['id']); ?>"
			   value="<?php echo esc_attr($value); ?>"
			   class="<?php echo esc_attr($args['class'] ?? ''); ?>"
		/>
        <?php
        if (!empty($args['desc']) && empty($args['desc_tip'])) {
            echo '<p class="description">' . esc_html__($args['desc'], 'wc_colissimo') . '</p>';
        }
        ?>
	</td>
</tr>
