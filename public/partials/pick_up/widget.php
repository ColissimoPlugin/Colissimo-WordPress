<script type="text/javascript">
    window.lpc_widget_info = <?php echo $widgetInfo; ?>;
</script>

<?php $this->modal->echo_modal(); ?>


<?php if (is_checkout()) { ?>
	<div id="lpc_layer_error_message"></div>
    <?php echo LpcHelper::renderPartial('pick_up' . DS . 'pick_up_info.php', ['relay' => $currentRelay]); ?>
	<div>
        <?php
        if (!empty($currentRelay)) {
            $linkText = __('Change PickUp point', 'wc_colissimo');
        } else {
            $linkText = __('Choose PickUp point', 'wc_colissimo');
        }
        ?>
		<button type="button" id="lpc_pick_up_widget_show_map" class="lpc_pick_up_widget_show_map"><?php echo $linkText; ?></button>
	</div>
<?php } ?>
