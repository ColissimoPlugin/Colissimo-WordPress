<?php

require_once LPC_INCLUDES . 'lpc_soap_api.php';

class LpcRelaysApi extends LpcSoapApi {
    const API_RELAYS_WSDL_URL = 'https://ws.colissimo.fr/pointretrait-ws-cxf/PointRetraitServiceWS/2.0?wsdl';

    public function getApiUrl() {
        return self::API_RELAYS_WSDL_URL;
    }

    public function getRelays($params) {
        $paramsWithoutPassword = $params;

        unset($paramsWithoutPassword['password']);

        LpcLogger::debug(
            'Get relays webservice query',
            [
                'method'  => __METHOD__,
                'payload' => $paramsWithoutPassword,
                'url'     => $this->getApiUrl(),
            ]
        );

        $response = $this->getSoapClient()->findRDVPointRetraitAcheminement($params);

        LpcLogger::debug(
            'Get relays webservice response',
            [
                'method'   => __METHOD__,
                'response' => $response,
            ]
        );

        return $response;
    }
}
