<?php

require_once LPC_INCLUDES . 'lpc_rest_api.php';

class LpcRelaysApi extends LpcRestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/pointretrait-ws-cxf/rest/v2/pointretrait/';

    public function getApiUrl($action) {
        return self::API_BASE_URL . $action;
    }

    public function getRelays($payload) {
        $paramsWithoutPassword = $payload;
        unset($paramsWithoutPassword['password']);

        LpcLogger::debug(
            'Get relays webservice query',
            [
                'method'  => __METHOD__,
                'payload' => $paramsWithoutPassword,
                'url'     => $this->getApiUrl('findRDVPointRetraitAcheminement'),
            ]
        );

        $response = $this->query('findRDVPointRetraitAcheminement', $payload);

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
