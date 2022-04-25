<div id="lpc_layer_relays">
	<div class="content">
		<div id="lpc_search_address">
			<label id="lpc_modal_relays_search_address">
                <?php echo __('Address', 'wc_colissimo'); ?>
				<input type="text" class="lpc_modal_relays_search_input" value="<?php echo $args['ceAddress']; ?>">
			</label>
			<div id="lpc_modal_address_details">
				<label id="lpc_modal_relays_search_zipcode">
                    <?php echo __('Zipcode', 'wc_colissimo'); ?>
					<input type="text" class="lpc_modal_relays_search_input" value="<?php echo $args['ceZipCode']; ?>">
				</label>
				<label id="lpc_modal_relays_search_city">
                    <?php echo __('City', 'wc_colissimo'); ?>
					<input type="text" class="lpc_modal_relays_search_input" value="<?php echo $args['ceTown']; ?>">
				</label>
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
