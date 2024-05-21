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

    public function getDependencies() {
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

        $output   = [];
        $output[] = '<div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1">';
        $output[] = '<h2 class="woocommerce-column__title">' . __('Download inward label', 'wc_colissimo') . '</h2>';

        if (in_array($returnGenerationType, ['yes', 'both'])) {
            $output[] = '<p>' . __('Select the parcel you would like to return:', 'wc_colissimo') . '</p>';
            $output[] = '<ul>';
            $links    = [];
            foreach ($trackingNumbers as $oneTrackingNumber) {
                $downloadInwardLabel = $this->labelInwardDownloadAccountAction->getUrlForTrackingNumber($oneTrackingNumber);
                $text                = sprintf(__('For outward label %s', 'wc_colissimo'), $oneTrackingNumber);
                $links[]             = '<li><a href="' . esc_url($downloadInwardLabel) . '">' . $text . '</a></li>';
            }
            $output[] = implode('', $links);
            $output[] = '</ul>';
        }

        if (in_array($returnGenerationType, ['product', 'both'])) {
            $output[] = '<script type="text/javascript">const lpc_orders_return = {
                selectProducts: "' . esc_attr__('You need to select at least one item to generate a label', 'wc_colissimo') . '",
                downloadUrlBase: "' . esc_url($this->labelInwardDownloadAccountAction->getUrlForCustom($order->get_id())) . '"
            }</script>';
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
            $output[] = '<script src="' . esc_url(plugins_url('/js/orders/details.js', LPC_PUBLIC . 'init.php')) . '"></script>';
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
            $output[] = '<link rel="stylesheet" href="' . esc_url(plugins_url('/css/orders/details.css', LPC_PUBLIC . 'init.php')) . '" />';
            $output[] = '<p>' . __('Select the products you would like to return:', 'wc_colissimo') . '</p>';
            $output[] = '<table id="lpc_return_table" class="shop_table">';
            $output[] = '<thead><tr><th></th><th>' . __('Product', 'wc_colissimo') . '</th><th>' . __('Quantity', 'wc_colissimo') . '</th></tr></thead>';
            $output[] = '<tbody>';
            foreach ($order->get_items() as $item) {
                $output[] = '<tr>';
                $output[] = '<td><input type="checkbox" /></td>';
                $output[] = '<td>' . $item->get_name() . '</td>';
                $output[] = '<td><input type="number" 
                                        class="input-text" 
                                        data-lpc-product="' . esc_attr($item->get_id()) . '"
                                        value="' . esc_attr($item->get_quantity()) . '" 
                                        min="0" 
                                        max="' . esc_attr($item->get_quantity()) . '" /></td>';
                $output[] = '</tr>';
            }
            $output[] = '</tbody>';
            $output[] = '<tfoot><tr><td colspan="3"><button type="button" class="button wp-element-button" id="lpc_download_return_label">';
            $output[] = __('Download return label', 'wc_colissimo');
            $output[] = '</button></td></tr></tfoot>';
            $output[] = '</table>';
        }

        $output[] = '</div>';

        echo implode('', $output);
    }
}
