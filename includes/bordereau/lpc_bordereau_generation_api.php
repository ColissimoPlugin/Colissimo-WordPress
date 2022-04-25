<?php

require_once LPC_INCLUDES . 'lpc_soap_api.php';

class LpcBordereauGenerationApi extends LpcSoapApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/sls-ws/SlsServiceWS/2.0?wsdl';

    public function getApiUrl() {
        return self::API_BASE_URL;
    }

    public function generateBordereau(array $parcelNumbers) {
        $request = [
            'contractNumber'                    => LpcHelper::get_option('lpc_id_webservices'),
            'generateBordereauParcelNumberList' => $parcelNumbers,
        ];

        LpcLogger::debug(
            'Generate bordereau query',
            [
                'method'  => __METHOD__,
                'payload' => $request,
            ]
        );

        $request['password'] = LpcHelper::get_option('lpc_pwd_webservices');

        $response = $this->getSoapClient()->generateBordereauByParcelsNumbers($request)->return;

        LpcLogger::debug(
            'Generate bordereau response',
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
            throw new Exception('Error when generating bordereau: ' . $response->messages->messageContent);
        }

        return $response;
    }

    public function getBordereauByNumber($bordereauNumber) {
        $request = [
            'contractNumber'  => LpcHelper::get_option('lpc_id_webservices'),
            'bordereauNumber' => $bordereauNumber,
        ];

        LpcLogger::debug(
            'Get bordereau by number query',
            [
                'method'  => __METHOD__,
                'payload' => $request,
            ]
        );

        $request['password'] = LpcHelper::get_option('lpc_pwd_webservices');

        $response = $this->getSoapClient()->getBordereauByNumber($request)->return;

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
