<?php

require_once LPC_PUBLIC . 'pickup' . DS . 'lpc_pickup_selection.php';

class LpcCapabilitiesPerCountry extends LpcComponent {
    const PATH_TO_COUNTRIES_PER_ZONE_JSON_FILE_FR = LPC_FOLDER . 'resources' . DS . 'capabilitiesByCountryFR.json';
    const PATH_TO_COUNTRIES_PER_ZONE_JSON_FILE_DOM1 = LPC_FOLDER . 'resources' . DS . 'capabilitiesByCountryDOM1.json';
    const FROM_FR = 'fr';
    const FROM_DOM1 = 'dom1';
    const DOM1_COUNTRIES_CODE = ['BL', 'GF', 'GP', 'MQ', 'PM', 'RE', 'YT', 'MF'];
    const DOM2_COUNTRIES_CODE = ['NC', 'PF', 'TF', 'WF'];
    const FRANCE_COUNTRIES_CODE = ['FR', 'MC'];

    private $capabilitiesByCountry;
    private $shippingMethods;

    public function __construct(LpcShippingMethods $shippingMethods = null) {
        $this->shippingMethods = LpcRegister::get('shippingMethods', $shippingMethods);
    }

    public function getDependencies() {
        return ['shippingMethods'];
    }

    public function init() {
        // only at plugin installation
        register_activation_hook(
            LPC_FOLDER . 'index.php',
            function () {
                if (is_multisite()) {
                    global $wpdb;

                    foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
                        switch_to_blog($blog_id);
                        $this->saveCapabilitiesPerCountryInDatabase();
                        restore_current_blog();
                    }
                } else {
                    $this->saveCapabilitiesPerCountryInDatabase();
                }
            }
        );
    }

    public function saveCapabilitiesPerCountryInDatabase() {
        update_option('lpc_capabilities_per_country_fr', $this->getCountriesPerZone(self::FROM_FR), false);
        update_option('lpc_capabilities_per_country_dom1', $this->getCountriesPerZone(self::FROM_DOM1), false);

        delete_option('lpc_capabilities_per_country');
    }

    public function getCapabilitiesPerCountry($fromCountry = null) {
        if (is_null($fromCountry)) {
            $fromCountry = $this->getStoreCountryCode();
        }

        if (in_array($fromCountry, self::DOM1_COUNTRIES_CODE)) {
            return get_option('lpc_capabilities_per_country_dom1');
        }

        if (in_array($fromCountry, self::FRANCE_COUNTRIES_CODE)) {
            return get_option('lpc_capabilities_per_country_fr');
        }

        return [];
    }


    public function getCountriesPerZone($from = self::FROM_FR) {
        if (self::FROM_FR === $from) {
            return json_decode(
                file_get_contents(self::PATH_TO_COUNTRIES_PER_ZONE_JSON_FILE_FR),
                true
            );
        }

        if (self::FROM_DOM1 === $from) {
            return json_decode(
                file_get_contents(self::PATH_TO_COUNTRIES_PER_ZONE_JSON_FILE_DOM1),
                true
            );
        }
    }

    public function getCapabilitiesForCountry($countryCode) {
        if (empty($countryCode)) {
            return [];
        }

        if (null === $this->capabilitiesByCountry) {
            foreach ($this->getCapabilitiesPerCountry() as $zoneId => $zone) {
                foreach ($zone['countries'] as $countryId => $countryCapabilities) {
                    $this->capabilitiesByCountry[$countryId] = array_merge(
                        ['zone' => $zoneId],
                        $countryCapabilities
                    );
                }
            }
        }

        return !is_null($this->capabilitiesByCountry) ? $this->capabilitiesByCountry[$countryCode] : [];
    }

    public function getProductCodeForOrder(WC_Order $order) {
        $countryCode    = $order->get_shipping_country();
        $shippingMethod = $this->shippingMethods->getColissimoShippingMethodOfOrder($order);

        $productCode      = $this->getInfoForDestination($countryCode, $shippingMethod);
        $storeCountryCode = $this->getStoreCountryCode();

        if (true === $productCode) {
            switch ($shippingMethod) {
                case 'lpc_relay':
                    return get_post_meta($order->get_id(), LpcPickupSelection::PICKUP_PRODUCT_CODE_META_KEY, true);
                case 'lpc_expert_ddp':
                case 'lpc_expert':
                    return 'DOS';
                case 'lpc_sign_ddp':
                case 'lpc_sign':
                    if (in_array($countryCode, self::DOM1_COUNTRIES_CODE)) {
                        if ($this->isIntraDOM1($storeCountryCode, $countryCode)) {
                            return 'COL';
                        } else {
                            return 'CDS';
                        }
                    }

                    // We can't have another option because we only use "true" for lpc_sign for DOM1 destinations
                    break;

                case 'lpc_nosign':
                    if (in_array($countryCode, self::DOM1_COUNTRIES_CODE)) {
                        if ($this->isIntraDOM1($storeCountryCode, $countryCode)) {
                            return 'COLD';
                        } else {
                            return 'COM';
                        }
                    }

                    // We can't have another option because we only use "true" for lpc_nosign for DOM1 destinations
                    break;
            }
        }

        return $productCode;
    }

    public function getIsCn23RequiredForDestination($order) {
        $countryCode = $order->get_shipping_country();
        $stateCode   = $order->get_shipping_state();
        $zipCode     = $order->get_shipping_postcode();
        $city        = $order->get_shipping_city();

        // From DOM1 destinations, we don't need CN23 if we sent from and to the same island
        $storeCountryCode = $this->getStoreCountryCode();
        if (in_array($countryCode, self::DOM1_COUNTRIES_CODE) && $storeCountryCode == $countryCode) {
            return false;
        }

        // Ceuta, Las Palmas, Melilla, Santa Cruz de Tenerife
        if ('ES' === $countryCode && in_array($stateCode, ['CE', 'GC', 'ML', 'TF'])) {
            return true;
        }
        // Mount Athos
        if ('GR' === $countryCode && '63086' === $zipCode) {
            return true;
        }
        // Helgoland, Büsingen
        if ('DE' === $countryCode && in_array($zipCode, ['27498', '78266'])) {
            return true;
        }
        // Campione d'Italia
        if ('IT' === $countryCode && 'CO' === $stateCode && in_array($zipCode, ['22060', '22061']) && strpos(strtolower($city), 'campione') !== false) {
            return true;
        }
        // Livigno
        if ('IT' === $countryCode && 'SO' === $stateCode && in_array($zipCode, ['23030', '23041']) && strpos(strtolower($city), 'livigno') !== false) {
            return true;
        }

        return $this->getInfoForDestination($countryCode, 'cn23');
    }

    public function getFtdRequiredForDestination($countryCode) {
        return $this->getInfoForDestination($countryCode, 'ftd');
    }

    public function getInsuranceAvailableForDestination($countryCode) {
        return $this->getInfoForDestination($countryCode, 'insurance');
    }

    public function getReturnProductCodeForDestination($countryCode) {
        $storeCountryCode = $this->getStoreCountryCode();

        $returnProductCode = $this->getInfoForDestination($countryCode, 'return');

        if (true === $returnProductCode) {
            if (in_array($countryCode, self::DOM1_COUNTRIES_CODE) && $this->isIntraDOM1($storeCountryCode, $countryCode)) {
                return 'CORE';
            } else {
                return 'CORI';
            }
        }

        return $returnProductCode;
    }

    public function getInfoForDestination($countryCode, $info) {
        $productInfo = $this->getCapabilitiesForCountry($countryCode);

        if ('FR' !== $this->getStoreCountryCode()) {
            $productInfo[$this->getCapabilitiesFileMethod(LpcSignDDP::ID)]   = false;
            $productInfo[$this->getCapabilitiesFileMethod(LpcExpertDDP::ID)] = false;
        }

        $info = $this->getCapabilitiesFileMethod($info);

        return isset($productInfo[$info]) ? $productInfo[$info] : false;
    }

    /**
     * @param string $methodId
     *
     * @return string
     */
    public function getCapabilitiesFileMethod($methodId) {
        $methods = [
            LpcNoSign::ID    => 'domiciless',
            LpcSign::ID      => 'domicileas',
            LpcSignDDP::ID   => 'domicileasddp',
            LpcRelay::ID     => 'pr',
            LpcExpert::ID    => 'expert',
            LpcExpertDDP::ID => 'expertddp',
        ];

        return empty($methods[$methodId]) ? $methodId : $methods[$methodId];
    }

    /**
     * Get all countries available for a delivery method
     *
     * @param $method
     *
     * @return array
     */
    public function getCountriesForMethod($methodId) {
        $method = $this->getCapabilitiesFileMethod($methodId);

        $countriesOfMethod = [];
        $countriesPerZone  = $this->getCapabilitiesPerCountry();

        foreach ($countriesPerZone as &$oneZone) {
            foreach ($oneZone['countries'] as $countryCode => &$oneCountry) {
                if (false !== $oneCountry[$method]) {
                    $countriesOfMethod[] = $countryCode;
                }
            }
        }

        return $countriesOfMethod;
    }

    protected function getStoreCountryCode() {
        $country = LpcHelper::get_option('lpc_origin_address_country', '');
        if (!empty($country)) {
            return $country;
        }

        $storeCountryWithState = explode(':', WC_Admin_Settings::get_option('woocommerce_default_country'));

        return reset($storeCountryWithState);
    }


    /**
     * @param $storeCountryCode
     * @param $countryCode
     *
     * @return bool
     */
    protected function isIntraDOM1($storeCountryCode, $countryCode) {
        // For expedition between these destinations, Colissimo considers it as intra
        $intraCountryCodes = ['GP', 'MQ', 'MF', 'BL'];

        if ($storeCountryCode == $countryCode) {
            return true;
        }

        if (in_array($storeCountryCode, $intraCountryCodes) && in_array($countryCode, $intraCountryCodes)) {
            return true;
        }

        return false;
    }
}
