<?php

abstract class LpcRestApi extends LpcComponent {
    const DATA_TYPE_JSON = 'json';
    const DATA_TYPE_URL = 'url';
    const DATA_TYPE_MULTIPART = 'multipart';
    /** @var bool|string */
    protected $lastResponse;

    abstract protected function getApiUrl($action);

    public function query(
        $action,
        $params = [],
        $dataType = self::DATA_TYPE_JSON,
        $headers = [],
        $unsafeFileUpload = false
    ) {
        $url = $this->getApiUrl($action);
        LpcLogger::debug(__METHOD__, ['url' => $url]);

        switch ($dataType) {
            case self::DATA_TYPE_URL:
                $url        .= '?' . http_build_query($params);
                $httpHeader = ['Content-Type: application/x-www-form-urlencoded; charset=utf-8'];
                break;
            case self::DATA_TYPE_MULTIPART:
                $data       = $params;
                $httpHeader = ['Content-Type: multipart/form-data'];
                break;
            case self::DATA_TYPE_JSON:
            default:
                $data       = wp_json_encode($params);
                $httpHeader = ['Content-Type: application/json'];
                break;
        }

        $httpHeader = array_merge($httpHeader, $headers);

        $ch = curl_init();
        curl_setopt_array(
            $ch,
            [
                CURLOPT_URL            => $url,
                CURLOPT_HTTPHEADER     => $httpHeader,
                CURLOPT_RETURNTRANSFER => true,
            ]
        );

        if (self::DATA_TYPE_URL !== $dataType) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        if ($unsafeFileUpload) {
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
        }

        $response = curl_exec($ch);

        if (!$response) {
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            LpcLogger::error(
                __METHOD__,
                [
                    'curl_errno' => $curlErrno,
                    'curl_error' => $curlError,
                ]
            );
            curl_close($ch);
            throw new Exception($curlError, $curlErrno);
        } else {
            $this->lastResponse = $response;
        }

        $returnStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (self::DATA_TYPE_URL === $dataType) {
            return $response;
        }

        return $this->parseResponse($returnStatus, $response);
    }

    protected function parseResponse($returnStatus, $response) {
        preg_match('/--(.*)\b/', $response, $boundary);

        $content = empty($boundary)
            ? $this->parseMonoPartBody($response)
            : $this->parseMultiPartBody($response, $boundary[0]);

        if (200 === $returnStatus) {
            return $content;
        }

        LpcLogger::warn(
            __METHOD__,
            [
                'returnStatus' => $returnStatus,
                'jsonInfos'    => !empty($content['<jsonInfos>']) ? $content['<jsonInfos>'] : $content,
            ]
        );

        if (!empty($content['<jsonInfos>'])) {
            $content = $content['<jsonInfos>'];
        }

        if (isset($content['messages'])) {
            $message = $content['messages'][0]['id'] . ' : ' . $content['messages'][0]['messageContent'];
        } elseif (!empty($content['error'])) {
            $message = $content['error'];
            if (!empty($content['message'])) {
                $message .= ' : ' . $content['message'];
            }
        } elseif (!empty($content['errorCode']) && !empty($content['errorLabel'])) {
            $message = $content['errorCode'] . ': ' . $content['errorLabel'];
        } else {
            $message = __('Unknown error', 'wc_colissimo');
        }

        throw new Exception('CURL error: ' . '(' . $returnStatus . ') ' . $message, $returnStatus);
    }

    protected function parseMultiPartBody($body, $boundary) {
        $messages = array_filter(
            array_map(
                'trim',
                explode($boundary, $body)
            )
        );

        $parts = [];
        foreach ($messages as $message) {
            if ('--' === $message) {
                break;
            }

            if (strpos($message, "\r\n\r\n") === false) {
                LpcLogger::error(
                    'Incomplete response from Colissimo API',
                    [
                        'response' => $message,
                    ]
                );
                continue;
            }

            $headers = [];
            [$headerLines, $body] = explode("\r\n\r\n", $message, 2);

            foreach (explode("\r\n", $headerLines) as $headerLine) {
                [$key, $value] = preg_split('/:\s+/', $headerLine, 2);
                $headers[strtolower($key)] = $value;
            }

            if (!empty($headers['content-type']) && 'application/json' === $headers['content-type']) {
                $body = json_decode($body, true);
            }

            if (!empty($headers['content-id'])) {
                $parts[$headers['content-id']] = '<jsonInfos>' === $headers['content-id']
                    ? json_decode($body, true)
                    : $body;
            }
        }

        return $parts;
    }

    protected function parseMonoPartBody($body) {
        return json_decode($body, true);
    }

}
