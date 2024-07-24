<?php

class LpcBordereauGenerationApi extends LpcRestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/sls-ws/SlsServiceWSRest/2.0/';
    const SOAP_BASE_URL = 'https://ws.colissimo.fr/sls-ws/SlsServiceWS/2.0?wsdl';

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
        if ('api_key' === LpcHelper::get_option('lpc_credentials_type', 'account')) {
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

        $response = $response['<jsonInfos>'] ?? $response;

        LpcLogger::debug(
            'Generate bordereau response',
            [
                'method'   => __METHOD__,
                'response' => $response,
            ]
        );

        if (!isset($response['messages'][0]['id'])) {
            throw new Exception('Error when generating delivery slip.');
        }

        if (0 != $response['messages'][0]['id']) {
            LpcLogger::error(
                __METHOD__ . 'error in API response',
                ['response' => $response['messages']]
            );
            throw new Exception('Error when generating bordereau: ' . $response['messages']['messageContent']);
        }

        return $response;
    }

    public function getBordereauByNumber($bordereauNumber) {
        if (!class_exists('SoapClient')) {
            LpcLogger::error(
                __METHOD__ . ' SOAP extension not activated on the server'
            );
            throw new Exception('Please activate the SOAP extension on your server. If you don\'t know how to do it, you can ask your hosting provider.');
        }

        $request = [
            'bordereauNumber' => $bordereauNumber,
        ];

        LpcLogger::debug(
            'Get bordereau by number query',
            [
                'method'  => __METHOD__,
                'payload' => $request,
            ]
        );

        if ('api_key' === LpcHelper::get_option('lpc_credentials_type', 'account')) {
            // TODO make it work when available
            $request['stream_context'] = stream_context_create(
                [
                    'http' => [
                        'header' => 'token: ' . LpcHelper::get_option('lpc_apikey'),
                    ],
                ]
            );
        } else {
            $request['contractNumber'] = LpcHelper::get_option('lpc_id_webservices');
            $request['password']       = LpcHelper::getPasswordWebService();
        }

        // TODO use REST when available
        require_once LPC_FOLDER . 'lib' . DS . 'MTOMSoapClient.php';
        $soapClient = new KeepItSimple\Http\Soap\MTOMSoapClient(self::SOAP_BASE_URL, $request);
        $response   = $soapClient->getBordereauByNumber($request)->return;

        /*
        $parentAccountId = LpcHelper::get_option('lpc_parent_account');
        if (!empty($parentAccountId)) {
            $headers[] = 'partnerClientCode: ' . $parentAccountId;
        }

        $response = $this->query(
            'getBordereauByNumber',
            $request,
            self::DATA_TYPE_JSON,
            $headers
        );
        */
        LpcLogger::debug(
            'Get bordereau by number response',
            [
                'method'   => __METHOD__,
                'response' => $response->messages,
            ]
        );

        if (0 != $response->messages->id) {
            LpcLogger::error(
                __METHOD__ . 'error in API response',
                ['response' => $response->messages]
            );
            throw new Exception('Error in API response');
        }

        return $response;
    }
}
