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
        add_action('save_post', [$this, 'updateShippingMethod'], 10, 3);
        add_action('current_screen',
            function ($currentScreen) {
                if (is_admin() && 'post' === $currentScreen->base && 'shop_order' === $currentScreen->post_type) {
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

        echo LpcHelper::renderPartial('orders' . DS . 'lpc_order_affect_methods.php', $args);
    }

    public function updateShippingMethod($post_id, $post, $update) {
        $slug = 'shop_order';

        if (
            !is_admin()
            || $slug != $post->post_type
            || !isset($_REQUEST['lpc_order_affect_update_method'])
            || empty(sanitize_text_field(wp_unslash($_REQUEST['lpc_order_affect_update_method'])))
        ) {
            return;
        }

        $lpcNewShippingMethodId = isset($_REQUEST['lpc_new_shipping_method']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc_new_shipping_method'])) : '';
        $orderShippingItemId    = isset($_REQUEST['lpc_order_affect_shipping_item_id']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc_order_affect_shipping_item_id'])) : '';
        $relayInformation       = isset($_REQUEST['lpc_order_affect_relay_informations']) ? sanitize_text_field(wp_unslash($_REQUEST['lpc_order_affect_relay_informations'])) : '';
        $order                  = wc_get_order($post_id);

        if (empty($orderShippingItemId) || empty($order) || empty($lpcNewShippingMethodId)) {
            return;
        }

        $shippingItem         = $order->get_item($orderShippingItemId);
        $lpcMethods           = $this->getColissimoShippingMethodsAvailable($order);
        $lpcNewShippingMethod = $lpcMethods[$lpcNewShippingMethodId];

        if (empty($lpcNewShippingMethod) || (LpcRelay::ID === $lpcNewShippingMethod->id && empty($relayInformation))) {
            return;
        }

        if (LpcRelay::ID === $lpcNewShippingMethod->id) {
            $relayInformationData = json_decode(stripslashes($relayInformation));

            update_post_meta(
                $post_id,
                LpcPickupSelection::PICKUP_LOCATION_ID_META_KEY,
                $relayInformationData->identifiant
            );
            update_post_meta(
                $post_id,
                LpcPickupSelection::PICKUP_LOCATION_LABEL_META_KEY,
                $relayInformationData->nom
            );
            update_post_meta(
                $post_id,
                LpcPickupSelection::PICKUP_PRODUCT_CODE_META_KEY,
                $relayInformationData->typeDePoint
            );

            $order->set_shipping_address_1($relayInformationData->adresse1);
            $order->set_shipping_postcode($relayInformationData->codePostal);
            $order->set_shipping_city($relayInformationData->localite);
            $order->set_shipping_country($relayInformationData->codePays);
            $order->set_shipping_company($relayInformationData->nom);

            $order->save();
        }

        $shippingItem->set_props(
            [
                'method_id'    => $lpcNewShippingMethod->id,
                'method_title' => $lpcNewShippingMethod->get_method_title(),
            ]
        );

        $shippingItem->save();
    }

    /**
     * Retrieve Colissimo shipping methods avalaible for an order by country
     *
     * @param WC_Order $order
     *
     * @return array
     */
    protected function getColissimoShippingMethodsAvailable(WC_Order $order) {
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
