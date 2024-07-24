<?php
$i        = $args['i'];
$oneRelay = $args['oneRelay'];
?>

<div class="lpc_layer_relay"
	 id="lpc_layer_relay_<?php echo $i; ?>"
	 data-relayindex="<?php echo $i; ?>"
	 data-lpc-relay-id="<?php echo $oneRelay['identifiant']; ?>"
	 data-lpc-relay-country_code="<?php echo $oneRelay['codePays']; ?>"
	 data-lpc-relay-latitude="<?php echo $oneRelay['coordGeolocalisationLatitude']; ?>"
	 data-lpc-relay-longitude="<?php echo $oneRelay['coordGeolocalisationLongitude']; ?>">
	<div class="lpc_pickup_info_distance">
		<img class="lpc_pickup_marker" src="<?php echo plugins_url('/images/map_marker.png', LPC_INCLUDES . 'init.php'); ?>">
		<span class="lpc_pickup_info_distance_txt"><?php echo sprintf(__('AT %dm', 'wc_colissimo'), $oneRelay['distanceEnMetre']); ?></span>
	</div>
	<div class="lpc_layer_relay_name"><?php echo $oneRelay['nom']; ?></div>
	<div class="lpc_layer_relay_address">
		<span class="lpc_layer_relay_type"><?php echo $oneRelay['typeDePoint']; ?></span>
		<span class="lpc_layer_relay_id"><?php echo $oneRelay['identifiant']; ?></span>
		<span class="lpc_layer_relay_address_street"><?php echo $oneRelay['adresse1']; ?></span>
		<span class="lpc_layer_relay_address_zipcode"><?php echo $oneRelay['codePostal']; ?></span>
		<span class="lpc_layer_relay_address_city"><?php echo $oneRelay['localite']; ?></span>
		<span class="lpc_layer_relay_address_country"><?php echo empty($oneRelay['libellePays']) ? $oneRelay['codePays'] : $oneRelay['libellePays']; ?></span>
		<span class="lpc_layer_relay_latitude"><?php echo $oneRelay['coordGeolocalisationLatitude']; ?></span>
		<span class="lpc_layer_relay_longitude"><?php echo $oneRelay['coordGeolocalisationLongitude']; ?></span>
		<span class="lpc_layer_relay_distance_value"><?php echo $oneRelay['distanceEnMetre']; ?></span>
		<span class="lpc_layer_relay_hour_monday"><?php echo $oneRelay['horairesOuvertureLundi']; ?></span>
		<span class="lpc_layer_relay_hour_tuesday"><?php echo $oneRelay['horairesOuvertureMardi']; ?></span>
		<span class="lpc_layer_relay_hour_wednesday"><?php echo $oneRelay['horairesOuvertureMercredi']; ?></span>
		<span class="lpc_layer_relay_hour_thursday"><?php echo $oneRelay['horairesOuvertureJeudi']; ?></span>
		<span class="lpc_layer_relay_hour_friday"><?php echo $oneRelay['horairesOuvertureVendredi']; ?></span>
		<span class="lpc_layer_relay_hour_saturday"><?php echo $oneRelay['horairesOuvertureSamedi']; ?></span>
		<span class="lpc_layer_relay_hour_sunday"><?php echo $oneRelay['horairesOuvertureDimanche']; ?></span>

		<div class="lpc_layer_relay_schedule">
            <?php
            $hourContent = '<table cellpadding="0" cellspacing="0">';
            foreach ($args['openingDays'] as $day => $oneDay) {
                if ('00:00-00:00 00:00-00:00' == $oneRelay[$oneDay]) {
                    continue;
                }
                $hourContent .= '<tr>';
                $hourContent .= '<td>' . ucfirst(__($day)) . '</td>';
                $hourContent .= '<td class="opening_hours">';
                $hourContent .= str_replace(
                    [' ', ' - 00:00-00:00'],
                    [' - ', ''],
                    $oneRelay[$oneDay]
                );
                $hourContent .= '</td></tr>';
            }
            $hourContent .= '</table>';
            echo $hourContent;
            ?>
		</div>
	</div>
	<div class="lpc_layer_relay_display_hours">
		<div class="lpc_layer_relay_hours_header">
			<div class="lpc_layer_relay_hours_icon_hour"></div>
			<div class="lpc_layer_relay_hours_title"><?php esc_html_e('Opening hours', 'wc_colissimo'); ?></div>
			<div class="lpc_layer_relay_hours_icon lpc_layer_relay_hours_icon_down"></div>
		</div>
		<div class="lpc_layer_relay_hours_details" style="display: none;">
            <?php echo $hourContent; ?>
		</div>
	</div>
	<div class="lpc_relay_choose_btn">
		<a class="lpc_show_relay_details"><?php esc_html_e('Display on map', 'wc_colissimo'); ?></a>
		<button class="lpc_relay_choose" type="button" data-relayindex="<?php echo $i; ?>">
            <?php esc_html_e('Choose', 'wc_colissimo'); ?>
		</button>
	</div>
</div>

<?php if (($i + 1) < $args['relaysNb']) { ?>
	<hr class="lpc_relay_separator">
<?php } ?>
