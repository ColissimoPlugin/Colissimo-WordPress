<?php

class LpcShippingZones extends LpcComponent {
    const UNKNOWN_WC_COUNTRIES = ['AN', 'IC', 'XZ'];
    const DEFAULT_PRICES_PER_ZONE_JSON_FILE = LPC_FOLDER . 'resources' . DS . 'defaultPrices.json';

    private $addCustomZonesDone = false;
    protected $lpcCapabilitiesPerCountry;

    public function __construct(?LpcCapabilitiesPerCountry $lpcCapabilitiesPerCountry = null) {
        $this->lpcCapabilitiesPerCountry = LpcRegister::get('capabilitiesPerCountry', $lpcCapabilitiesPerCountry);
    }

    public function getDependencies(): array {
        return ['capabilitiesPerCountry'];
    }

    public function init() {
        // only at plugin installation
        register_activation_hook(
            LPC_FOLDER . 'index.php',
            function () {
                $this->addCustomZonesOrUpdateOne();
            }
        );
    }

    public function addCustomZonesOrUpdateOne($zoneName = '') {
        if ($this->addCustomZonesDone) {
            return;
        }

        $currentZones = [];
        foreach (WC_Shipping_Zones::get_zones() as $zone) {
            $currentZones[$zone['zone_name']] = $zone;
        }

        $defaultPrices = json_decode(
            file_get_contents(self::DEFAULT_PRICES_PER_ZONE_JSON_FILE),
            true
        );

        foreach ($this->lpcCapabilitiesPerCountry->getCapabilitiesPerCountry() as $zoneCode => $zoneDefinition) {
            if (!empty($zoneName) && $zoneDefinition['name'] !== $zoneName) {
                continue;
            }

            $countries       = [];
            $shippingMethods = [];
            foreach ($zoneDefinition['countries'] as $countryCode => $countryDefinition) {
                $countries[] = $countryCode;

                if (!empty($countryDefinition['domiciless'])) {
                    $shippingMethods['lpc_nosign'] = true;
                }
                if (!empty($countryDefinition['domicileas'])) {
                    $shippingMethods['lpc_sign'] = true;
                }
                if (!empty($countryDefinition['domicileasddp'])) {
                    $shippingMethods['lpc_sign_ddp'] = true;
                }
                if (!empty($countryDefinition['pr'])) {
                    $shippingMethods['lpc_relay'] = true;
                }
            }

            $this->addCustomZone(
                $zoneDefinition['name'],
                $countries,
                array_keys($shippingMethods),
                $currentZones,
                empty($defaultPrices[$zoneCode]) ? [] : $defaultPrices[$zoneCode]
            );
        }

        $this->addCustomZonesDone = true;
    }

    protected function addCustomZone($zoneName, array $countries, array $shippingMethods, array $currentZones, array $defaultPrices) {
        global $wpdb;

        $newZone = null;
        if (!empty($currentZones[$zoneName])) {
            $newZone = $currentZones[$zoneName];
        }
        if (empty($newZone['id'])) {
            $newZone = new WC_Shipping_Zone();
        } else {
            $newZone = WC_Shipping_Zones::get_zone($newZone['id']);
        }

        $newZone->set_zone_name($zoneName);

        $existingZoneLocations = array_map(
            fn($v) => $v->code,
            array_filter(
                $newZone->get_zone_locations(),
                fn($v) => 'country' === $v->type
            )
        );
        foreach ($countries as $country) {
            if (!in_array($country, self::UNKNOWN_WC_COUNTRIES)) {
                if (!in_array($country, $existingZoneLocations)) {
                    $newZone->add_location($country, 'country');
                }
            }
        }

        $existingShippingMethods = array_map(
            fn($v) => $v->id,
            $newZone->get_shipping_methods()
        );

        $weightUnit = LpcHelper::get_option('woocommerce_weight_unit', 'kg');
        foreach ($shippingMethods as $shippingMethod) {
            if (!in_array($shippingMethod, $existingShippingMethods)) {
                $shippingMethodInstanceId = $newZone->add_shipping_method($shippingMethod);

                $wpdb->update(
                    "{$wpdb->prefix}woocommerce_shipping_zone_methods",
                    [
                        'is_enabled' => false,
                    ],
                    [
                        'instance_id' => $shippingMethodInstanceId,
                    ]
                );

                // Add default prices to the shipping method we just added
                if (!empty($defaultPrices[$shippingMethod])) {
                    $optionValues = ['shipping_rates' => []];
                    foreach ($defaultPrices[$shippingMethod] as $onePrice) {
                        $optionValues['shipping_rates'][] = [
                            'min_weight'       => wc_get_weight($onePrice['weight_min'], $weightUnit, 'g'),
                            'max_weight'       => wc_get_weight($onePrice['weight_max'], $weightUnit, 'g'),
                            'min_price'        => 0,
                            'shipping_class'   => [0 => 'all'],
                            'product_category' => [0 => 'all'],
                            'price'            => $onePrice['price'],
                        ];
                    }

                    update_option('woocommerce_' . $shippingMethod . '_' . $shippingMethodInstanceId . '_settings', $optionValues);
                }
            }
        }

        $newZone->save();
    }
}
