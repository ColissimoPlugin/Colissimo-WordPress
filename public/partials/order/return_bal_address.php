<div class="lpc_balreturn">
	<h2 class="entry-title margin-top-0"><?php esc_html_e('MailBox picking return', 'wc_colissimo'); ?></h2>

	<div class="lpc_balreturn_shipping lpc_balreturn_withseparator">
		<div>
            <?php esc_html_e('Your order was initially sent to the following address:', 'wc_colissimo'); ?>
		</div>
		<div class="lpc_balreturn_shipping_address">
            <?php echo WC()->countries->get_formatted_address($data['address']); ?>
		</div>
	</div>

	<div class="lpc_balreturn_address woocommerce-address-fields__field-wrapper">
		<div>
            <?php esc_html_e('You may change the address the return will be made from via the following fields:', 'wc_colissimo'); ?>
		</div>
		<form method="POST" action="<?php echo esc_url($data['balReturnUrl']); ?>">
			<input type="hidden" name="lpc_label_products" value="<?php echo esc_attr($data['products']); ?>" />

			<p class="form-row form-row-first">
				<label for="lpc_bal_companyName"><?php esc_html_e('Name', 'wc_colissimo'); ?></label>
				<input type="text"
					   id="lpc_bal_companyName"
					   name="address[companyName]"
					   value="<?php echo esc_attr($data['address']['company']); ?>"
					   class="input-text" />
			</p>
			<p class="form-row form-row-last">
				<label for="lpc_bal_country"><?php esc_html_e('Country', 'wc_colissimo'); ?></label>
				<input type="text" id="lpc_bal_country" name="address[country]" value="FR" readonly="readonly" disabled="disabled" class="input-text" />
			</p>
			<p class="form-row form-row-wide">
				<label for="lpc_bal_street"><?php esc_html_e('Address', 'wc_colissimo'); ?></label>
				<input type="text" id="lpc_bal_street" name="address[street]" value="<?php echo esc_attr($data['address']['address_1']); ?>" class="input-text" />
			</p>
			<p class="form-row form-row-first">
				<label for="lpc_bal_zipCode"><?php esc_html_e('Zip code', 'wc_colissimo'); ?></label>
				<input type="text"
					   id="lpc_bal_zipCode"
					   name="address[zipCode]"
					   value="<?php echo esc_attr($data['address']['postcode']); ?>"
					   class="input-text" />
			</p>
			<p class="form-row form-row-last">
				<label for="lpc_bal_city"><?php esc_html_e('City', 'wc_colissimo'); ?></label>
				<input type="text"
					   id="lpc_bal_city"
					   name="address[city]"
					   value="<?php echo esc_attr($data['address']['city']); ?>"
					   class="input-text" />
			</p>

			<div class="lpc_balreturn_btn">
				<button type="submit" class="button wp-element-button">
                    <?php esc_html_e('Check that this address is allowed for MailBox picking return', 'wc_colissimo'); ?>
				</button>
			</div>
		</form>
	</div>
</div>
