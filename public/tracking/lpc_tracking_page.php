<?php

defined('ABSPATH') || die('Restricted Access');

class LpcTrackingPage extends LpcComponent {
    const ROUTE = '.*lpc/tracking/(.+)/?';
    const QUERY_VAR = 'lpc_tracking_hash';

    protected $lpcUnifiedTrackingApi;

    public function __construct(LpcUnifiedTrackingApi $lpcUnifiedTrackingApi = null) {
        $this->lpcUnifiedTrackingApi = LpcRegister::get('unifiedTrackingApi', $lpcUnifiedTrackingApi);
    }

    public function getDependencies() {
        return ['unifiedTrackingApi'];
    }

    public function init() {
        // Whitelist our URL parameter
        add_filter(
            'query_vars',
            function (array $query_vars) {
                $query_vars[] = self::QUERY_VAR;

                return $query_vars;
            }
        );

        // If our parameter is in the URL, show our tracking page
        add_action(
            'parse_request',
            function (WP $wp) {
                if (!empty($wp->query_vars[self::QUERY_VAR])) {
                    $this->control($wp);
                }
            }
        );
    }

    public function control(WP $wp) {
        LpcHelper::enqueueStyle(
            'lpc_tracking',
            null,
            plugins_url('/css/lpc_tracking.css', LPC_PUBLIC . 'init.php')
        );

        $trackingHash = $wp->query_vars[self::QUERY_VAR];
        $decryptedVar = $this->lpcUnifiedTrackingApi->decrypt($trackingHash);
        [$orderId, $trackingNumber] = explode('-', $decryptedVar);

        try {
            $order = new WC_Order($orderId);

            try {
                if (isset($_SERVER['REMOTE_ADDR'])) {
                    $trackingInfo = $this->lpcUnifiedTrackingApi->getTrackingInfo(
                        $trackingNumber,
                        wc_clean(wp_unslash($_SERVER['REMOTE_ADDR'])),
                        null,
                        null
                    );
                }
            } catch (Exception $e) {
                if ($e->getMessage() === 'Numéro de colis inconnu') {
                    wp_redirect(get_permalink(wc_get_page_id('myaccount')) . 'orders');
                    exit;
                }

                header('HTTP/1.0 500 Internal Server Error');
                wp_die(
                    sprintf(
                        __('An error occured while retrieving tracking info (%1$s [%2$u])... <a href="%3$s">get back to the home page</a>.', 'wc_colissimo'),
                        $e->getMessage(),
                        $e->getCode(),
                        get_home_url()
                    )
                );
            }
            $trackingInfo->mainStatus = $this->getMainStatus($trackingInfo);

            die(
            LpcHelper::renderPartialInLayout(
                'tracking' . DS . 'tracking_page.php',
                [
                    'order'        => $order,
                    'logoUrl'      => plugins_url('/images/colissimo.png', LPC_INCLUDES . 'init.php'),
                    'trackingInfo' => $trackingInfo,
                ]
            )
            );
        } catch (Exception $e) {
            header('HTTP/1.0 404 Not Found');
            wp_die(sprintf(__('Not found... <a href="%s">get back to the home page</a>.', 'wc_colissimo'), get_home_url()));
        }
    }

    public static function addRewriteRule() {
        // Detect our permalink structure and fill in our URL parameter so that it could be detected
        add_rewrite_rule(
            self::ROUTE,
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    protected function getMainStatus(stdClass $trackingInfo) {
        try {
            if (!empty($trackingInfo->statusDelivery)) {
                return __('Delivered', 'wc_colissimo');
            }

            $lastEvent = end($trackingInfo->parcel->event);

            return $lastEvent->labelLong;
        } catch (\Exception $e) {
            return '';
        }
    }
}
