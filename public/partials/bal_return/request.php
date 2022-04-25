<div class="lpc_balreturn">
	<h1 class="entry-title"><?php echo esc_html__('MailBox picking return', 'wc_colissimo'); ?></h1>

	<h2 class="lpc_balreturn_subtitle"><?php echo esc_html__('Retrieval address', 'wc_colissimo'); ?></h2>
	<div class="lpc_balreturn_shipping lpc_balreturn_withseparator">
		<div>
            <?php echo esc_html__('Your order was initially sent to the following address:', 'wc_colissimo'); ?>
		</div>
		<div class="lpc_balreturn_shipping_address">
            <?php echo $args['order']->get_formatted_shipping_address(); ?>
		</div>
	</div>

	<div class="lpc_balreturn_address woocommerce-address-fields__field-wrapper">
		<div><?php echo esc_html__(
                'You may change the address the return will be made from via the following fields:',
                'wc_colissimo'
            ); ?></div>
		<form method="POST" action="<?php echo $args['urlBalReturn']; ?>">
			<p class="form-row form-row-wide">
				<label for="lpc_bal_companyName"><?php echo esc_html__('Name'); ?></label>
				<input type="text" id="lpc_bal_companyName" name="address[companyName]"
					   value="<?php echo $args['order']->get_formatted_shipping_full_name(); ?>" />
			</p>
			<p class="form-row form-row-wide">
				<label for="lpc_bal_street"><?php echo esc_html__('Street', 'wc_colissimo'); ?></label>
                <?php
                $shippingStreet = $args['order']->get_shipping_address_1();
                $shippingStreet .= !empty($args['order']->get_shipping_address_2()) ? ' ' . $args['order']->get_shipping_address_2() : '';
                ?>
				<input type="text" id="lpc_bal_street" name="address[street]" value="<?php echo $shippingStreet; ?>" />
			</p>
			<p class="form-row form-row-wide">
				<label for="lpc_bal_zipCode"><?php echo esc_html__('Zip code', 'wc_colissimo'); ?></label>
				<input type="text" id="lpc_bal_zipCode" name="address[zipCode]"
					   value="<?php echo $args['order']->get_shipping_postcode(); ?>" />
			</p>
			<p class="form-row form-row-wide">
				<label for="lpc_bal_city"><?php echo esc_html__('City', 'wc_colissimo'); ?></label>
				<input type="text" id="lpc_bal_city" name="address[city]"
					   value="<?php echo $args['order']->get_shipping_city(); ?>" />
			</p>
			<p class="form-row form-row-wide">
				<label for="lpc_bal_country"><?php echo esc_html__('Country'); ?></label>
				<input type="text" id="lpc_bal_country" name="address[country]" value="FR" readonly="true"
					   disabled="true" />
				<i><?php echo esc_html__('Only France is allowed', 'wc_colissimo'); ?></i>
			</p>

			<div class="lpc_balreturn_btn">
				<input type="hidden" name="lpc_action" value="checkAvailability" />
				<button
						type="submit"><?php echo esc_html__(
                        'Check that this address is allowed for MailBox picking return',
                        'wc_colissimo'
                    ); ?></button>
			</div>
		</form>
	</div>
</div>
