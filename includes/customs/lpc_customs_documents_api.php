<?php


class LpcCustomsDocumentsApi extends LpcRestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/api-document/rest/';

    protected function getApiUrl($action) {
        return self::API_BASE_URL . $action;
    }

    /**
     * @param array  $orderLabels  All the labels and their type for the current order, for multi-parcels
     * @param string $documentType The type among the ones provided in the WS documentation (see lpc_admin_order_banner.php)
     * @param string $parcelNumber The label number
     * @param string $document     The "binary data" of the uploaded file according to the doc (@/tmp/xxxx.pdf in the examples)
     * @param string $documentName The uploaded file name for the error message
     *
     * @return string
     * @throws Exception When an error occurs.
     */
    public function storeDocument(array $orderLabels, string $documentType, string $parcelNumber, string $document, string $documentName): string {
        $accountNumber = LpcHelper::get_option('lpc_id_webservices');
        $login         = LpcHelper::get_option('lpc_id_webservices');
        $password      = LpcHelper::get_option('lpc_pwd_webservices');

        if (function_exists('curl_file_create')) {
            $document         = curl_file_create($document, mime_content_type($document), $documentName);
            $unsafeFileUpload = false;
        } else {
            $document         = '@' . realpath($document);
            $unsafeFileUpload = true;
        }

        $payload = [
            'accountNumber' => $accountNumber,
            'parcelNumber'  => $parcelNumber,
            'documentType'  => $documentType,
            'file'          => $document,
            'filename'      => $parcelNumber . '-' . $documentType . '.' . pathinfo($documentName, PATHINFO_EXTENSION),
        ];

        // If it is a master parcel, add the follower parcels tracking numbers
        if (!empty($orderLabels[$parcelNumber]) && 'MASTER' === $orderLabels[$parcelNumber]) {
            $followerParcels = [];
            foreach ($orderLabels as $label => $type) {
                if ('FOLLOWER' === $type) {
                    $followerParcels[] = $label;
                }
            }
            $payload['parcelNumberList'] = implode(',', $followerParcels);
        }

        LpcLogger::debug(
            'Customs Documents Sending Request',
            [
                'method'  => __METHOD__,
                'payload' => $payload,
            ]
        );

        $credentials = ['login: ' . $login, 'password: ' . $password];

        try {
            $response = $this->query('storedocument', $payload, self::DATA_TYPE_MULTIPART, $credentials, true, $unsafeFileUpload);

            LpcLogger::debug(
                'Customs Documents Sending Response',
                [
                    'method'   => __METHOD__,
                    'response' => $response,
                ]
            );

            if ('000' != $response['errorCode']) {
                throw new Exception($response['errors']['code'] . ' - ' . $response['errorLabel'] . ': ' . $response['errors']['message']);
            }

            // 50c82f93-015f-3c41-a841-07746eee6510.pdf for example, where 50c82f93-015f-3c41-a841-07746eee6510 is the uuid
            return $response['documentId'];
        } catch (Exception $e) {
            $message = [$e->getMessage()];

            if (!empty($this->lastResponse)) {
                $this->lastResponse = json_decode($this->lastResponse, true);
                if (!empty($this->lastResponse['errors'])) {
                    foreach ($this->lastResponse['errors'] as $oneError) {
                        $message[] = $oneError['code'] . ': ' . $oneError['message'];
                    }
                }
            }

            LpcLogger::error(
                'Error during customs documents sending',
                [
                    'payload'   => $payload,
                    'login'     => $login,
                    'exception' => implode(', ', $message),
                ]
            );

            if (1 < count($message)) {
                array_shift($message);
            }

            throw new Exception(sprintf(__('An error occurred when transmitting the file %1$s: %2$s', 'wc_colissimo'), $documentName, implode(', ', $message)));
        }
    }

    /**
     * @param string $parcelNumber
     *
     * @return array
     */
    public function getDocuments($parcelNumber) {
        $login    = LpcHelper::get_option('lpc_id_webservices');
        $password = LpcHelper::get_option('lpc_pwd_webservices');

        $payload = [
            'credential' => [
                'login'    => $login,
                'password' => $password,
            ],
            'cab'        => $parcelNumber,
        ];

        LpcLogger::debug(
            'Customs Documents Get Request',
            [
                'method'  => __METHOD__,
                'payload' => $payload,
            ]
        );

        try {
            $response = $this->query(
                'documents',
                $payload,
                self::DATA_TYPE_JSON,
                [],
                false,
                false,
                false
            );

            LpcLogger::debug(
                'Customs Documents Get Response',
                [
                    'method'   => __METHOD__,
                    'response' => $response,
                ]
            );

            if (!in_array($response['errorCode'], ['000', '003'])) {
                return [
                    'status'  => 'error',
                    'message' => $response['errorCode'] . ' - ' . $response['errorLabel'],
                ];
            }

            // {
            // "errorCode": "000",
            // "errorLabel": "OK",
            // "documents": [
            // {
            // "documentType": "CN23",
            // "path": "/uds/e35f56ae-e1f2-3d3d-9901-347c5d1d1b88.pdf",
            // "uuid": "912d976c-30e1-3c3a-b24d-dd9420d48a52",
            // "cab": "8Q53782186134"
            // }
            // ]
            // }
            return $response;
        } catch (Exception $e) {
            LpcLogger::error(
                'Error during customs documents get',
                [
                    'payload'   => $payload,
                    'login'     => $login,
                    'exception' => $e->getMessage(),
                ]
            );

            return [
                'status'  => 'error',
                'message' => sprintf(__('An error occurred while getting the customs files for this order: %s', 'wc_colissimo'), $e->getMessage()),
            ];
        }
    }
}
