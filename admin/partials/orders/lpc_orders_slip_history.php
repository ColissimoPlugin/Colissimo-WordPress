<?php
$lpc_slip_table = (isset($args['table'])) ? $args['table'] : [];
$get_args       = (isset($args['get'])) ? $args['get'] : [];
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lpc_orders_header.php';
?>

<div class="wrap">
    <?php
    $lpc_slip_table->prepare_items($get_args);
    ?>
	<form method="get">
        <?php
        if (isset($_REQUEST['page'])) {
            ?>
			<input type="hidden" name="page"
				   value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_REQUEST['page']))); ?>" />
        <?php } ?>
	</form>
	<form method="post">
		<input type="hidden" name="action">
        <?php
        if (isset($_REQUEST['page'])) {
            ?>
			<input type="hidden" name="page"
				   value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_REQUEST['page']))); ?>" />
            <?php
        }
        $lpc_slip_table->display();
        ?>
	</form>
</div>
