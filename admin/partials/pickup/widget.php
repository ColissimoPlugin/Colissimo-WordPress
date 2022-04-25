<script type="text/javascript">
    window.lpc_widget_info = <?php echo $args['widgetInfo']; ?>;
</script>

<?php $args['modal']->echo_modal(); ?>


<div>
    <?php
    $linkText = __('Choose PickUp point', 'wc_colissimo');
    ?>
	<a id="lpc_pick_up_widget_show_map" class="lpc_pick_up_widget_show_map"><?php echo $linkText; ?></a>
</div>
