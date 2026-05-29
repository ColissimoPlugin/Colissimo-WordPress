<?php
defined('ABSPATH') || die('Restricted Access');

class LpcBordereauGenerationApi extends LpcRestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/sls-ws/SlsServiceWSRest/3.1/';

    public function getApiUrl($action) {
        return self::API_BASE_URL . $action;
    }

    public function generateBordereau(array $parcelNumbers) {
        $parcelNumbersObject                 = new stdClass();
        $parcelNumbersObject->parcelsNumbers = $parcelNumbers;

        $request = [
            'generateBordereauParcelNumberList' => $parcelNumbersObject,
        ];

        LpcLogger::debug(
            'Generate bordereau query',
            [
                'method'  => __METHOD__,
                'payload' => $request,
            ]
        );

        $headers = [];
        if ('api_key' === LpcHelper::get_option('lpc_credentials_type', 'api_key')) {
            $headers[] = 'apiKey: ' . LpcHelper::get_option('lpc_apikey');
        } else {
            $request['contractNumber'] = LpcHelper::get_option('lpc_id_webservices');
            $request['password']       = LpcHelper::getPasswordWebService();
        }

        $response = $this->query(
            'generateBordereauByParcelsNumbers',
            $request,
            self::DATA_TYPE_JSON,
            $headers
        );

        $jsonResponse = $response['<jsonInfos>'] ?? [];

        LpcLogger::debug(
            'Generate bordereau response',
            [
                'method'   => __METHOD__,
                'response' => $jsonResponse,
            ]
        );

        if (!isset($jsonResponse['messages'][0]['id'])) {
            throw new Exception('Error when generating delivery slip.');
        }

        if (0 != $jsonResponse['messages'][0]['id']) {
            LpcLogger::error(
                __METHOD__ . 'error in API response',
                ['response' => $jsonResponse['messages']]
            );
            throw new Exception('Error when generating bordereau: ' . $jsonResponse['messages']['messageContent']);
        }

        return $response;
    }
}
