<?php
// for phpcs

/**
 * Action on the tracking email's header
 *
 * @since 1.6
 */
do_action('woocommerce_email_header', $email_heading, $email); ?>
	<p><?php printf(__('Hi %s,', 'wc_colissimo'), $order->get_billing_first_name()); ?></p>
	<p><?php printf(__('The inward label for order #%s has been generated.', 'wc_colissimo'), $order->get_order_number()); ?></p>
<?php if (!empty($additional_content)) { ?>
	<p></p>
	<p>
        <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
	</p>
<?php } ?>

<?php
/**
 * Action on the tracking email's footer
 *
 * @since 1.6
 */
do_action('woocommerce_email_footer', $email);
