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
     * @param string $documentPath
     * @param string $documentName The uploaded file name for the error message
     *
     * @return string
     * @throws Exception When an error occurs.
     */
    public function storeDocument(array $orderLabels, string $documentType, string $parcelNumber, string $documentPath, string $documentName): string {
        if (function_exists('curl_file_create')) {
            $document         = curl_file_create($documentPath, mime_content_type($documentPath), $documentName);
            $unsafeFileUpload = false;
        } else {
            $document = '@' . realpath($documentPath);
            // TODO $document = new CurlFile($documentPath, mime_content_type($documentPath), $documentName);
            $unsafeFileUpload = true;
        }

        if ('api_key' === LpcHelper::get_option('lpc_credentials_type', 'account')) {
            // TODO remove this option once the documents API has been fixed
            $contractNumber = LpcHelper::get_option('lpc_contract_number');
            $headers        = [
                'apiKey: ' . LpcHelper::get_option('lpc_apikey'),
            ];
        } else {
            $login          = LpcHelper::get_option('lpc_id_webservices');
            $contractNumber = LpcHelper::get_option('lpc_parent_account');
            if (empty($contractNumber)) {
                $contractNumber = $login;
            }
            $headers = [
                'login: ' . $login,
                'password: ' . LpcHelper::getPasswordWebService(),
            ];
        }

        $payload = [
            'accountNumber' => $contractNumber,
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

        try {
            $response = $this->query(
                'storedocument',
                $payload,
                self::DATA_TYPE_MULTIPART,
                $headers,
                $unsafeFileUpload
            );

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
                    'exception' => implode(', ', $message),
                ]
            );

            if (1 < count($message)) {
                array_shift($message);
            }

            throw new Exception(sprintf(__('An error occurred when transmitting the file %1$s: %2$s', 'wc_colissimo'), $documentName, implode(', ', $message)));
        }
    }
}
