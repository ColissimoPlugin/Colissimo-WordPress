<div id="lpc_layer_relays">
	<div class="content">
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
				<button id="lpc_layer_button_search" type="button"><?php echo __('Search', 'wc_colissimo'); ?></button>
			</div>
		</div>

		<div id="lpc_left">
			<div id="lpc_map"></div>
		</div>
		<div id="lpc_right">
			<div class="blockUI" id="lpc_layer_relays_loader" style="display: none;"></div>
			<div id="lpc_layer_error_message" style="display: none;"></div>
			<div id="lpc_layer_list_relays"></div>
		</div>
	</div>
</div>
