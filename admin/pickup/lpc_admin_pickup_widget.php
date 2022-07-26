<?php

require_once LPC_INCLUDES . 'pick_up' . DS . 'lpc_pick_up_widget_api.php';
require_once LPC_INCLUDES . 'lpc_modal.php';

class LpcAdminPickupWidget extends LpcComponent {
    const BASE_URL = 'https://ws.colissimo.fr';
    const MAP_JS_URL = 'https://api.mapbox.com/mapbox-gl-js/v2.6.1/mapbox-gl.js';
    const MAP_CSS_URL = 'https://api.mapbox.com/mapbox-gl-js/v2.6.1/mapbox-gl.css';
    const WEB_JS_URL = self::BASE_URL . '/widget-colissimo/js/jquery.plugin.colissimo.js';

    protected $pickUpWidgetApi;
    protected $lpcCapabilitiesPerCountry;

    public function __construct(
        LpcPickUpWidgetApi $pickUpWidgetApi = null,
        LpcCapabilitiesPerCountry $lpcCapabilitiesPerCountry = null
    ) {
        $this->pickUpWidgetApi           = LpcRegister::get('pickupWidgetApi', $pickUpWidgetApi);
        $this->lpcCapabilitiesPerCountry = LpcRegister::get('capabilitiesPerCountry', $lpcCapabilitiesPerCountry);
    }

    public function getDependencies() {
        return ['pickupWidgetApi', 'capabilitiesPerCountry'];
    }

    public function init() {
        add_action('current_screen',
            function ($currentScreen) {
                // Add scripts and styles only on the WC order in edition mode
                if (is_admin() && 'post' === $currentScreen->base && 'shop_order' === $currentScreen->post_type) {
                    // Mapbox scripts to display the map, needed by the Colissimo widget
                    LpcHelper::enqueueScript('lpc_mapbox', self::MAP_JS_URL, null, ['jquery']);
                    LpcHelper::enqueueStyle('lpc_mapbox', self::MAP_CSS_URL);

                    wp_register_script('lpc_widgets_web_js_url', self::WEB_JS_URL, ['lpc_mapbox'], '0.1', true);

                    // This js file opens the modal and loads the widget when the user clicks on the "Choose PickUp point" link
                    LpcHelper::enqueueScript(
                        'lpc_widget',
                        plugins_url('/js/pickup/widget.js', LPC_INCLUDES . 'init.php'),
                        null,
                        ['jquery-ui-autocomplete', 'lpc_widgets_web_js_url']
                    );

                    LpcHelper::enqueueStyle('lpc_pickup_widget', plugins_url('/css/pickup/widget.css', LPC_INCLUDES . 'init.php'));
                }
            }
        );
    }

    public function addWidget(WC_Order $order) {
        $availableCountries = $this->getWidgetListCountry();
        if (empty($availableCountries)) {
            $availableCountries = ['FR'];
        }

        $args = [];

        $args['widgetInfo'] =
            [
                'ceCountryList'     => implode(',', $availableCountries),
                'ceLang'            => defined('ICL_LANGUAGE_CODE') ? ICL_LANGUAGE_CODE : 'FR',
                'ceAddress'         => !empty($order->get_shipping_address_1()) ? $order->get_shipping_address_1() : '',
                'ceZipCode'         => !empty($order->get_shipping_postcode()) ? $order->get_shipping_postcode() : '',
                'ceTown'            => !empty($order->get_shipping_city()) ? $order->get_shipping_city() : '',
                'ceCountry'         => !empty($order->get_shipping_country()) ? $order->get_shipping_country() : '',
                'URLColissimo'      => self::BASE_URL,
                'token'             => $this->pickUpWidgetApi->authenticate(),
                'dyPreparationTime' => LpcHelper::get_option('lpc_preparation_time', 1),
                'dyWeight'          => '19000',
            ];

        if (LpcHelper::get_option('lpc_prCustomizeWidget', 'no') == 'yes') {
            $args['lpcAddressTextColor'] = LpcHelper::get_option('lpc_prAddressTextColor', null);
            if (!empty($args['lpcAddressTextColor'])) {
                $args['widgetInfo']['couleur1'] = $args['lpcAddressTextColor'];
            }
            $args['lpcListTextColor'] = LpcHelper::get_option('lpc_prListTextColor', null);
            if (!empty($args['lpcListTextColor'])) {
                $args['widgetInfo']['couleur2'] = $args['lpcListTextColor'];
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
                $args['widgetInfo']['font'] = $fontNames[$fontValue];
            }
        }

        $args['widgetInfo'] = wp_json_encode($args['widgetInfo']);

        $args['modal'] = new LpcModal(
            '<div id="lpc_widget_container" class="widget_colissimo"></div>', __('Choose a PickUp point', 'wc_colissimo'),
            'lpc_pick_up_widget_container'
        );

        $args['showButton'] = true;
        $args['showInfo']   = false;
        $args['type']       = 'link';

        return LpcHelper::renderPartial('pickup' . DS . 'widget.php', $args);
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
