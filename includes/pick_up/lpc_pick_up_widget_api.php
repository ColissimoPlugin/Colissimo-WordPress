<?php

require_once LPC_INCLUDES . 'lpc_rest_api.php';

class LpcPickUpWidgetApi extends LpcRestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/widget-colissimo/rest/';
    const OPTION_TOKEN_KEY = 'lpc_pickup_widget_token';
    const OPTION_TOKEN_KEY_EXPIRATION = 'lpc_pickup_widget_token_expiration';
    const PICKUP_WIDGET_TOKEN_VALIDITY = 1700;

    public string $token = '';

    protected function getApiUrl($action) {
        return self::API_BASE_URL . $action;
    }

    public function authenticate(bool $forceReload = false): string {
        $token           = LpcHelper::get_option(self::OPTION_TOKEN_KEY);
        $tokenExpiration = LpcHelper::get_option(self::OPTION_TOKEN_KEY_EXPIRATION, 0);

        if (!$forceReload && !empty($token) && (time() < (int) $tokenExpiration)) {
            $this->token = $token;

            return $this->token;
        }

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
                update_option(self::OPTION_TOKEN_KEY, $this->token);
                update_option(self::OPTION_TOKEN_KEY_EXPIRATION, time() + self::PICKUP_WIDGET_TOKEN_VALIDITY);
            }

            return $this->token;
        } catch (Exception $e) {
            LpcLogger::error('Error during authentication. Check your credentials."', ['message' => $e->getMessage()]);

            return '';
        }
    }
}
