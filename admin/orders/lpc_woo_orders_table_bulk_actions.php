<?php

class LpcWooOrdersTableBulkActions extends LpcComponent {
    /** @var LpcCapabilitiesPerCountry */
    private $lpcCapabilitiesPerCountry;
    /** @var LpcAdminNotices */
    private $lpcAdminNotices;
    private $actions;

    public function __construct(
        LpcCapabilitiesPerCountry $lpcCapabilitiesPerCountry = null,
        LpcAdminNotices $lpcAdminNotices = null
    ) {
        $this->lpcCapabilitiesPerCountry = LpcRegister::get('capabilitiesPerCountry', $lpcCapabilitiesPerCountry);
        $this->lpcAdminNotices           = LpcRegister::get('lpcAdminNotices', $lpcAdminNotices);
    }

    public function getDependencies() {
        return ['capabilitiesPerCountry', 'lpcAdminNotices'];
    }

    public function init() {
        add_filter('bulk_actions-edit-shop_order', [$this, 'define_bulk_actions'], 20);
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_bulk_actions'], 10, 3);
        add_action('admin_notices', [$this, 'bulk_admin_notices']);
        add_action(
            'woocommerce_init',
            function () {
                $this->actions = [
                    'ship_lpc_nosign'     => [
                        'id'   => LpcNoSign::ID,
                        'name' => __('Colissimo without signature', 'wc_colissimo'),
                    ],
                    'ship_lpc_sign'       => [
                        'id'   => LpcSign::ID,
                        'name' => __('Colissimo with signature', 'wc_colissimo'),
                    ],
                    'ship_lpc_sign_ddp'   => [
                        'id'   => LpcSignDDP::ID,
                        'name' => __('Colissimo with signature - DDP option', 'wc_colissimo'),
                    ],
                    'ship_lpc_expert'     => [
                        'id'   => LpcExpert::ID,
                        'name' => __('Colissimo International', 'wc_colissimo'),
                    ],
                    'ship_lpc_expert_ddp' => [
                        'id'   => LpcExpertDDP::ID,
                        'name' => __('Colissimo International - DDP option', 'wc_colissimo'),
                    ],
                ];
            }
        );
    }

    public function define_bulk_actions($actions) {
        foreach ($this->actions as $one_action => $action_description) {
            $actions[$one_action] = sprintf(__('Ship with: %s', 'wc_colissimo'), $action_description['name']);
        }

        return $actions;
    }

    public function handle_bulk_actions($redirect_to, $action, $ids) {
        if (!in_array($action, array_keys($this->actions))) {
            return esc_url_raw($redirect_to);
        }

        /**
         * Filter on the order IDs passed to the hook
         *
         * @since 1.7.1
         */
        $ids                = apply_filters('woocommerce_bulk_action_ids', array_reverse(array_map('absint', $ids)), $action, 'order');
        $availableCountries = $this->lpcCapabilitiesPerCountry->getCountriesForMethod($this->actions[$action]['id']);
        $changed            = 0;

        foreach ($ids as $id) {
            $order              = wc_get_order($id);
            $orderShippingTotal = $order->get_shipping_total();
            $orderShippingTax   = $order->get_shipping_tax();

            // If the shipping country isn't allowed for this shipping method, add warning and skip it
            if (!in_array($order->get_shipping_country(), $availableCountries)) {
                $this->lpcAdminNotices->add_notice(
                    'shipment_change',
                    'notice-error',
                    sprintf(__('The order #%1$d cannot be shipped with %2$s', 'wc_colissimo'), $id, $this->actions[$action]['name'])
                );
                continue;
            }

            $orderShippingItems = $order->get_items('shipping');
            $previouslyRelay    = false;
            if (!empty($orderShippingItems)) {
                // If the order already has the same shipping method, skip it
                foreach ($orderShippingItems as $oneItem) {
                    $methodId = $oneItem->get_method_id();
                    if ($methodId === $this->actions[$action]['id']) {
                        continue 2;
                    }

                    if (LpcRelay::ID === $methodId) {
                        $previouslyRelay = true;
                    }
                }

                // Remove the old shipping method(s)
                foreach ($orderShippingItems as $oneItem) {
                    $order->remove_item($oneItem->get_id());
                }
            }

            if ($previouslyRelay) {
                $this->lpcAdminNotices->add_notice(
                    'shipment_change',
                    'notice-warning',
                    sprintf(
                        __('The order #%d was made to be shipped to a relay point, make sure to change its shipping address!', 'wc_colissimo'),
                        $id
                    )
                );
            }

            // Add the new shipping method
            $item = new WC_Order_Item_Shipping();
            $item->set_props(
                [
                    'method_title' => $this->actions[$action]['name'],
                    'method_id'    => $this->actions[$action]['id'],
                    'total'        => $orderShippingTotal,
                    'taxes'        => $orderShippingTax,
                ]
            );
            $order->add_item($item);
            $order->add_order_note(sprintf(__('Shipping method changed to %s with bulk edit.', 'wc_colissimo'), $this->actions[$action]['name']));
            $order->save();
            $changed ++;
        }

        $redirect_to = add_query_arg(
            [
                'post_type'   => 'shop_order',
                'bulk_action' => $action,
                'changed'     => $changed,
            ],
            $redirect_to
        );

        return esc_url_raw($redirect_to);
    }

    public function bulk_admin_notices() {
        global $post_type, $pagenow;

        // Bail out if not on shop order list page.
        if ('edit.php' !== $pagenow || 'shop_order' !== $post_type || !isset($_REQUEST['bulk_action']) || empty($_REQUEST['changed'])) {
            return;
        }

        $bulk_action = wc_clean(wp_unslash($_REQUEST['bulk_action']));
        if (false === strpos($bulk_action, 'ship_lpc_')) {
            return;
        }

        $number  = absint($_REQUEST['changed']);
        $message = sprintf(__('Shipping method changed to %1$s for %2$d orders.', 'wc_colissimo'), $this->actions[$bulk_action]['name'], number_format_i18n($number));
        echo '<div class="updated"><p>' . esc_html($message) . '</p></div>';
    }
}
