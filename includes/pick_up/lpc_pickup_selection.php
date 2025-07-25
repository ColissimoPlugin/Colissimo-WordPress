<?php

class LpcPickupSelection extends LpcComponent {
    const AJAX_TASK_NAME = 'pickup_selection';
    const PICKUP_LOCATION_DATA_META_KEY = '_lpc_meta_pickUpLocationData';
    const PICKUP_LOCATION_ID_META_KEY = '_lpc_meta_pickUpLocationId';
    const PICKUP_LOCATION_LABEL_META_KEY = '_lpc_meta_pickUpLocationLabel';
    const PICKUP_PRODUCT_CODE_META_KEY = '_lpc_meta_pickUpProductCode';
    const PICKUP_LOCATION_SESSION_VAR_NAME = 'lpc_pickUpInfo';

    protected $ajaxDispatcher;

    public function __construct(?LpcAjax $ajaxDispatcher = null) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);

        add_filter('woocommerce_order_button_html', [$this, 'preventPlaceOrderButton'], 10, 2);
        add_action('woocommerce_checkout_process', [$this, 'preventCheckoutProcess']);
    }

    public function getDependencies(): array {
        return ['ajaxDispatcher'];
    }

    public function init() {
        $this->listenToPickUpSelection();
        $this->savePickUpSelectionOnOrderProcessed();
        $this->applyPickupAddress();
    }

    protected function listenToPickUpSelection() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'pickUpLocationListener']);
    }

    public function pickUpLocationListener() {
        $pickUpInfo = LpcHelper::getVar(self::PICKUP_LOCATION_SESSION_VAR_NAME, null, 'array');
        $this->setCurrentPickUpLocationInfo($pickUpInfo);

        return $this->ajaxDispatcher->makeSuccess(
            [
                'html' => LpcHelper::renderPartial(
                    'pickup' . DS . 'pick_up_info.php',
                    ['relay' => $pickUpInfo]
                ),
            ]
        );
    }

    public function getCurrentPickUpLocationInfo() {
        $pickUpInfo = WC()->session->get(self::PICKUP_LOCATION_SESSION_VAR_NAME);
        if (empty($pickUpInfo)) {
            $this->initSession();
            $pickUpInfo = $_SESSION[self::PICKUP_LOCATION_SESSION_VAR_NAME] ?? [];
        }

        return $pickUpInfo;
    }

    public function setCurrentPickUpLocationInfo($pickUpInfo, $orderId = null) {
        WC()->session->set(self::PICKUP_LOCATION_SESSION_VAR_NAME, $pickUpInfo);
        $this->initSession();
        $_SESSION[self::PICKUP_LOCATION_SESSION_VAR_NAME] = $pickUpInfo;

        $debug      = debug_backtrace();
        $stackTrace = [];
        foreach ($debug as $step) {
            if (empty($step['file']) || empty($step['line'])) {
                continue;
            }
            $stackTrace[] = $step['file'] . ' => ' . $step['line'];
        }

        LpcLogger::debug(
            'Changing the saved pickup data',
            [
                'orderId'    => $orderId,
                'pickupData' => $pickUpInfo,
                'saved'      => $this->getCurrentPickUpLocationInfo(),
                'stackTrace' => $stackTrace,
            ]
        );
    }

    private function initSession() {
        if (empty(session_id()) || session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    public function getAjaxUrl() {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME);
    }

    public function savePickUpSelectionOnOrderProcessed() {
        add_action('woocommerce_store_api_checkout_order_processed',
            function ($order) {
                $shippings = $order->get_shipping_methods();
                $shipping  = current($shippings);

                if (empty($shipping)) {
                    return;
                }

                $shippingMethod = $shipping->get_method_id();
                if (LpcRelay::ID === $shippingMethod) {
                    $this->setPickupAsShippingAddress($order);
                    $this->setCurrentPickUpLocationInfo(null, $order->get_id());
                }
            }
        );
        add_action(
            'woocommerce_checkout_order_processed',
            function ($orderId, $posted_data = []) {
                $order = wc_get_order($orderId);
                if (empty($order)) {
                    return;
                }

                $shippings = $order->get_shipping_methods();
                $shipping  = current($shippings);

                if (!empty($shipping)) {
                    $shippingMethod = $shipping->get_method_id();
                    if (LpcRelay::ID === $shippingMethod) {
                        $pickUpInfo = $this->getCurrentPickUpLocationInfo();
                        $this->updatePickupMeta($order, $pickUpInfo);
                        $this->setCurrentPickUpLocationInfo(null, $orderId);
                    }
                } elseif (!empty($posted_data['shipping_method'])) {
                    // When activating the synced renewal on a subscription product, for some reason the shipping info isn't on the order
                    $shippingMethod = array_pop($posted_data['shipping_method']);
                    if (strpos($shippingMethod, LpcRelay::ID) !== false) {
                        // The action woocommerce_checkout_order_created didn't update the shipping address so we do it here
                        $this->setPickupAsShippingAddress($order);
                        $this->setCurrentPickUpLocationInfo(null, $orderId);
                    }
                }
            },
            10,
            2
        );
    }

    private function updatePickupMeta($order, $pickUpInfo) {
        if (empty($order)) {
            LpcLogger::error('Order missing when trying to save selected pickup during a purchase');

            return;
        }

        if (empty($pickUpInfo['identifiant'])) {
            LpcLogger::error(
                'Pickup data missing when trying to save selected pickup during a purchase',
                [
                    'order'      => $order->get_id(),
                    'pickUpInfo' => $pickUpInfo,
                ]
            );

            return;
        }

        $order->update_meta_data(self::PICKUP_LOCATION_ID_META_KEY, $pickUpInfo['identifiant']);
        $order->update_meta_data(self::PICKUP_LOCATION_LABEL_META_KEY, $pickUpInfo['nom']);
        $order->update_meta_data(self::PICKUP_PRODUCT_CODE_META_KEY, $pickUpInfo['typeDePoint']);
        $order->update_meta_data(self::PICKUP_LOCATION_DATA_META_KEY, json_encode($pickUpInfo));
        $order->save();

        LpcLogger::debug('Saved pickup data on order ' . $order->get_id(), ['pickUpInfo' => $pickUpInfo]);
    }

    private function setPickupAsShippingAddress($order, $isSubOrder = false) {
        $pickupData = $this->getCurrentPickUpLocationInfo();

        if (empty($pickupData['adresse1']) || empty($pickupData['identifiant'])) {
            LpcLogger::error(
                'Could not save pickup data on order because the address was missing',
                [
                    'order'      => $order->get_id(),
                    'pickupData' => $pickupData,
                ]
            );

            return;
        } else {
            LpcLogger::debug(
                'Saving pickup data on order',
                [
                    'order'      => $order->get_id(),
                    'pickupData' => $pickupData,
                ]
            );
        }

        $this->updatePickupMeta($order, $pickupData);

        $order->set_shipping_address_1(!empty($pickupData['adresse1']) ? $pickupData['adresse1'] : '');
        $order->set_shipping_address_2(!empty($pickupData['adresse2']) ? $pickupData['adresse2'] : '');
        $order->set_shipping_postcode(!empty($pickupData['codePostal']) ? $pickupData['codePostal'] : '');
        $order->set_shipping_city(!empty($pickupData['localite']) ? $pickupData['localite'] : '');
        $order->set_shipping_country(!empty($pickupData['codePays']) ? $pickupData['codePays'] : '');
        $order->set_shipping_company(!empty($pickupData['nom']) ? $pickupData['nom'] : '');
        $order->set_shipping_state('');

        // To prevent Up2pay e-Transactions Crédit Agricole from messing with the shipping address
        $up2PayPlugin = 'e-transactions-wc/wc-etransactions.php';
        if (file_exists(WPMU_PLUGIN_DIR . DIRECTORY_SEPARATOR . $up2PayPlugin) || is_plugin_active($up2PayPlugin)) {
            $order->update_meta_data('wc_etransactions_original_shipping_address_1', $order->get_shipping_address_1());
            $order->update_meta_data('wc_etransactions_original_shipping_address_2', $order->get_shipping_address_2());
            $order->update_meta_data('wc_etransactions_original_shipping_city', $order->get_shipping_city());
            $order->update_meta_data('wc_etransactions_original_shipping_postcode', $order->get_shipping_postcode());
            $order->update_meta_data('wc_etransactions_original_shipping_company', $order->get_shipping_company());
        }

        $order->save();

        if (!$isSubOrder && class_exists('YITH_Vendors_Orders') && method_exists('YITH_Vendors_Orders', 'get_suborders')) {
            $subOrderIds = YITH_Vendors_Orders::get_suborders($order->get_id());
            if (!empty($subOrderIds)) {
                foreach ($subOrderIds as $subOrderId) {
                    $subOrder = wc_get_order($subOrderId);
                    $this->setPickupAsShippingAddress($subOrder, true);
                }
            }
        }
    }

    public function preventPlaceOrderButton($orderButton) {
        if (!$this->isRelayRequired()) {
            return $orderButton;
        }

        $relayInfo = $this->getCurrentPickUpLocationInfo();

        if (!empty($relayInfo['adresse1']) && !empty($relayInfo['identifiant'])) {
            return $orderButton;
        }

        $textButton = __('Please select a pick-up point', 'wc_colissimo');

        return '<button type="submit" class="button alt wp-element-button" name="woocommerce_checkout_place_order" id="place_order">' . $textButton . '</button>';
    }

    public function preventCheckoutProcess() {
        if (!$this->isRelayRequired()) {
            return;
        }

        $relayInfo = $this->getCurrentPickUpLocationInfo();

        if (empty($relayInfo['adresse1']) || empty($relayInfo['identifiant'])) {
            throw new Exception(__('Please select a pick-up point', 'wc_colissimo'));
        }

        $customerPhoneNumber = isset($_REQUEST['billing_phone']) ? sanitize_text_field(wp_unslash($_REQUEST['billing_phone'])) : '';

        // Even if we don't have a shipping phone natively in WooCommerce, we can check if a shipping phone exist if the billing one is empty
        // because a plugin or a theme can add it
        if (empty($customerPhoneNumber) && isset($_REQUEST['shipping_phone']) && !empty($_REQUEST['shipping_phone'])) {
            $customerPhoneNumber = sanitize_text_field(wp_unslash($_REQUEST['shipping_phone']));
        }

        $customerPhoneNumber     = str_replace(' ', '', $customerPhoneNumber);
        $wcSession               = WC()->session;
        $customerData            = $wcSession->get('customer');
        $customerShippingCountry = $customerData['shipping_country'];

        if (empty($customerPhoneNumber)) {
            throw new Exception(
                __(
                    'Please define a mobile phone number for SMS notification tracking',
                    'wc_colissimo'
                )
            );
        }

        if ('BE' !== $customerShippingCountry) {
            return;
        }

        if (!preg_match('/^\+324\d{8}$/', $customerPhoneNumber)) {
            $acceptableNumber = false;
        } else {
            $mobileNumbers = array_reverse(str_split($customerPhoneNumber));
            $mobileNumbers = array_map('intval', $mobileNumbers);
            $suiteAsc      = true;
            $suiteDesc     = true;
            $suiteEqual    = true;
            foreach ($mobileNumbers as $key => $val) {
                if (7 === $key) {
                    break;
                }

                if ($mobileNumbers[$key + 1] !== $val - 1) {
                    $suiteAsc = false;
                }
                if ($mobileNumbers[$key + 1] !== $val + 1) {
                    $suiteDesc = false;
                }
                if ($mobileNumbers[$key + 1] !== $val) {
                    $suiteEqual = false;
                }
            }

            $acceptableNumber = !$suiteAsc && !$suiteDesc && !$suiteEqual;
        }

        if (!$acceptableNumber) {
            throw new Exception(
                __(
                    'The mobile number for a Belgian destination must start with +324 and be 12 characters long. For example +324XXXXXXXX',
                    'wc_colissimo'
                )
            );
        }
    }

    public function applyPickupAddress() {
        add_action(
            'woocommerce_checkout_order_created',
            function ($order) {
                if (!$order->has_shipping_method('lpc_relay')) {
                    return;
                }

                $this->setPickupAsShippingAddress($order);
            }
        );
    }

    private function isRelayRequired(): bool {
        $wcSession      = WC()->session;
        $wcCart         = WC()->cart;
        $shippingMethod = $wcSession->get('chosen_shipping_methods');
        $needShipping   = $wcCart->needs_shipping();

        if (!$needShipping || empty($shippingMethod)) {
            return false;
        }

        $relayMethod = false;
        foreach ($shippingMethod as $oneMethod) {
            if (strpos($oneMethod, LpcRelay::ID) !== false) {
                $relayMethod = true;
            }
        }

        if (!$relayMethod) {
            return false;
        }

        return true;
    }
}
