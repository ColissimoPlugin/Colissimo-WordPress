<?php
$lpc_orders_table_today = (isset($args['table_today'])) ? $args['table_today'] : [];
$lpc_orders_table_all   = (isset($args['table_all'])) ? $args['table_all'] : [];
$get_args               = (isset($args['get'])) ? $args['get'] : [];
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lpc_orders_header.php';
?>

<div class="wrap">
    <?php
    $lpc_orders_table_today->prepare_items($get_args);
    $lpc_orders_table_all->prepare_items($get_args);
    $lpc_orders_table_today->displayHeaders();
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
        ?>
		<div class="lpc_slip_creation_table_container">
			<div class="lpc_slip_creation_table_header">
				<h2><?php esc_html_e("Today's parcels", 'wc_colissimo'); ?></h2>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</div>
			<div class="lpc_slip_creation_table_listing" id="lpc_slip_creation_table_listing_today">
                <?php $lpc_orders_table_today->display(); ?>
			</div>
		</div>
		<div class="lpc_slip_creation_table_container">
			<div class="lpc_slip_creation_table_header">
				<h2><?php esc_html_e('Other parcels', 'wc_colissimo'); ?></h2>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</div>
			<div class="lpc_slip_creation_table_listing" id="lpc_slip_creation_table_listing_other">
                <?php $lpc_orders_table_all->display(); ?>
			</div>
		</div>
	</form>
</div>
