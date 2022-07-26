<?php
$i        = $args['i'];
$oneRelay = $args['oneRelay'];
?>

<div class="lpc_layer_relay"
	 id="lpc_layer_relay_<?php echo $i; ?>"
	 data-relayindex="<?php echo $i; ?>"
	 data-lpc-relay-id="<?php echo $oneRelay->identifiant; ?>"
	 data-lpc-relay-country_code="<?php echo $oneRelay->codePays; ?>"
	 data-lpc-relay-latitude="<?php echo $oneRelay->coordGeolocalisationLatitude; ?>"
	 data-lpc-relay-longitude="<?php echo $oneRelay->coordGeolocalisationLongitude; ?>">
	<div class="lpc_layer_relay_name"><?php echo $oneRelay->nom; ?></div>
	<div class="lpc_layer_relay_address">
		<span class="lpc_layer_relay_type"><?php echo $oneRelay->typeDePoint; ?></span>
		<span class="lpc_layer_relay_id"><?php echo $oneRelay->identifiant; ?></span>
		<span class="lpc_layer_relay_address_street"><?php echo $oneRelay->adresse1; ?></span>
		<span class="lpc_layer_relay_address_zipcode"><?php echo $oneRelay->codePostal; ?></span>
		<span class="lpc_layer_relay_address_city"><?php echo $oneRelay->localite; ?></span>
		<span class="lpc_layer_relay_address_country"><?php echo $oneRelay->libellePays; ?></span>
		<span class="lpc_layer_relay_latitude"><?php echo $oneRelay->coordGeolocalisationLatitude; ?></span>
		<span class="lpc_layer_relay_longitude"><?php echo $oneRelay->coordGeolocalisationLongitude; ?></span>
		<div>
			<a href="#" class="lpc_show_relay_details"><?php echo __('Display', 'wc_colissimo'); ?></a>
		</div>
		<div class="lpc_layer_relay_schedule">
			<table cellpadding="0" cellspacing="0">
                <?php
                foreach ($args['openingDays'] as $day => $oneDay) {
                    if ('00:00-00:00 00:00-00:00' == $oneRelay->$oneDay) {
                        continue;
                    }
                    ?>

					<tr>
						<td><?php echo __($day); ?></td>
						<td class="opening_hours">
                            <?php echo str_replace(
                                [' ', ' - 00:00-00:00'],
                                [' - ', ''],
                                $oneRelay->$oneDay
                            ); ?>
						</td>
					</tr>

                    <?php
                }
                ?>
			</table>
		</div>
		<div class="lpc_layer_relay_distance"><?php echo __('At', 'wc_colissimo') . ' ' . $oneRelay->distanceEnMetre; ?>
			m
		</div>
	</div>
	<div class="lpc_relay_choose_btn">
		<button class="lpc_relay_choose" type="button" data-relayindex="<?php echo $i; ?>">
            <?php echo __('Choose', 'wc_colissimo'); ?>
		</button>
	</div>
</div>

<?php if (($i + 1) < $args['relaysNb']) { ?>
	<hr class="lpc_relay_separator">
<?php } ?>
