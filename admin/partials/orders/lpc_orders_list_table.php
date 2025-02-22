<?php
$lpc_orders_table = $args['table'] ?? [];
$get_args         = $args['get'] ?? [];
wp_nonce_field('wc_colissimo_view');
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lpc_orders_header.php';
?>
<div class="wrap">
    <?php
    $lpc_orders_table->prepare_items($get_args);
    $lpc_orders_table->displayHeaders();
    ?>
	<form method="get">
        <?php
        if (isset($_REQUEST['page'])) {
            ?>
			<input type="hidden" name="page"
				   value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_REQUEST['page']))); ?>" />
            <?php
        }
        $lpc_orders_table->search_box('search', 'search_id'); ?>
	</form>
	<form method="post">
        <?php
        if (isset($_REQUEST['page'])) {
            ?>
			<input type="hidden" name="page"
				   value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_REQUEST['page']))); ?>" />
            <?php
        }
        $lpc_orders_table->display();
        ?>
	</form>
</div>
