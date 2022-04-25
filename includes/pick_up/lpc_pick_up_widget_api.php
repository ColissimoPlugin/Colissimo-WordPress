<?php

require_once LPC_INCLUDES . 'lpc_rest_api.php';

class LpcPickUpWidgetApi extends LpcRestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/widget-colissimo/rest/';

    public $token = null;

    protected function getApiUrl($action) {
        return self::API_BASE_URL . $action;
    }

    public function authenticate($login = null, $password = null) {
        if (empty($login)) {
            $login = LpcHelper::get_option('lpc_id_webservices');
        }

        if (empty($password)) {
            $password = LpcHelper::get_option('lpc_pwd_webservices');
        }

        try {
            LpcLogger::debug(
                'Widget authenticate query',
                [
                    'method'  => __METHOD__,
                    'payload' => [
                        'login' => $login,
                        'url'   => $this->getApiUrl('authenticate.rest'),
                    ],
                ]
            );

            $response = $this->query(
                'authenticate.rest',
                [
                    'login'    => $login,
                    'password' => $password,
                ],
                self::DATA_TYPE_URL
            );

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
            LpcLogger::error('Error during authentication. Check your credentials."', ['exception' => $e]);

            return '';
        }
    }
}
