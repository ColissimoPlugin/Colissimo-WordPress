<?php
echo '= ' . esc_html($email_heading) . " =\n\n";
echo sprintf(__('Hi %s,', 'wc_colissimo'), esc_html($order->get_billing_first_name())) . "\n\n";
echo sprintf(__('The inward label for order #%s has been generated.', 'wc_colissimo'), esc_html($order->get_order_number())) . "\n\n";

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
