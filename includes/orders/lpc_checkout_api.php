<?php

require_once LPC_INCLUDES . 'lpc_rest_api.php';

class LpcCheckoutApi extends LpcRestApi {
    const API_BASE_URL = 'https://ws.colissimo.fr/tunnel-commande/rest/TunnelCommandeWS/';
    const MAX_NB_TRIES_SCHEDULE = 14;
    const SECONDS_IN_A_DAY = 86400;

    protected function getApiUrl($action) {
        return self::API_BASE_URL . $action;
    }

    public function getDeliveryDate(string $postCode): ?string {
        if ('api_key' === LpcHelper::get_option('lpc_credentials_type', 'api_key')) {
            $payload['credentials']['apiKey'] = LpcHelper::get_option('lpc_apikey');
        } else {
            $payload['credentials']['login']    = LpcHelper::get_option('lpc_id_webservices');
            $payload['credentials']['password'] = LpcHelper::getPasswordWebService();
        }

        $parentAccountId = LpcHelper::get_option('lpc_parent_account');
        if (!empty($parentAccountId)) {
            $payload['credentials']['partnerClientCode'] = $parentAccountId;
        }

        $payload['data']['zipCodeDest']  = $postCode;
        $payload['data']['regateDepart'] = LpcHelper::get_option('lpc_delivery_date_deposit_location');
        $payload['data']['depositDate']  = $this->getDepositDate();

        if (empty($payload['data']['depositDate'])) {
            return null;
        }

        LpcLogger::debug(
            'Getting delivery date payload',
            [
                'method'  => __METHOD__,
                'payload' => $payload['data'],
            ]
        );

        try {
            $response = $this->query('getDateLivraison', $payload);

            if (empty($response['errorCode']) || 'OK' !== $response['errorCode']) {
                LpcLogger::error(
                    'Delivery date request failed',
                    [
                        'method' => __METHOD__,
                        'error'  => $response['message'] ?? ($response['errorCode'] ?? 'Unknown error'),
                    ]
                );

                return null;
            }
        } catch (Exception $e) {
            LpcLogger::error(
                'Delivery date request failed',
                [
                    'method' => __METHOD__,
                    'error'  => $e->getMessage(),
                ]
            );

            return null;
        }

        LpcLogger::debug(
            'Getting delivery date',
            [
                'method'   => __METHOD__,
                'response' => $response,
            ]
        );

        return !empty($response['deliveryDate']) ? $this->formatDeliveryDate($response['deliveryDate']) : null;
    }

    private function getDepositDate(): ?string {
        $cuttOffDates = LpcHelper::get_option('lpc_delivery_date_cuttoff_times');
        if (empty($cuttOffDates)) {
            return null;
        }

        $cuttOffDates = @json_decode($cuttOffDates, true);
        if (empty($cuttOffDates['weekly_schedule'])) {
            return null;
        }

        $time            = time();
        $preparationTime = (int) LpcHelper::get_option('lpc_preparation_time');
        $preparationTime *= self::SECONDS_IN_A_DAY;

        $nbTries     = 0;
        $currentTime = (int) wp_date('H', $time);
        do {
            $dayTime           = $time + $preparationTime + ($nbTries * self::SECONDS_IN_A_DAY);
            $processingDate    = wp_date('Y-m-d', $dayTime);
            $processingWeekday = wp_date('N', $dayTime);

            // Check exceptions first
            $cuttOffTimeFromRules = $this->getExceptionCuttOff($cuttOffDates, $processingDate);

            // Get global weekday rule as a fallback
            if (empty($cuttOffTimeFromRules)) {
                $cuttOffTimeFromRules = $cuttOffDates['weekly_schedule'][LpcHelper::DAYS[$processingWeekday]] ?? null;
            }

            // For the first day, we accept orders placed before the cuttoff hour. For next days the order is ready the first business hour so don't check the time
            if (0 === $nbTries && empty($preparationTime) && !empty($cuttOffTimeFromRules) && 'none' !== $cuttOffTimeFromRules && $currentTime > (int) $cuttOffTimeFromRules) {
                $cuttOffTimeFromRules = null;
            }

            $nbTries ++;
        } while ($nbTries < self::MAX_NB_TRIES_SCHEDULE && (empty($cuttOffTimeFromRules) || 'none' === $cuttOffTimeFromRules));

        if (empty($cuttOffTimeFromRules) || 'none' === $cuttOffTimeFromRules) {
            return null;
        }

        return $processingDate;
    }

    private function getExceptionCuttOff(array $cuttOffDates, string $date): ?string {
        if (empty($cuttOffDates['exceptions'])) {
            return null;
        }

        foreach ($cuttOffDates['exceptions'] as $oneException) {
            if ($oneException['date'] === $date) {
                return $oneException['hour'];
            }
        }

        return null;
    }

    private function formatDeliveryDate(string $deliveryDate): ?string {
        $dateTime = DateTime::createFromFormat('d/m/Y', $deliveryDate);
        if (!$dateTime) {
            return null;
        }

        $text = LpcHelper::get_option('lpc_delivery_date_text');
        if (empty($text) || strpos($text, '{date}') === false) {
            $text = __('Delivery expected on {date}', 'wc_colissimo');
        }

        $format = LpcHelper::get_option('lpc_delivery_date_format');
        switch ($format) {
            case 'default':
                $dateFormat = LpcHelper::get_option('date_format', __('l, F j', 'wc_colissimo'));
                break;
            case 'full':
                $dateFormat = __('l, F j', 'wc_colissimo');
                break;
            case 'simple':
                $dateFormat = __('F j', 'wc_colissimo');
                break;
            case 'short':
                $dateFormat = __('M j', 'wc_colissimo');
                break;
            default:
                $dateFormat = $format;
        }

        $timestamp = $dateTime->getTimestamp();
        $date      = LpcHelper::translateDate(date($dateFormat, $timestamp));

        $styles    = '';
        $textColor = LpcHelper::get_option('lpc_delivery_date_color');
        if (!empty($textColor)) {
            $styles .= 'color:' . $textColor . ';';
        }

        $textFont = LpcHelper::getFont('lpc_delivery_date_font');
        if (!empty($textFont) && 'default' !== $textFont) {
            $styles .= 'font-family:' . $textFont . ';';
        }

        $textSize = LpcHelper::get_option('lpc_delivery_date_size');
        if (!empty($textSize) && 'default' !== $textSize) {
            $styles .= 'font-size:' . $textSize . ';';
        }

        return '<span style="' . esc_attr($styles) . '">' . esc_html(str_replace('{date}', $date, $text)) . '</span>';
    }
}
