<?php

defined('ABSPATH') || die('Restricted Access');

class LpcOrderTracking extends LpcComponent {
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcLabelInwardDownloadAccountAction */
    protected $labelInwardDownloadAccountAction;

    protected $lpcCapabilitiesPerCountry;

    public function __construct(
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcLabelInwardDownloadAccountAction $labelInwardDownloadAccountAction = null,
        LpcCapabilitiesPerCountry $lpcCapabilitiesPerCountry = null
    ) {
        $this->outwardLabelDb                   = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->labelInwardDownloadAccountAction = LpcRegister::get('labelInwardDownloadAccountAction', $labelInwardDownloadAccountAction);
        $this->lpcCapabilitiesPerCountry        = LpcRegister::get('capabilitiesPerCountry', $lpcCapabilitiesPerCountry);
    }

    public function getDependencies(): array {
        return ['outwardLabelDb', 'labelInwardDownloadAccountAction', 'capabilitiesPerCountry'];
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
        // Check if we allow return labels
        $returnGenerationType = LpcHelper::get_option('lpc_customers_download_return_label', 'no');
        if ('no' === $returnGenerationType) {
            return;
        }

        // We only allow return labels for a certain amount of days
        $returnGenerationDays = LpcHelper::get_option('lpc_customers_download_return_label_days', 14);
        $limitDate            = $order->get_date_created();
        $limitDate->add(new DateInterval('P' . $returnGenerationDays . 'D'));
        if ($limitDate < new DateTime()) {
            return;
        }

        // If no parcel has been sent, no need for return label
        $trackingNumbers = $this->outwardLabelDb->getOrderLabels($order->get_id());
        if (empty($trackingNumbers)) {
            return;
        }

        // Make sure the country is eligible for return labels
        if (false === $this->lpcCapabilitiesPerCountry->getReturnProductCodeForDestination($order->get_shipping_country())) {
            return;
        }

        $output = [];
        // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
        $output[] = '<script src="' . esc_url(plugins_url('/js/orders/details.js', LPC_PUBLIC . 'init.php')) . '"></script>';
        // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
        $output[] = '<link rel="stylesheet" href="' . esc_url(plugins_url('/css/orders/details.css', LPC_PUBLIC . 'init.php')) . '" />';

        $myAccountUrl = get_permalink(get_option('woocommerce_myaccount_page_id'));
        $myAccountUrl = add_query_arg('lpcreturn', '', $myAccountUrl);
        $myAccountUrl = add_query_arg('order_id', $order->get_id(), $myAccountUrl);

        $output[] = '<input type="hidden" id="lpc_return_label_url" value="' . esc_url($myAccountUrl, null, 'javascript') . '" />';
        $output[] = '<a href="#" class="button wp-element-button" id="lpc_return_products">';
        $output[] = __('Return products', 'wc_colissimo');
        $output[] = '</a>';

        echo implode('', $output);
    }
}
