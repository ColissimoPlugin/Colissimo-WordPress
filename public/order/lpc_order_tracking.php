<?php

defined('ABSPATH') || die('Restricted Access');

class LpcOrderTracking extends LpcComponent {
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcLabelInwardDownloadAccountAction */
    protected $labelInwardDownloadAccountAction;

    public function __construct(LpcOutwardLabelDb $outwardLabelDb = null, LpcLabelInwardDownloadAccountAction $labelInwardDownloadAccountAction = null) {
        $this->outwardLabelDb                   = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->labelInwardDownloadAccountAction = LpcRegister::get('labelInwardDownloadAccountAction', $labelInwardDownloadAccountAction);
    }

    public function getDependencies() {
        return ['outwardLabelDb'];
    }

    public function init() {
        add_filter('woocommerce_account_orders_columns', [$this, 'addTrackingLinkTitle'], 10, 1);
        add_action('woocommerce_my_account_my_orders_column_order-tracking', [$this, 'addTrackingLinkData'], 10, 1);
        add_action('woocommerce_order_details_after_order_table', [$this, 'addReturnLabelDownload'], 10, 1);
    }

    public function addTrackingLinkTitle($columns) {
        $newColumns = [];
        foreach ($columns as $key => $column) {
            if ('order-actions' === $key) {
                $newColumns['order-tracking'] = __('Colissimo order tracking', 'wc_colissimo');
            }
            $newColumns[$key] = $column;
        }

        return $newColumns;
    }

    public function addTrackingLinkData($order) {
        $orderId         = $order->get_id();
        $trackingNumbers = $this->outwardLabelDb->getOrderLabels($orderId);

        // No tracking number available yet, or Colissimo not used
        if (empty($trackingNumbers)) {
            echo '-';

            return;
        }

        $isWebsitePage = 'website_tracking_page' === LpcHelper::get_option('lpc_email_tracking_link', 'website_tracking_page');
        $output        = [];
        foreach ($trackingNumbers as $oneTrackingNumber) {
            if ($isWebsitePage) {
                $trackingLink = get_site_url() . LpcRegister::get('unifiedTrackingApi')->getTrackingPageUrlForOrder($orderId, $oneTrackingNumber);
            } else {
                $trackingLink = str_replace(
                    '{lpc_tracking_number}',
                    $oneTrackingNumber,
                    LpcAbstractShipping::LPC_LAPOSTE_TRACKING_LINK
                );
            }

            $output[] = '<a target="_blank" href="' . esc_url($trackingLink) . '">' . esc_html($oneTrackingNumber) . '</a>';
        }

        echo implode('<br />', $output);
    }

    public function addReturnLabelDownload(WC_Order $order) {
        if ('no' === LpcHelper::get_option('lpc_customers_download_return_label', 'no')) {
            echo '';

            return;
        }
        $trackingNumbers = $this->outwardLabelDb->getOrderLabels($order->get_id());
        if (empty($trackingNumbers)) {
            echo '';

            return;
        }
        $output = [
            '<div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1">
	<h2 class="woocommerce-column__title">' . __('Download inward label', 'wc_colissimo') . '</h2>',
            '<ul>',
        ];
        $links  = [];
        foreach ($trackingNumbers as $oneTrackingNumber) {
            $downloadInwardLabel = $this->labelInwardDownloadAccountAction->getUrlForTrackingNumber($oneTrackingNumber);
            $text                = sprintf(__('For outward label %s', 'wc_colissimo'), $oneTrackingNumber);
            $links[]             = '<li><a target="_blank" href="' . esc_url($downloadInwardLabel) . '">' . $text . '</a></li>';
        }
        $output[] = implode('', $links);
        $output[] = '</ul>';
        $output[] = '</div>';

        echo implode('', $output);
    }
}
