<script type="text/javascript">
    window.lpc_widget_info = <?php echo $args['widgetInfo']; ?>;
</script>

<?php $args['modal']->echo_modal(); ?>


<?php if ($args['showButton']) { ?>
	<div id="lpc_layer_error_message"></div>
    <?php
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
        ?>
        <?php if ('link' === $args['type']) { ?>
			<a id="lpc_pick_up_widget_show_map" class="lpc_pick_up_widget_show_map"><?php echo $linkText; ?></a>
        <?php } else { ?>
			<button type="button" id="lpc_pick_up_widget_show_map" class="lpc_pick_up_widget_show_map"><?php echo $linkText; ?></button>
        <?php } ?>
	</div>
<?php } ?>
