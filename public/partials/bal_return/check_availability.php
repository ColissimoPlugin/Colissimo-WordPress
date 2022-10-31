<div class="lpc_balreturn">
	<h1 class="entry-title"><?php echo esc_html__('MailBox picking return', 'wc_colissimo'); ?></h1>

	<h2 class="lpc_balreturn_subtitle"><?php echo esc_html__('Retrieval address', 'wc_colissimo'); ?></h2>
	<div class="lpc_balreturn_shipping lpc_balreturn_withseparator">
		<div>
            <?php echo esc_html__('Address from which the return will be from:', 'wc_colissimo'); ?>
		</div>
		<div class="lpc_balreturn_shipping_address">
            <?php echo WC()->countries->get_formatted_address($args['addressDisplay']); ?>
		</div>
	</div>

	<div class="lpc_balreturn_address woocommerce-address-fields__field-wrapper">
		<form method="POST" action="<?php echo $args['urlBalReturn']; ?>">
            <?php
            if ($args['listMailBoxPickingDatesResponse'] && !empty($args['mailBoxPickingDate'])) {
                ?>
				<input type="hidden" id="lpc_bal_companyName" name="address[companyName]" value="<?php esc_attr_e($args['address']['companyName']); ?>" />
				<input type="hidden" id="lpc_bal_street" name="address[street]" value="<?php esc_attr_e($args['address']['street']); ?>" />
				<input type="hidden" id="lpc_bal_zipCode" name="address[zipCode]" value="<?php esc_attr_e($args['address']['zipCode']); ?>" />
				<input type="hidden" id="lpc_bal_city" name="address[city]" value="<?php esc_attr_e($args['address']['city']); ?>" />
				<input type="hidden" id="lpc_bal_pickingDate" name="pickingDate" value="<?php esc_attr_e($args['listMailBoxPickingDatesResponse']['mailBoxPickingDates'][0]); ?>" />
				<p>
                    <?php
                    echo sprintf(
                        __('Please confirm before today %1$s that you will put the parcel in the MailBox described previously, before the %2$s at %3$s.', 'wc_colissimo'),
                        $args['listMailBoxPickingDatesResponse']['validityTime'],
                        $args['mailBoxPickingDate'],
                        $args['listMailBoxPickingDatesResponse']['mailBoxPickingDateMaxHour']
                    ); ?></p>
				<div>
					<input type="hidden" name="lpc_action" value="confirm" />
					<button class="lpc_balreturn_btn" type="submit"><?php echo esc_html__('Confirm pick-up', 'wc_colissimo'); ?></button>
				</div>
                <?php
            } else {
                ?>
				<p class="lpc_balreturn_error"><b><?php echo esc_html__('This address is not eligible for MailBox pick-up.', 'wc_colissimo'); ?></b></p>
                <?php
            } ?>

		</form>
	</div>
</div>
