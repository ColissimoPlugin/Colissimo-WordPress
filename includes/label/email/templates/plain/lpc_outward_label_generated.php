<?php
$shipping_address2 = '';
if (!empty($order->get_shipping_address_2())) {
    $shipping_address2 = $order->get_shipping_address_2() . "\n";
}
echo '= ' . esc_html($email_heading) . " =\n\n";
echo sprintf(__('Hi %s,', 'wc_colissimo'), esc_html($order->get_billing_first_name())) . "\n\n";
echo sprintf(__('Your order #%s is being prepared and will soon be taken care of for shipping.', 'wc_colissimo'), esc_html($order->get_order_number())) . "\n\n";
echo sprintf(__('You can follow up your order here:', 'wc_colissimo')) . ' ' . $tracking_link . "\n\n";
echo sprintf(__('Tracking number: %s', 'wc_colissimo'), get_post_meta($order->get_id(), 'lpc_outward_parcel_number', true)) . "\n\n";
echo __('Shipping address:', 'wc_colissimo') . "\n" . $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() .
     "\n" . $order->get_shipping_address_1() . "\n" . $shipping_address2 . $order->get_shipping_postcode() . ' ' . $order->get_shipping_city()
     . "\n" . WC()->countries->countries[$order->get_shipping_country()] . "\n\n";

if (!empty($additional_content)) {
    echo "\n\n";
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n\n";
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/**
 * Action on the tracking email's footer (text version)
 *
 * @since 1.6
 */
echo esc_html(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
