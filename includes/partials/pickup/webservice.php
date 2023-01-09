<?php if ('gmaps' === $args['mapType'] && !empty($args['apiKey'])) { ?>
    <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
	<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $args['apiKey']; ?>" async defer></script>
<?php } ?>

<?php if ('leaflet' === $args['mapType']) { ?>
    <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.2/dist/leaflet.css"
		  integrity="sha256-sA+zWATbFveLLNqWO2gtiw3HL/lh1giY/Inf1BJ0z14="
		  crossorigin="" />
    <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
	<script src="https://unpkg.com/leaflet@1.9.2/dist/leaflet.js"
			integrity="sha256-o9N1jGDZrf5tS+Ft4gbIK7mYMipq9lqpVJ91xHSyKhg="
			crossorigin=""></script>
<?php } ?>

<div>
    <?php
    if ($args['showButton']) {
        if ($args['showInfo']) {
            echo LpcHelper::renderPartial('pickup' . DS . 'pick_up_info.php', ['relay' => $args['currentRelay']]);
        }
        ?>
		<div>
            <?php
            if (!empty($args['currentRelay'])) {
                $linkText = __('Change PickUp point', 'wc_colissimo');
            } else {
                $linkText = __('Choose PickUp point', 'wc_colissimo');
            }

            if ('link' === $args['type']) {
                ?>
				<a id="lpc_pick_up_web_service_show_map"
				   data-lpc-template="lpc_pick_up_web_service"
				   data-lpc-callback="lpcInitMapWebService"><?php echo $linkText; ?></a>
                <?php
            } else {
                ?>
				<button type="button"
						id="lpc_pick_up_web_service_show_map"
						data-lpc-template="lpc_pick_up_web_service"
						data-lpc-callback="lpcInitMapWebService"><?php echo $linkText; ?></button>
                <?php
            }
            ?>
		</div>
        <?php
    }
    $args['modal']->echo_modal();
    ?>
</div>
