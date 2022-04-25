<?php
$shippingMethods = isset($args['lpc_shipping_methods']) ? $args['lpc_shipping_methods'] : [];
?>

<div class="lpc_order_affect">
	<script type="text/javascript">
        if (window.lpc_bind_order_affect !== undefined) {
            window.lpc_bind_order_affect();
        }

        if (window.initLpcModal !== undefined) {
            window.initLpcModal();
        }
	</script>

	<button type="button" class="button button-primary lpc_order_affect_toggle_methods">
        <?php echo __('Click here to ship this order with Colissimo', 'wc_colissimo'); ?>
	</button>

	<div class="lpc_order_affect_available_methods" style="display: none">
        <?php if (empty($shippingMethods)) { ?>
			<span class="lpc_order_affect_error_message">
				<?php echo __('No Colissimo shipping methods are available for this order', 'wc_colissimo'); ?>
			</span>
            <?php
        }

        foreach ($shippingMethods as $oneMethodId => $oneMethod) {
            ?>
			<div class="lpc_order_affect_method">
				<label>
					<input type="radio" value="<?php echo $oneMethodId; ?>" name="lpc_new_shipping_method">
                    <?php echo $oneMethod; ?>
				</label>
                <?php if (LpcRelay::ID === $oneMethodId) { ?>
					<div class="lpc_order_affect_relay" style="display: none">
                        <?php echo $args['link_choose_relay']; ?>
						<div class="lpc_order_affect_relay_information_displayed"></div>
					</div>
                <?php } ?>
			</div>
        <?php } ?>
		<input type="hidden" name="lpc_order_affect_relay_informations" value="{}">
		<input type="hidden" name="lpc_order_affect_update_method" value="0">
		<input type="hidden" name="lpc_order_affect_shipping_item_id" value="0">
		<div class="lpc_order_affect_error_message">
			<span style="display: none" class="lpc_order_affect_error_message_pickup">
				<?php echo __('Please select a pick-up point', 'wc_colissimo'); ?>
			</span>
			<span style="display: none" class="lpc_order_affect_error_message_method">
				<?php echo __('Please select a shipping method', 'wc_colissimo'); ?>
			</span>
		</div>

        <?php if (!empty($shippingMethods)) { ?>
			<button type="button" class="button button-primary lpc_order_affect_validate_method">
                <?php echo __('Choose', 'wc_colissimo'); ?>
			</button>
        <?php } ?>

	</div>
</div>
