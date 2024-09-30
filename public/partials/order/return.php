<input type="hidden" id="lpc_select_products" value="<?php esc_attr_e('You need to select at least one item to generate a label', 'wc_colissimo'); ?>" />
<input type="hidden" id="lpc_generate_url" value="<?php echo esc_url($data['generateUrlBase'], null, 'javascript'); ?>" />
<input type="hidden" id="lpc_download_url" value="<?php echo esc_url($data['downloadUrlBase'], null, 'javascript'); ?>" />

<h2 class="woocommerce-return-details__title margin-top-0"><?php esc_html_e('Return details', 'wc_colissimo'); ?></h2>

<div id="lpc_return_options">
	<p><?php esc_html_e('Select the products you would like to return:', 'wc_colissimo'); ?></p>
	<table id="lpc_return_table" class="shop_table">
		<thead>
			<tr>
				<th><input type="checkbox" id="lpc_selectall" /></th>
				<th><?php esc_html_e('Product', 'wc_colissimo'); ?></th>
				<th><?php esc_html_e('Quantity', 'wc_colissimo'); ?></th>
			</tr>
		</thead>
		<tbody>
            <?php
            foreach ($data['order']->get_items() as $item) {
                $itemId = $item->get_id();
                ?>
				<tr>
					<td><input type="checkbox" class="lpc_return_checkbox" id="lpc_return_<?php echo esc_attr($itemId); ?>" /></td>
					<td><label for="lpc_return_<?php echo esc_attr($itemId); ?>"><?php echo $item->get_name(); ?></label></td>
					<td>
						<select data-lpc-product="<?php echo esc_attr($itemId); ?>">
                            <?php for ($i = 1; $i <= $item->get_quantity(); $i ++) { ?>
								<option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
                            <?php } ?>
						</select>
					</td>
				</tr>
            <?php } ?>
		</tbody>
	</table>

	<button type="button" class="button wp-element-button" id="lpc_download_return_label">
        <?php esc_html_e($data['securedReturn'] ? 'Return from a post office' : 'Generate inward label', 'wc_colissimo'); ?>
	</button>

    <?php if ($data['balReturn']) { ?>
		<input type="hidden" id="lpc_bal_url" value="<?php echo esc_url($data['balReturnUrl'], null, 'javascript'); ?>" />
		<button type="button" class="button wp-element-button" id="lpc_return_bal_button">
            <?php esc_html_e('MailBox picking return', 'wc_colissimo'); ?>
		</button>
    <?php } ?>
</div>
<div id="lpc_return_label_confirmation">
    <?php
    wp_kses(
        printf(
            __($data['securedReturn'] ? 'Your secured code for the return label %s has been generated.' : 'Your label %s has been generated', 'wc_colissimo'),
            '<span id="lpc_return_label_confirmation_tracking_number"></span>'
        ),
        [
            'span' => [
                'id' => [],
            ],
        ]
    );
    ?>
</div>
<div id="lpc_return_instructions_container">
	<p id="instructions_title"><?php esc_html_e('How to return your parcel?', 'wc_colissimo'); ?></p>
	<ol>
		<li><?php esc_html_e('Pack your products.', 'wc_colissimo'); ?></li>
        <?php if ($data['securedReturn']) { ?>
			<li>
                <?php esc_html_e('Go to the post office of your choice:', 'wc_colissimo'); ?>
				<a target="_blank"
				   href="https://localiser.laposte.fr/?jesuis=particulier&contact=vente&qp=<?php echo esc_attr($data['order']->get_shipping_postcode()); ?>">https://localiser.laposte.fr</a>
			</li>
			<li>
                <?php
                esc_html_e(
                    'At the drop-off point, present your barcode to your contact or to the machine (or your 9-character code) to have your label printed and make your deposit.',
                    'wc_colissimo'
                );
                ?>
			</li>
        <?php } else { ?>
			<li><?php esc_html_e('Print your label and stick it on your parcel.', 'wc_colissimo'); ?></li>
			<li>
                <?php esc_html_e('Drop off your parcel at the post office of your choice:', 'wc_colissimo'); ?>
				<a target="_blank"
				   href="https://localiser.laposte.fr/?jesuis=particulier&contact=vente&qp=<?php echo esc_attr($data['order']->get_shipping_postcode()); ?>">https://localiser.laposte.fr</a>
			</li>
        <?php } ?>
		<li>
            <?php
            wp_kses(
                printf(
                    __('Track your parcel shipment on %s', 'wc_colissimo'),
                    '<a target="_blank" href="https://laposte.fr/outils/suivre-vos-envois">https://laposte.fr/suivi</a>'
                ),
                [
                    'a' => [
                        'href' => [],
                        'target' => [],
                    ],
                ]
            );
            ?>
		</li>
	</ol>
</div>
