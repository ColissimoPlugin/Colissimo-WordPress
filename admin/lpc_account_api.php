<?php

require_once LPC_INCLUDES . 'lpc_rest_api.php';

class LpcAccountApi extends LpcRestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/api-ewe/';
    const LPC_CONTRACT_TYPE_FACILITE = 'FACILITE';

    protected function getApiUrl($action) {
        return self::API_BASE_URL . $action;
    }

    public function isCgvAccepted(): bool {
        $acceptedCgv = LpcHelper::get_option('lpc_accepted_cgv');

        if (!empty($acceptedCgv)) {
            return true;
        }

        // Get contract type
        $accountInformation = $this->getCgvInformation();

        // We couldn't get the account information, we can't check the CGV
        if (empty($accountInformation)) {
            return true;
        }

        if (self::LPC_CONTRACT_TYPE_FACILITE !== $accountInformation['contractType'] || !empty($accountInformation['cgv']['accepted'])) {
            update_option('lpc_accepted_cgv', true);

            return true;
        }

        return false;
    }

    public function getCgvInformation() {
        $login    = LpcHelper::get_option('lpc_id_webservices');
        $password = LpcHelper::get_option('lpc_pwd_webservices');

        if (empty($login) || empty($password)) {
            return false;
        }

        $payload = [
            'credential' => [
                'login'    => $login,
                'password' => $password,
            ],
        ];

        try {
            $response = $this->query('v1/rest/additionalinformations', $payload);

            if (!empty($response['messageErreur'])) {
                LpcLogger::error(
                    'CGV information request failed',
                    [
                        'method' => __METHOD__,
                        'error'  => $response['messageErreur'],
                    ]
                );

                return false;
            }
        } catch (Exception $e) {
            LpcLogger::error(
                'CGV information request failed',
                [
                    'method' => __METHOD__,
                    'error'  => $e->getMessage(),
                ]
            );

            return false;
        }

        LpcLogger::debug(
            'Getting CGV information',
            [
                'method'   => __METHOD__,
                'response' => $response,
            ]
        );

        if (empty($response['contractType'])) {
            return false;
        }

        return $response;
    }
}
