<div class="lpc_balreturn">
	<h2 class="entry-title margin-top-0"><?php esc_html_e('MailBox picking return', 'wc_colissimo'); ?></h2>

	<div class="lpc_balreturn_shipping lpc_balreturn_withseparator">
		<div>
            <?php esc_html_e('Address from which the return will be from:', 'wc_colissimo'); ?>
		</div>
		<div class="lpc_balreturn_shipping_address">
            <?php echo WC()->countries->get_formatted_address($data['addressDisplay']); ?>
		</div>
	</div>

	<div class="lpc_balreturn_address woocommerce-address-fields__field-wrapper">
		<form method="POST" action="<?php echo esc_url($data['balReturnUrl']); ?>">
			<input type="hidden" name="lpc_label_products" value="<?php echo esc_attr($data['products']); ?>" />

            <?php if ($data['listMailBoxPickingDatesResponse'] && !empty($data['mailBoxPickingDate'])) { ?>
				<input type="hidden" id="lpc_bal_companyName" name="address[companyName]" value="<?php echo esc_attr($data['address']['companyName']); ?>" />
				<input type="hidden" id="lpc_bal_street" name="address[street]" value="<?php echo esc_attr($data['address']['street']); ?>" />
				<input type="hidden" id="lpc_bal_zipCode" name="address[zipCode]" value="<?php echo esc_attr($data['address']['zipCode']); ?>" />
				<input type="hidden" id="lpc_bal_city" name="address[city]" value="<?php echo esc_attr($data['address']['city']); ?>" />
				<input type="hidden"
					   id="lpc_bal_pickingDate"
					   name="pickingDate"
					   value="<?php echo esc_attr($data['listMailBoxPickingDatesResponse']['mailBoxPickingDates'][0]); ?>" />
				<p>
                    <?php
                    echo sprintf(
                        __('Please confirm before today %1$s that you will put the parcel in the MailBox described previously, before the %2$s at %3$s.', 'wc_colissimo'),
                        $data['listMailBoxPickingDatesResponse']['validityTime'],
                        $data['mailBoxPickingDate'],
                        $data['listMailBoxPickingDatesResponse']['mailBoxPickingDateMaxHour']
                    );
                    ?>
				</p>
				<div>
					<button type="submit" class="button wp-element-button lpc_balreturn_btn">
                        <?php esc_html_e('Confirm pick-up', 'wc_colissimo'); ?>
					</button>
				</div>
            <?php } else { ?>
				<p class="lpc_balreturn_error"><b><?php esc_html_e('This address is not eligible for MailBox pick-up.', 'wc_colissimo'); ?></b></p>
            <?php } ?>
		</form>
	</div>
</div>
