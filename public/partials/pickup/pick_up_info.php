<?php $relay = $args['relay'];
if (!empty($relay)) {
    $openingDays = [
        'Monday'    => 'horairesOuvertureLundi',
        'Tuesday'   => 'horairesOuvertureMardi',
        'Wednesday' => 'horairesOuvertureMercredi',
        'Thursday'  => 'horairesOuvertureJeudi',
        'Friday'    => 'horairesOuvertureVendredi',
        'Saturday'  => 'horairesOuvertureSamedi',
        'Sunday'    => 'horairesOuvertureDimanche',
    ];
    $openTime    = __('Opening hours', 'wc_colissimo') . '<br />';
    foreach ($openingDays as $day => $oneDay) {
        if (empty($relay[$oneDay]) || ' ' === $relay[$oneDay] || '00:00-00:00 00:00-00:00' === $relay[$oneDay]) {
            continue;
        }
        $openTime .= ucfirst(__($day)) . ' ' . str_replace(' 00:00-00:00', '', $relay[$oneDay]) . '<br />';
    }

    ?>
	<div id="lpc_pick_up_info" data-pickup-id="<?php echo esc_attr($relay['identifiant']); ?>">
		<div class="lpc_pickup_info_title"><?php echo __('SELECTED RELAY', 'wc_colissimo'); ?></div>
		<div>
            <?php if (!empty($relay['distanceEnMetre'])) { ?>
				<div class="lpc_pickup_info_distance">
					<img class="lpc_pickup_marker" src="<?php echo plugins_url('/images/map_marker.png', LPC_INCLUDES . 'init.php'); ?>">
					<span class="lpc_pickup_info_distance_txt"><?php echo sprintf(__('AT %dm', 'wc_colissimo'), $relay['distanceEnMetre']); ?></span>
				</div>
            <?php } ?>
			<div class="lpc_pickup_info_address">
				<div class="lpc_pickup_info_address_name">
                    <?php echo $relay['nom'];
                    if (!empty($openTime)) {
                        ?>
						<div class="tooltip-box">
							<div class="tooltip-text">
                                <?php echo $openTime; ?>
							</div>
						</div>
                    <?php } ?>
				</div>
				<div class="lpc_pickup_info_address_line"><?php echo esc_html($relay['adresse1']); ?></div>
				<div class="lpc_pickup_info_address_line"><?php echo esc_html($relay['codePostal']) . ' ' . esc_html($relay['localite']); ?></div>
				<div class="lpc_pickup_info_address_line"><?php echo esc_html($relay['libellePays']); ?></div>
			</div>
		</div>
	</div>
<?php } else { ?>
	<div id="lpc_pick_up_info"></div>
<?php } ?>
