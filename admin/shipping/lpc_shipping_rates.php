<?php

class LpcShippingRates extends LpcComponent {
    const AJAX_TASK_NAME_EXPORT = 'shipping/export';
    const AJAX_TASK_NAME_IMPORT = 'shipping/import';
    const AJAX_TASK_NAME_DEFAULT_PRICES = 'shipping/default_prices';

    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcShippingZones */
    protected $shippingZones;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcShippingZones $shippingZones = null
    ) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->shippingZones  = LpcRegister::get('shippingZones', $shippingZones);
    }

    public function getDependencies(): array {
        return ['ajaxDispatcher', 'shippingZones'];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME_EXPORT, [$this, 'export']);
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME_IMPORT, [$this, 'import']);
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME_DEFAULT_PRICES, [$this, 'defaultPrices']);
    }

    public function export() {
        $shippingMethod = WC_Shipping_Zones::get_shipping_method(LpcHelper::getVar('method_id'));

        $lines = [
            [
                'min_weight',
                'max_weight',
                'min_price',
                'max_price',
                'shipping_class',
                'price',
            ],
        ];

        $shippingRates = $shippingMethod->get_option('shipping_rates', []);
        foreach ($shippingRates as $rate) {
            $lines[] = [
                $rate['min_weight'],
                $rate['max_weight'] ?? '',
                $rate['min_price'],
                $rate['max_price'] ?? '',
                implode(' ', $rate['shipping_class']),
                $rate['price'],
            ];
        }

        $fileContent = '';

        foreach ($lines as $line) {
            $fileContent .= implode(',', $line);
            $fileContent .= "\r\n";
        }

        if (empty($shippingMethod->title)) {
            $filename = 'Export rates Colissimo ' . date('Y-m-d');
        } else {
            $filename = 'Export ' . $shippingMethod->title . ' ' . date('Y-m-d');
        }

        // Fix for IE catching
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

        // force download dialog
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');

        // Set file name and force the browser to display the save dialog
        header('Content-Disposition: attachment; filename=' . $filename . '.csv');
        header('Content-Transfer-Encoding: binary');
        echo $fileContent;
        exit;
    }

    public function import() {
        if (!isset($_FILES['lpc_shipping_rates_import']['name'])) {
            die(json_encode(
                [
                    'type'    => 'error',
                    'message' => __('File not found', 'wc_colissimo'),
                ])
            );
        }

        $uploadOverrides = [
            'test_form' => false,
            'mimes'     => ['csv' => 'text/csv'],
        ];

        $file = $_FILES['lpc_shipping_rates_import']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $file = wp_handle_upload($file, $uploadOverrides);

        if (isset($file['error'])) {
            die(json_encode(
                [
                    'type'    => 'error',
                    'message' => $file['error'],
                ])
            );
        }

        try {
            $fileContent = file_get_contents($file['file']);
        } catch (Exception $exception) {
            LpcLogger::error($exception->getMessage());
        }

        if (empty($fileContent)) {
            die(json_encode(
                [
                    'type'    => 'error',
                    'message' => __('The content of the file is empty', 'wc_colissimo'),
                ])
            );
        }

        $fileContent = str_replace("\r\n", "\n", $fileContent);
        $lines       = explode("\n", $fileContent);
        array_pop($lines);

        $headers = explode(',', array_shift($lines));

        $rates = [];
        foreach ($lines as $line) {
            $rate    = [];
            $newLine = explode(',', $line);
            foreach ($headers as $key => $header) {
                if ('shipping_class' === $header) {
                    $newValue = explode(' ', $newLine[$key]);
                } else {
                    $newValue = strlen($newLine[$key]) === 0 ? '' : (float) $newLine[$key];
                }
                $rate[$header] = $newValue;
            }
            $rates[] = $rate;
        }

        $shippingMethod = WC_Shipping_Zones::get_shipping_method(LpcHelper::getVar('method_id'));
        $optionName     = $shippingMethod->get_instance_option_key();
        $currentOptions = get_option($optionName, []);

        usort(
            $rates,
            function ($a, $b) {
                $result = 0;

                if ($a['price'] > $b['price']) {
                    $result = 1;
                } else {
                    if ($a['price'] < $b['price']) {
                        $result = - 1;
                    }
                }

                return $result;
            }
        );

        $currentOptions['shipping_rates'] = $rates;

        if (update_option($optionName, $currentOptions, false)) {
            die(json_encode(
                [
                    'type' => 'success',
                ])
            );
        }

        die(json_encode(
            [
                'type'    => 'error',
                'message' => __('Error while saving imported rates', 'wc_colissimo'),
            ])
        );
    }

    public function defaultPrices() {
        $response = [
            'type'    => 'error',
            'message' => '',
            'data'    => [],
        ];

        global $wpdb;
        $method = $wpdb->get_row(
            'SELECT zone_id, method_id 
            FROM ' . $wpdb->prefix . 'woocommerce_shipping_zone_methods 
            WHERE instance_id = ' . intval(LpcHelper::getVar('instance_id'))
        );

        if (empty($method)) {
            $response['message'] = __('Zone not found', 'wc_colissimo');
            echo json_encode($response);
            exit;
        }

        $currentZone = WC_Shipping_Zones::get_zone($method->zone_id);
        $zoneName    = $currentZone->get_zone_name();

        $zoneKey = null;

        $capabilities = LpcHelper::get_option('lpc_capabilities_per_country_fr', []);
        foreach ($capabilities as $key => $zone) {
            if ($zone['name'] === $zoneName) {
                $zoneKey = $key;
                break;
            }
        }

        if (empty($zoneKey)) {
            $currentZoneCountries = array_map(
                function ($country) {
                    return $country->code;
                },
                array_filter(
                    $currentZone->get_zone_locations(),
                    function ($location) {
                        return 'country' === $location->type;
                    }
                )
            );

            foreach ($capabilities as $key => $zone) {
                if (!empty(array_intersect($currentZoneCountries, array_keys($zone['countries'])))) {
                    $zoneKey = $key;
                    break;
                }
            }

            if (empty($zoneKey)) {
                $response['message'] = __('Current zone countries not eligible to Colissimo shipping', 'wc_colissimo');
                echo json_encode($response);
                exit;
            }
        }

        $defaultPrices = json_decode(
            file_get_contents($this->shippingZones::DEFAULT_PRICES_PER_ZONE_JSON_FILE),
            true
        );

        $methodKey = 'lpc_expert' === $method->method_id ? 'lpc_sign' : $method->method_id;
        if (empty($defaultPrices[$zoneKey][$methodKey])) {
            $response['message'] = __('This shipping method isn\'t available for this zone', 'wc_colissimo');
        } else {
            $response['type']           = 'success';
            $response['data']['prices'] = $defaultPrices[$zoneKey][$methodKey];
            $weightUnit                 = LpcHelper::get_option('woocommerce_weight_unit', 'kg');

            foreach ($response['data']['prices'] as $key => $price) {
                // Cast to string because PHP messes up the float values with json_encode
                $response['data']['prices'][$key]['weight_min'] = (string) wc_get_weight($price['weight_min'], $weightUnit, 'g');
                $response['data']['prices'][$key]['weight_max'] = (string) wc_get_weight($price['weight_max'], $weightUnit, 'g');
            }
        }
        echo json_encode($response);
        exit;
    }

    public function getUrlExport($shippingMethodId) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME_EXPORT) . '&method_id=' . $shippingMethodId;
    }

    public function getUrlImport($shippingMethodId) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME_IMPORT) . '&method_id=' . $shippingMethodId;
    }

    public function getUrlDefaultPrices($shippingMethodId) {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME_DEFAULT_PRICES) . '&instance_id=' . $shippingMethodId;
    }
}
