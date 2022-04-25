<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $args['apiKey']; ?>" async defer></script>

<div>
	<div>
        <?php
        $linkText = __('Choose PickUp point', 'wc_colissimo');
        ?>
		<a id="lpc_pick_up_web_service_show_map" data-lpc-template="lpc_pick_up_web_service"
		   data-lpc-callback="lpcInitMapWebService"><?php echo $linkText; ?></a>
	</div>
    <?php $args['modal']->echo_modal(); ?>
</div>
