<?php
require_once LPC_PUBLIC . 'pickup' . DS . 'lpc_pickup_selection.php';

class LpcAdminOrderAffect extends LpcComponent {

    protected $lpcShippingMethods;

    protected $lpcCapabilitiesByCountry;

    protected $lpcAdminPickupWebService;

    protected $lpcAdminPickupWidget;


    public function __construct(
        LpcShippingMethods $shippingMethods = null,
        LpcCapabilitiesPerCountry $capabilitiesPerCountry = null,
        LpcAdminPickupWebService $lpcAdminPickupWebService = null,
        LpcAdminPickupWidget $lpcAdminPickupWidget = null
    ) {
        $this->lpcShippingMethods       = LpcRegister::get('shippingMethods', $shippingMethods);
        $this->lpcCapabilitiesByCountry = LpcRegister::get('capabilitiesPerCountry', $capabilitiesPerCountry);
        if ('widget' === LpcHelper::get_option('lpc_pickup_map_type', 'widget')) {
            $this->lpcAdminPickupWidget = LpcRegister::get('adminPickupWidget', $lpcAdminPickupWidget);
        } else {
            $this->lpcAdminPickupWebService = LpcRegister::get('adminPickupWebService', $lpcAdminPickupWebService);
        }
    }

    public function init() {
        add_action('woocommerce_after_order_itemmeta', [$this, 'addAffectLink'], 10, 2);
        add_action('current_screen',
            function ($currentScreen) {
                if ('woocommerce_page_wc-orders' === $currentScreen->base || ('post' === $currentScreen->base && 'shop_order' === $currentScreen->post_type)) {
                    LpcHelper::enqueueScript(
                        'lpc_order_affect',
                        plugins_url('/js/orders/lpc_order_affect.js', LPC_ADMIN . 'init.php'),
                        null,
                        ['jquery-core']
                    );

                    LpcHelper::enqueueStyle(
                        'lpc_order_affect_methods',
                        plugins_url('/css/orders/lpc_order_affect_methods.css', LPC_ADMIN . 'init.php'),
                        null
                    );
                }
            }
        );

        add_action('wp_ajax_lpc_order_affect', [$this, 'updateShippingMethod']);
    }

    public function addAffectLink($itemId, $item) {
        if (empty($item) || $item->get_type() !== 'shipping') {
            return;
        }

        $order = $item->get_order();

        if (!empty($this->lpcShippingMethods->getColissimoShippingMethodOfOrder($order)) || !$order->is_editable()) {
            return;
        }

        $methods = $this->getColissimoShippingMethodsAvailable($order);

        $methods = array_map(
            function ($value) {
                return $value->get_method_title();
            },
            $methods
        );

        $args['lpc_shipping_methods'] = $methods;

        $args['link_choose_relay'] = 'widget' === LpcHelper::get_option('lpc_pickup_map_type', 'widget')
            ? $this->lpcAdminPickupWidget->addWidget($order)
            : $this->lpcAdminPickupWebService->addWebserviceMap($order);

        $args['lpc_partial_name'] = 'lpc_order_affect_methods_woocommerce';

        echo LpcHelper::renderPartial('orders' . DS . 'lpc_order_affect_methods.php', $args);
    }

    public function updateShippingMethod() {
        $orderId = LpcHelper::getVar('order_id');
        if (empty($orderId)) {
            LpcHelper::endAjax(false, ['message' => 'Order not found']);
        }

        $order = wc_get_order($orderId);
        if (empty($order) || 'shop_order' !== $order->get_type()) {
            LpcHelper::endAjax(false, ['message' => 'This post is not an order']);
        }

        $lpcNewShippingMethodId = LpcHelper::getVar('new_shipping_method');
        if (empty($lpcNewShippingMethodId)) {
            LpcHelper::endAjax(false, ['message' => __('Please select a shipping method', 'wc_colissimo')]);
        }

        $lpcMethods           = $this->getColissimoShippingMethodsAvailable($order);
        $lpcNewShippingMethod = $lpcMethods[$lpcNewShippingMethodId];

        if (empty($lpcNewShippingMethod)) {
            LpcHelper::endAjax(false, ['message' => __('Please select a shipping method', 'wc_colissimo')]);
        }

        if (LpcRelay::ID === $lpcNewShippingMethod->id) {
            $relayInformation = LpcHelper::getVar('relay_information');
            if (empty($relayInformation)) {
                LpcHelper::endAjax(false, ['message' => __('Please select a pick-up point', 'wc_colissimo')]);
            }

            $relayInformationData = json_decode(stripslashes($relayInformation));

            $order->update_meta_data(LpcPickupSelection::PICKUP_LOCATION_ID_META_KEY, $relayInformationData->identifiant);
            $order->update_meta_data(LpcPickupSelection::PICKUP_LOCATION_LABEL_META_KEY, $relayInformationData->nom);
            $order->update_meta_data(LpcPickupSelection::PICKUP_PRODUCT_CODE_META_KEY, $relayInformationData->typeDePoint);

            $order->set_shipping_address_1($relayInformationData->adresse1);
            $order->set_shipping_postcode($relayInformationData->codePostal);
            $order->set_shipping_city($relayInformationData->localite);
            $order->set_shipping_country($relayInformationData->codePays);
            $order->set_shipping_company($relayInformationData->nom);

            $order->save();
        }

        $orderShippingItemId = LpcHelper::getVar('shipping_item_id');
        if (empty($orderShippingItemId)) {
            $shippingItem = new WC_Order_Item_Shipping();
            $shippingItem->set_props(
                [
                    'method_id'    => $lpcNewShippingMethod->id,
                    'method_title' => $lpcNewShippingMethod->get_method_title(),
                ]
            );

            $order->add_item($shippingItem);
            $order->save();
        } else {
            $shippingItem = $order->get_item($orderShippingItemId);
            $shippingItem->set_props(
                [
                    'method_id'    => $lpcNewShippingMethod->id,
                    'method_title' => $lpcNewShippingMethod->get_method_title(),
                ]
            );
            $shippingItem->save();
        }

        LpcHelper::endAjax();
    }

    /**
     * Retrieve Colissimo shipping methods available for an order by country
     *
     * @param WC_Order $order
     *
     * @return array
     */
    public function getColissimoShippingMethodsAvailable(WC_Order $order) {
        $allShippingMethods                 = WC()->shipping() ? WC()->shipping()->load_shipping_methods() : [];
        $colissimoShippingMethodsPerCountry = $this->lpcCapabilitiesByCountry->getCapabilitiesForCountry($order->get_shipping_country());
        $methods                            = [];

        foreach ($allShippingMethods as $oneMethod) {
            $method = $this->lpcCapabilitiesByCountry->getCapabilitiesFileMethod($oneMethod->id);
            if (!empty($colissimoShippingMethodsPerCountry[$method])) {
                $methods[$oneMethod->id] = $oneMethod;
            }
        }

        return $methods;
    }
}
