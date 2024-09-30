<?php

require_once LPC_INCLUDES . 'lpc_rest_api.php';

class LpcAccountApi extends LpcRestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/api-ewe/';
    const LPC_CONTRACT_TYPE_FACILITE = 'FACILITE';

    protected function getApiUrl($action) {
        return self::API_BASE_URL . $action;
    }

    public function getAutologinURLs(): array {
        $payload = [];
        if ('api_key' === LpcHelper::get_option('lpc_credentials_type', 'account')) {
            $payload['credential']['apiKey'] = LpcHelper::get_option('lpc_apikey');
        } else {
            $payload['credential']['login']    = LpcHelper::get_option('lpc_id_webservices');
            $payload['credential']['password'] = LpcHelper::getPasswordWebService();
        }

        $parentAccountId = LpcHelper::get_option('lpc_parent_account');
        if (!empty($parentAccountId)) {
            $payload['partnerClientCode'] = $parentAccountId;
        }

        try {
            $response = $this->query('v1/rest/urlCboxExt', $payload);

            if (!empty($response['messageErreur'])) {
                LpcLogger::error(
                    'Auto login request failed',
                    [
                        'method' => __METHOD__,
                        'error'  => $response['messageErreur'],
                    ]
                );

                return [];
            }
        } catch (Exception $e) {
            LpcLogger::error(
                'Auto login request failed',
                [
                    'method' => __METHOD__,
                    'error'  => $e->getMessage(),
                ]
            );

            return [];
        }

        return $response;
    }

    public function isCgvAccepted(): bool {
        $acceptedCgv = LpcHelper::get_option('lpc_accepted_cgv');

        if (!empty($acceptedCgv)) {
            return true;
        }

        // Get contract type
        $accountInformation = $this->getAccountInformation();

        // We couldn't get the account information, we can't check the CGV
        if (empty($accountInformation['contractType'])) {
            return true;
        }

        if (self::LPC_CONTRACT_TYPE_FACILITE !== $accountInformation['contractType'] || !empty($accountInformation['cgv']['accepted'])) {
            update_option('lpc_accepted_cgv', true, false);

            return true;
        }

        return false;
    }

    public function getAccountInformation(array $payload = []): array {
        if (empty($payload)) {
            if ('api_key' === LpcHelper::get_option('lpc_credentials_type', 'account')) {
                $payload['credential']['apiKey'] = LpcHelper::get_option('lpc_apikey');
            } else {
                $payload['credential']['login']    = LpcHelper::get_option('lpc_id_webservices');
                $payload['credential']['password'] = LpcHelper::getPasswordWebService();
            }

            $parentAccountId = LpcHelper::get_option('lpc_parent_account');
            if (!empty($parentAccountId)) {
                $payload['partnerClientCode'] = $parentAccountId;
            }
        }

        $payload['tagInfoPartner'] = 'WOOCOMMERCE';

        try {
            $response = $this->query('v1/rest/additionalinformations', $payload);

            if (!empty($response['messageErreur'])) {
                LpcLogger::error(
                    'Contract information request failed',
                    [
                        'method' => __METHOD__,
                        'error'  => $response['messageErreur'],
                    ]
                );

                return [];
            }
        } catch (Exception $e) {
            LpcLogger::error(
                'Contract information request failed',
                [
                    'method' => __METHOD__,
                    'error'  => $e->getMessage(),
                ]
            );

            return [];
        }

        LpcLogger::debug(
            'Getting contract information',
            [
                'method'   => __METHOD__,
                'response' => $response,
            ]
        );

        if (empty($response['cgv'])) {
            return [];
        }

        return $response;
    }
}
