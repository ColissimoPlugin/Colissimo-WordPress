<tr valign="top">
    <th scope="row">
        <label for="<?php esc_attr_e($args['id_and_name']); ?>">
            <?php
            printf(
                esc_html__($args['label'], 'wc_colissimo'),
                LpcHelper::get_option('woocommerce_weight_unit', '')
            );
            echo wc_help_tip($args['desc']);
            ?>
        </label>
    </th>
    <td>
        <input name="<?php esc_attr_e($args['id_and_name']); ?>" id="<?php esc_attr_e($args['id_and_name']); ?>"
               type="number" step="0.01" min="0" style="height:100%;"
               value="<?php echo !empty($args['value']) ? $args['value'] : '0'; ?>" />
    </td>
</tr>

