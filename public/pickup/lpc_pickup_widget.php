<?php

require_once LPC_INCLUDES . 'pick_up' . DS . 'lpc_pick_up_widget_api.php';
require_once LPC_INCLUDES . 'lpc_modal.php';
require_once LPC_PUBLIC . 'pickup' . DS . 'lpc_pickup.php';

class LpcPickupWidget extends LpcPickup {
    const BASE_URL = 'https://ws.colissimo.fr';
    const MAP_JS_URL = 'https://api.mapbox.com/mapbox-gl-js/v2.6.1/mapbox-gl.js';
    const MAP_CSS_URL = 'https://api.mapbox.com/mapbox-gl-js/v2.6.1/mapbox-gl.css';
    const WEB_JS_URL = self::BASE_URL . '/widget-colissimo/js/jquery.plugin.colissimo.js';

    protected $modal;
    protected $pickUpWidgetApi;
    protected $lpcPickUpSelection;
    protected $lpcCapabilitiesPerCountry;
    protected $lpcPickupWebService;

    public function __construct(
        LpcPickUpWidgetApi $pickUpWidgetApi = null,
        LpcPickupSelection $lpcPickUpSelection = null,
        LpcCapabilitiesPerCountry $lpcCapabilitiesPerCountry = null,
        LpcPickupWebService $lpcPickupWebService = null
    ) {
        $this->pickUpWidgetApi           = LpcRegister::get('pickupWidgetApi', $pickUpWidgetApi);
        $this->lpcPickUpSelection        = LpcRegister::get('pickupSelection', $lpcPickUpSelection);
        $this->lpcCapabilitiesPerCountry = LpcRegister::get('capabilitiesPerCountry', $lpcCapabilitiesPerCountry);
        $this->lpcPickupWebService       = LpcRegister::get('pickupWebService', $lpcPickupWebService);
    }

    public function getDependencies() {
        return ['pickupWidgetApi', 'pickupSelection', 'capabilitiesPerCountry', 'pickupWebService'];
    }

    public function init() {
        if ('widget' === LpcHelper::get_option('lpc_pickup_map_type', 'widget')) {
            $this->addWidgetOnCart();
        }
    }

    protected function addWidgetOnCart() {
        $modalContent = '<div id="lpc_widget_container" class="widget_colissimo"></div>';
        $this->modal  = new LpcModal($modalContent, __('Choose a PickUp point', 'wc_colissimo'), 'lpc_pick_up_widget_container');

        add_action(
            'wp_enqueue_scripts',
            function () {
                if (is_checkout()) {
                    wp_register_script('lpc_mapbox', self::MAP_JS_URL, ['jquery'], '0.1', true);

                    wp_register_script('lpc_widgets_web_js_url', self::WEB_JS_URL, ['lpc_mapbox'], '0.1');

                    $args = [
                        'pickUpSelectionUrl' => $this->lpcPickUpSelection->getAjaxUrl(),
                    ];
                    wp_localize_script('lpc_widgets_web_js_url', 'lpcPickUpSelection', $args);

                    // This js file opens the modal and loads the widget when the user clicks on the "Select/change relay point" button
                    wp_register_script(
                        'lpc_widget',
                        plugins_url('/js/pickup/widget.js', LPC_INCLUDES . 'init.php'),
                        ['jquery-ui-autocomplete', 'lpc_widgets_web_js_url'],
                        LPC_VERSION,
                        true
                    );
                    wp_enqueue_script('lpc_widget');

                    wp_register_style('lpc_pickup_widget', plugins_url('/css/pickup/widget.css', LPC_INCLUDES . 'init.php'), [], LPC_VERSION);
                    wp_enqueue_style('lpc_pickup_widget');

                    wp_register_style('lpc_mapbox', self::MAP_CSS_URL, [], LPC_VERSION);
                    wp_enqueue_style('lpc_mapbox');

                    $this->modal->loadScripts();
                }
            }
        );

        add_action('woocommerce_after_shipping_rate', [$this, 'showWidgetInHooks']);
    }

