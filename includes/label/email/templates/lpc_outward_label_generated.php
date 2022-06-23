<?php
// for phpcs

/**
 * Action on the tracking email's header
 *
 * @since 1.6
 */
do_action('woocommerce_email_header', $email_heading, $email); ?>
	<p><?php printf(__('Hi %s,', 'wc_colissimo'), $order->get_billing_first_name()); ?></p>
	<p><?php printf(__('Your order #%s is being prepared and will soon be taken care of for shipping.', 'wc_colissimo'), $order->get_order_number()); ?></p>
<?php
$begining = __('You can follow up your order', 'wc_colissimo');
$linkText = __('here', 'wc_colissimo');
?>
	<p><?php printf('%s <a href="%s" target="_blank"> %s </a>', $begining, $tracking_link, $linkText); ?> </p>
	<p></p>
	<p>
        <?php echo sprintf(
            __('Tracking number: %s', 'wc_colissimo'),
            '<a target="_blank" href="' . esc_url($tracking_link) . '">' . get_post_meta($order->get_id(), 'lpc_outward_parcel_number', true) . '</a>'
        ); ?>
	</p>

	<p><?php echo __('Shipping address:', 'wc_colissimo') . '<br>' . $order->get_formatted_shipping_address(); ?></p>
	<p>
        <?php
        if (!empty($additional_content)) {
            echo wp_kses_post(wpautop(wptexturize($additional_content)));
        }
        ?>
	</p>
<?php
/**
 * Action on the tracking email's footer
 *
 * @since 1.6
 */
do_action('woocommerce_email_footer', $email);
