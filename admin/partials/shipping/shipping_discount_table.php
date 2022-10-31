<?php
$shippingMethod  = $args['shippingMethod'];
$currentDiscount = $shippingMethod->get_option('shipping_discount', []);
?>
<tr valign="top">
	<th scope="row" class="titledesc"><?php esc_html_e('Shipping discount', 'wc_colissimo'); ?></th>
	<td class="forminp" id="<?php echo $shippingMethod->id; ?>_shipping_discounts" style="overflow: auto;">
		<table class="shippingrows widefat" cellspacing="0">
			<thead>
				<tr>
					<td class="check-column"><input type="checkbox"></td>
					<th>
						<p><?php esc_html_e('Number of products', 'wc_colissimo'); ?></p>
					</th>
					<th>
						<p><?php esc_html_e('Discount in precentage', 'wc_colissimo'); ?></p>
					</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th colspan="7">
						<button type="button" class="add button" id="lpc_shipping_discount_add"
								style="margin-left: 24px"><?php esc_html_e('Add discount', 'wc_colissimo'); ?></button>
						<button type="button" class="remove button"
								id="lpc_shipping_discount_remove"><?php esc_html_e('Delete selected', 'wc_colissimo'); ?></button>
					</th>
				</tr>
			</tfoot>
			<tbody class="table_discount">
                <?php
                $counter = 0;
                foreach ($currentDiscount as $i => $discount) {
                    ?>
					<tr>
						<td class="check-column"><input type="checkbox" /></td>
						<td style="text-align: center">
							<input type="number"
								   class="input-number regular-input"
								   step="1"
								   min="0"
								   required
								   value="<?php echo isset($discount['nb_product']) ? esc_attr($discount['nb_product']) : ''; ?>"
								   name="shipping_discount[<?php echo $i; ?>][nb_product]" />
						</td>
						<td style="text-align: center">
							<input type="number"
								   class="input-number regular-input"
								   step="any"
								   min="0"
								   max="100"
								   required
								   value="<?php echo isset($discount['percentage']) ? esc_attr($discount['percentage']) : ''; ?>"
								   name="shipping_discount[<?php echo $i; ?>][percentage]" />
						</td>
					</tr>
                    <?php $counter ++;
                } ?>
			</tbody>
		</table>
	</td>
</tr>
<script type="text/javascript">
    window.lpc_i18n_delete_selected_discount = "<?php echo esc_attr('Delete the selected discount?'); ?>";
</script>
