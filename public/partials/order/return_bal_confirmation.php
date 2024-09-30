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
	<div>
        <?php if ($data['pickupConfirmation']) { ?>
            <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
			<script src="<?php echo esc_url(plugins_url('/js/orders/return_bal.js', LPC_PUBLIC . 'init.php')); ?>"></script>
		<input type="hidden" id="lpc_download_url" value="<?php echo esc_url($data['labelDownloadUrl'], null, 'javascript'); ?>" />

			<div>
				<b><?php esc_html_e('Your PickUp has been confirmed.', 'wc_colissimo'); ?></b>
			</div>
			<div>
                <?php esc_html_e('Your return tracking number is:', 'wc_colissimo'); ?>
                <?php echo esc_html($data['returnTrackingNumber']); ?>
			</div>

			<div id="lpc_return_instructions_container">
				<p id="instructions_title"><?php esc_html_e('How to return your parcel?', 'wc_colissimo'); ?></p>
				<ol>
					<li><?php esc_html_e('Pack your products.', 'wc_colissimo'); ?></li>
					<li><?php esc_html_e('Print your label and stick it on your parcel.', 'wc_colissimo'); ?></li>
					<li><?php esc_html_e('Put your parcel in your mailbox before the appointment time.', 'wc_colissimo'); ?></li>
					<li>
                        <?php
                        wp_kses(
                            printf(
                                __('Track your parcel shipment on %s', 'wc_colissimo'),
                                '<a target="_blank" href="https://laposte.fr/outils/suivre-vos-envois?code=' . esc_attr($data['returnTrackingNumber']) . '">https://laposte.fr/suivi</a>'
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
        <?php } else { ?>
			<p class="lpc_balreturn_error"><b><?php esc_html_e('An error occured while confirming the mailBox pick-up.', 'wc_colissimo'); ?></b></p>
        <?php } ?>
	</div>
</div>