    public function showWidgetInHooks($method, $index = 0) {
        if ($this->getMode($method->get_method_id(), $method->get_id()) !== self::WIDGET) {
            return;
        }

        $WcSession = WC()->session;
        $customer  = $WcSession->customer;

        $availableCountries = $this->getWidgetListCountry();
        if (empty($availableCountries)) {
            $availableCountries = ['FR'];
        }

        $widgetInfo = [
            'URLColissimo'      => self::BASE_URL,
            'ceLang'            => defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'FR',
            'ceCountryList'     => implode(',', $availableCountries),
            'ceAddress'         => $customer['shipping_address'],
            'ceZipCode'         => $customer['shipping_postcode'],
            'ceTown'            => $customer['shipping_city'],
            'ceCountry'         => $customer['shipping_country'],
            'token'             => $this->pickUpWidgetApi->authenticate(),
            'dyPreparationTime' => LpcHelper::get_option('lpc_preparation_time', 1),
            'dyWeight'          => '19000',
        ];

        if (LpcHelper::get_option('lpc_prCustomizeWidget', 'no') == 'yes') {
            $lpcAddressTextColor = LpcHelper::get_option('lpc_prAddressTextColor', null);
            if (!empty($lpcAddressTextColor)) {
                $widgetInfo['couleur1'] = $lpcAddressTextColor;
            }
            $lpcListTextColor = LpcHelper::get_option('lpc_prListTextColor', null);
            if (!empty($lpcListTextColor)) {
                $widgetInfo['couleur2'] = $lpcListTextColor;
            }

            $fontValue = LpcHelper::get_option('lpc_prDisplayFont', null);

            $fontNames = [
                'georgia'       => 'Georgia, serif',
                'palatino'      => '"Palatino Linotype", "Book Antiqua", Palatino, serif',
                'times'         => '"Times New Roman", Times, serif',
                'arial'         => 'Arial, Helvetica, sans-serif',
                'arialblack'    => '"Arial Black", Gadget, sans-serif',
                'comic'         => '"Comic Sans MS", cursive, sans-serif',
                'impact'        => 'Impact, Charcoal, sans-serif',
                'lucida'        => '"Lucida Sans Unicode", "Lucida Grande", sans-serif',
                'tahoma'        => 'Tahoma, Geneva, sans-serif',
                'trebuchet'     => '"Trebuchet MS", Helvetica, sans-serif',
                'verdana'       => 'Verdana, Geneva, sans-serif',
                'courier'       => '"Courier New", Courier, monospace',
                'lucidaconsole' => '"Lucida Console", Monaco, monospace',
            ];

            if (!empty($fontNames[$fontValue])) {
                $widgetInfo['font'] = $fontNames[$fontValue];
            }
        }

        $widgetInfo   = wp_json_encode($widgetInfo);
        $currentRelay = $this->lpcPickUpSelection->getCurrentPickUpLocationInfo();

        $address = [
            'address'     => $customer['shipping_address'],
            'zipCode'     => $customer['shipping_postcode'],
            'city'        => $customer['shipping_city'],
            'countryCode' => $customer['shipping_country'],
        ];
        if ('yes' === LpcHelper::get_option('lpc_select_default_pr', 'no')
            && empty($currentRelay)
            && count($address) == count(array_filter($address))) {
            $currentRelay = $this->lpcPickupWebService->getDefaultPickupLocationInfoWS($address, '1');
        }

        $args = [
            'widgetInfo'   => $widgetInfo,
            'modal'        => $this->modal,
            'currentRelay' => $currentRelay,
            'showButton'   => is_checkout(),
            'showInfo'     => true,
            'type'         => 'button',
        ];

        echo LpcHelper::renderPartial('pickup' . DS . 'widget.php', $args);
    }

    /**
     * Get list of enabled countries for relay method
     *
     * @return array
     */
    public function getWidgetListCountry() {
        // Get theoric countries available for relay method
        $countriesOfMethod = $this->lpcCapabilitiesPerCountry->getCountriesForMethod(LpcRelay::ID);

        // Get zones where relay method is enabled in configuration
        $allZones               = WC_Shipping_Zones::get_zones();
        $zonesWithMethodEnabled = [];
        foreach ($allZones as $oneZone) {
            foreach ($oneZone['shipping_methods'] as $oneMethod) {
                if (LpcRelay::ID === $oneMethod->id && 'yes' === $oneMethod->enabled) {
                    $zonesWithMethodEnabled[$oneZone['id']] = 1;
                    break;
                }
            }
        }
        $zoneIds = array_keys($zonesWithMethodEnabled);

        // Get country codes from both
        $countries = [];
        foreach ($zoneIds as $oneZone) {
            $currentZone = new WC_Shipping_Zone($oneZone);
            $zoneLoc     = $currentZone->get_zone_locations();
            foreach ($zoneLoc as $oneLoc) {
                if ('country' === $oneLoc->type && in_array($oneLoc->code, $countriesOfMethod)) {
                    $countries[] = $oneLoc->code;
                }
            }
        }

        return $countries;
    }
}
