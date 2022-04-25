<?php

require_once LPC_INCLUDES . 'lpc_rest_api.php';

class LpcLabelGenerationApi extends LpcRestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/sls-ws/SlsServiceWSRest/2.0/';

    protected function getApiUrl($action) {
        return self::API_BASE_URL . $action;
    }

    public function generateLabel(LpcLabelGenerationPayload $payload) {
        try {
            $assembledPayload = $payload->assemble();
            LpcLogger::debug(
                'Label generation request',
                [
                    'method'  => __METHOD__,
                    'payload' => $payload->getPayloadWithoutPassword(),
                ]
            );

            $response = $this->query(
                'generateLabel',
                $assembledPayload,
                self::DATA_TYPE_JSON
            );

            $jsonResponse = $response['<jsonInfos>'];

            LpcLogger::debug(
                'Label generation response',
                [
                    'method'   => __METHOD__,
                    'response' => $jsonResponse,
                ]
            );

            if (0 != $jsonResponse['messages'][0]['id']) {
                throw new Exception($jsonResponse['messages'][0]['messageContent'], $jsonResponse['messages'][0]['id']);
            }

            return $response;
        } catch (Exception $e) {
            $payloadWithoutPass = $assembledPayload;
            unset($payloadWithoutPass['password']);
            LpcLogger::error(
                'Error during label generation."',
                [
                    'payload'   => $payloadWithoutPass,
                    'exception' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    public function listMailBoxPickingDates(array $payload) {
        $payloadWithoutPass = $payload;
        unset($payloadWithoutPass['password']);

        LpcLogger::debug(
            'List mail box picking dates query',
            [
                'method'  => __METHOD__,
                'payload' => $payloadWithoutPass,
            ]
        );

        $response = $this->query('getListMailBoxPickingDates', $payload, self::DATA_TYPE_JSON);

        LpcLogger::debug(
            'List mail box picking dates response',
            [
                'method'   => __METHOD__,
                'response' => $response,
            ]
        );

        return $response;
    }

    public function planPickup(array $payload) {
        // __START__DEV
        return [
            'id'             => 0,
            'messageContent' => 'by-passed for tests',
            'type'           => 'INFOS',
        ];
        // __END__DEV

        $payloadWithoutPass = $payload;
        unset($payloadWithoutPass['password']);
        LpcLogger::debug(
            'Plan pickup query',
            [
                'method'  => __METHOD__,
                'payload' => $payloadWithoutPass,
            ]
        );

        $response = $this->query('planPickup', $payload);

        LpcLogger::debug(
            'Plan pickup response',
            [
                'method'   => __METHOD__,
                'response' => $response,
            ]
        );

        return $response;
    }
}
