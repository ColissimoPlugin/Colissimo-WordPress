<?php

require_once LPC_INCLUDES . 'lpc_modal.php';

class LpcAdminPickupWebService extends LpcComponent {
    protected $ajaxDispatcher;

    public function __construct(
        LpcAjax $ajaxDispatcher = null
    ) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
    }

    public function getDependencies() {
        return ['ajaxDispatcher'];
    }

    public function init() {
        $this->ajaxDispatcher->register('pickupWS', [$this, 'pickupWS']);

        add_action('current_screen',
            function ($currentScreen) {
                if (is_admin() && 'post' === $currentScreen->base && 'shop_order' === $currentScreen->post_type) {
                    $args = [
                        'ajaxURL'   => $this->ajaxDispatcher->getUrlForTask('pickupWS'),
                        'mapType'   => LpcHelper::get_option('lpc_pickup_map_type', 'widget'),
                        'mapMarker' => plugins_url('/images/map_marker.png', LPC_INCLUDES . 'init.php'),
                    ];

                    LpcHelper::enqueueScript(
                        'lpc_admin_pick_up_ws',
                        plugins_url('/js/pickup/webservice.js', LPC_INCLUDES . 'init.php'),
                        null,
                        ['jquery'],
                        'lpcPickUpSelection',
                        $args
                    );

                    LpcHelper::enqueueStyle(
                        'lpc_admin_pick_up_ws',
                        plugins_url('/css/pickup/webservice.css', LPC_INCLUDES . 'init.php')
                    );
                }
            }
        );
    }

    public function addWebserviceMap(WC_Order $order) {
        $modal = new LpcModal(null, 'Choose a PickUp point', 'lpc_pick_up_web_service');

        $map = LpcHelper::renderPartial(
            'pickup' . DS . 'webservice_map.php',
            [
                'ceAddress'   => !empty($order->get_shipping_address_1()) ? $order->get_shipping_address_1() : '',
                'ceZipCode'   => !empty($order->get_shipping_postcode()) ? $order->get_shipping_postcode() : '',
                'ceTown'      => !empty($order->get_shipping_city()) ? $order->get_shipping_city() : '',
                'ceCountryId' => !empty($order->get_shipping_country()) ? $order->get_shipping_country() : '',
            ]
        );

        $modal->setContent($map);

        $args = [
            'modal'      => $modal,
            'apiKey'     => LpcHelper::get_option('lpc_gmap_key', ''),
            'type'       => 'link',
            'showButton' => true,
            'showInfo'   => false,
            'mapType'    => LpcHelper::get_option('lpc_pickup_map_type', 'leaflet'),
        ];

        return LpcHelper::renderPartial('pickup' . DS . 'webservice.php', $args);
    }

    public function pickupWS() {
        require_once LPC_INCLUDES . 'pick_up' . DS . 'lpc_relays_api.php';
        require_once LPC_INCLUDES . 'pick_up' . DS . 'lpc_generate_relays_payload.php';

        $address = [
            'address'     => LpcHelper::getVar('address'),
            'zipCode'     => LpcHelper::getVar('zipCode'),
            'city'        => LpcHelper::getVar('city'),
            'countryCode' => LpcHelper::getVar('countryId'),
        ];

        $generateRelaysPaypload = new LpcGenerateRelaysPayload();

        try {
            $generateRelaysPaypload
                ->withLogin()
                ->withPassword()
                ->withAddress($address)
                ->withShippingDate()
                ->withOptionInter()
                ->checkConsistency();

            $relaysApi = new LpcRelaysApi(['trace' => false]);

            $relaysPayload = $generateRelaysPaypload->assemble();

            $resultWs = $relaysApi->getRelays($relaysPayload);
        } catch (\SoapFault $fault) {
            return $this->ajaxDispatcher->makeAndLogError(['message' => $fault]);
        } catch (Exception $exception) {
            LpcLogger::error($exception->getMessage());

            return $this->ajaxDispatcher->makeError(['message' => $exception->getMessage()]);
        }

        $return = $resultWs->return;

        if (0 == $return->errorCode) {
            if (empty($return->listePointRetraitAcheminement)) {
                LpcLogger::warn(__('The web service returned 0 relay', 'wc_colissimo'));

                return $this->ajaxDispatcher->makeError(['message' => __('No relay available', 'wc_colissimo')]);
            }

            $listRelaysWS = $return->listePointRetraitAcheminement;
            $html         = '';

            $i           = 0;
            $partialArgs = [
                'relaysNb'    => count($listRelaysWS),
                'openingDays' => [
                    'Monday'    => 'horairesOuvertureLundi',
                    'Tuesday'   => 'horairesOuvertureMardi',
                    'Wednesday' => 'horairesOuvertureMercredi',
                    'Thursday'  => 'horairesOuvertureJeudi',
                    'Friday'    => 'horairesOuvertureVendredi',
                    'Saturday'  => 'horairesOuvertureSamedi',
                    'Sunday'    => 'horairesOuvertureDimanche',
                ],
            ];

            foreach ($listRelaysWS as $oneRelay) {
                $partialArgs['oneRelay'] = $oneRelay;
                $partialArgs['i']        = $i ++;

                $html .= LpcHelper::renderPartial('pickup' . DS . 'relay.php', $partialArgs);
            }

            return $this->ajaxDispatcher->makeSuccess(
                [
                    'html'                 => $html,
                    'chooseRelayText'      => __('Choose this relay', 'wc_colissimo'),
                    'confirmRelayText'     => __('Confirm relay', 'wc_colissimo'),
                    'confirmRelayDescText' => __('Do you confirm the shipment to this relay:', 'wc_colissimo'),
                ]
            );
        } else {
            if (in_array($return->errorCode, [301, 300, 203])) {
                LpcLogger::warn($return->errorCode . ' : ' . $return->errorMessage);

                return $this->ajaxDispatcher->makeError(['message' => __('No relay available', 'wc_colissimo')]);
            } else {
                // Error codes we want to display the related messages to the client, we'll only display a generic message for the other error codes
                $errorCodesWSClientSide = [
                    '104',
                    '105',
                    '117',
                    '125',
                    '129',
                    '143',
                    '144',
                    '145',
                    '146',
                ];

                if (in_array($return->errorCode, $errorCodesWSClientSide)) {
                    return $this->ajaxDispatcher->makeAndLogError(['message' => $return->errorCode . ' : ' . $return->errorMessage]);
                } else {
                    LpcLogger::error($return->errorCode . ' : ' . $return->errorMessage);

                    return $this->ajaxDispatcher->makeError(['message' => __('Error')]);
                }
            }
        }
    }
}
