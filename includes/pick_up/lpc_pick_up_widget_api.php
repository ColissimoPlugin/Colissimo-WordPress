<?php

require_once LPC_INCLUDES . 'lpc_rest_api.php';

class LpcPickUpWidgetApi extends LpcRestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/widget-colissimo/rest/';

    public $token = null;

    protected function getApiUrl($action) {
        return self::API_BASE_URL . $action;
    }

    public function authenticate() {
        try {
            if ('api_key' === LpcHelper::get_option('lpc_credentials_type', 'account')) {
                $credentials = [
                    'apikey' => LpcHelper::get_option('lpc_apikey'),
                ];
            } else {
                $credentials = [
                    'login'    => LpcHelper::get_option('lpc_id_webservices'),
                    'password' => LpcHelper::getPasswordWebService(),
                ];
            }

            $parentAccountId = LpcHelper::get_option('lpc_parent_account');
            if (!empty($parentAccountId)) {
                $credentials['partnerClientCode'] = $parentAccountId;
            }

            $response = $this->query('authenticate.rest', $credentials);

            LpcLogger::debug(
                'Widget authenticate response',
                [
                    'method'   => __METHOD__,
                    'response' => $response,
                ]
            );

            if (!empty($response['token'])) {
                $this->token = $response['token'];
            }

            return $this->token;
        } catch (Exception $e) {
            LpcLogger::error('Error during authentication. Check your credentials."', ['message' => $e->getMessage()]);

            return '';
        }
    }
}
