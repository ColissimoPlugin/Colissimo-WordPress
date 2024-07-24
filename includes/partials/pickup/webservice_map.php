<div id="lpc_layer_relays">
	<div class="content">

        <?php
        $classMap   = '';
        $mobileIcon = 'dashicons-editor-ul';
        if ('list' === LpcHelper::get_option('lpc_show_list_only_mobile')) {
            $classMap   = 'class="lpc_mobile_display_none"';
            $mobileIcon = 'dashicons-location-alt';
        }
        ?>
		<span id="lpc_layer_relay_switch_mobile">
			<span class="lpc_layer_relay_switch_mobile_icon dashicons <?php echo $mobileIcon; ?>"></span>
		</span>
        <?php if (is_admin() && !empty($args['orderId'])) { ?>
			<input type="hidden" id="lpc_layer_order_id" value="<?php echo esc_attr($args['orderId']); ?>">
        <?php } ?>
		<div id="lpc_search_address">
			<input
					id="lpc_modal_relays_search_address"
					type="text"
					class="lpc_modal_relays_search_input"
					value="<?php echo $args['ceAddress']; ?>"
					placeholder="<?php echo __('Address', 'wc_colissimo'); ?>">
			<div id="lpc_modal_address_details">
				<input
						type="text"
						id="lpc_modal_relays_search_zipcode"
						class="lpc_modal_relays_search_input"
						value="<?php echo $args['ceZipCode']; ?>"
						placeholder="<?php echo __('Zipcode', 'wc_colissimo'); ?>">
				<input
						type="text"
						id="lpc_modal_relays_search_city"
						class="lpc_modal_relays_search_input"
						value="<?php echo $args['ceTown']; ?>"
						placeholder="<?php echo __('City', 'wc_colissimo'); ?>">
				<input type="hidden" id="lpc_modal_relays_country_id" value="<?php echo $args['ceCountryId']; ?>">
				<button id="lpc_layer_button_search" type="button">
					<span id="lpc_layer_button_search_desktop"><?php echo __('Search', 'wc_colissimo'); ?></span>
					<span class="dashicons dashicons-search" id="lpc_layer_button_search_mobile"></span>
				</button>
			</div>
            <?php if ($args['maxRelayPoint'] < 20) { ?>
				<a href="#" id="lpc_modal_relays_display_more"><?php esc_html_e('Display more pickup points', 'wc_colissimo'); ?></a>
            <?php } ?>
		</div>

		<div id="lpc_left" <?php echo $classMap; ?>>
			<div id="lpc_map"></div>
		</div>

		<div id="lpc_right">
			<div class="blockUI" id="lpc_layer_relays_loader" style="display: none;"></div>
			<div id="lpc_layer_error_message" style="display: none;"></div>
			<div id="lpc_layer_list_relays"></div>
		</div>
	</div>
</div>
