<?php

require_once LPC_INCLUDES . 'lpc_modal.php';
require_once LPC_PUBLIC . 'pickup' . DS . 'lpc_pickup.php';

class LpcPickupWebService extends LpcPickup {
    protected $modal;
    protected $ajaxDispatcher;
    protected $lpcPickUpSelection;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcPickupSelection $lpcPickUpSelection = null
    ) {
        $this->ajaxDispatcher     = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->lpcPickUpSelection = LpcRegister::get('pickupSelection', $lpcPickUpSelection);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'pickupSelection'];
    }

    public function init() {
        if ('no' === LpcHelper::get_option('lpc_prUseWebService', 'no')) {
            return;
        }

        $this->modal = new LpcModal(null, 'Choose a PickUp point', 'lpc_pick_up_web_service');

        $this->ajaxDispatcher->register('pickupWS', [$this, 'pickupWS']);

        add_action(
            'wp_enqueue_scripts',
            function () {
                if (is_checkout()) {
                    wp_register_script('lpc_pick_up_ws', plugins_url('/js/pickup/webservice.js', LPC_INCLUDES . 'init.php'), ['jquery'], LPC_VERSION, true);

                    $args = [
                        'ajaxURL'            => $this->ajaxDispatcher->getUrlForTask('pickupWS'),
                        'pickUpSelectionUrl' => $this->lpcPickUpSelection->getAjaxUrl(),
                    ];

                    wp_localize_script('lpc_pick_up_ws', 'lpcPickUpSelection', $args);

                    wp_register_style('lpc_pick_up_ws', plugins_url('/css/pickup/webservice.css', LPC_INCLUDES . 'init.php'), [], LPC_VERSION);

                    wp_enqueue_script('lpc_pick_up_ws');
                    wp_enqueue_style('lpc_pick_up_ws');
                    $this->modal->loadScripts();
                }
            }
        );

        add_action('woocommerce_after_shipping_rate', [$this, 'addGoogleMaps']);
    }

    /**
     * Uses a WC hook to add a "Select pick up location" button on the checkout page
     *
     * @param     $method
     * @param int $index
     */
    public function addGoogleMaps($method, $index = 0) {
        if ($this->getMode($method->get_method_id(), $method->get_id()) !== self::WEB_SERVICE) {
            return;
        }

        $wcSession = WC()->session;
        $customer  = $wcSession->customer;

        $map = LpcHelper::renderPartial(
            'pick_up' . DS . 'webservice_map.php',
            [
                'ceAddress'   => $customer['shipping_address'],
                'ceZipCode'   => $customer['shipping_postcode'],
                'ceTown'      => $customer['shipping_city'],
                'ceCountryId' => $customer['shipping_country'],
            ]
        );
        $this->modal->setContent($map);

        $args = [
            'modal'        => $this->modal,
            'apiKey'       => LpcHelper::get_option('lpc_gmap_key', ''),
            'currentRelay' => $this->lpcPickUpSelection->getCurrentPickUpLocationInfo(),
        ];
        echo LpcHelper::renderPartial('pick_up' . DS . 'webservice.php', $args);
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
            $generateRelaysPaypload->withLogin()->withPassword()->withAddress($address)->withShippingDate()->withOptionInter()->checkConsistency();

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

                $html .= LpcHelper::renderPartial('pick_up/relay.php', $partialArgs);
            }

            return $this->ajaxDispatcher->makeSuccess(
                [
                    'html'                 => $html,
                    'chooseRelayText'      => __('Choose this relay', 'wc_colissimo'),
                    'confirmRelayText'     => __('Confirm relay', 'wc_colissimo'),
                    'confirmRelayDescText' => __('Do you confirm the shipment to this relay:', 'wc_colissimo'),
                ]
            );
        } elseif (in_array($return->errorCode, [301, 300, 203])) {
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
