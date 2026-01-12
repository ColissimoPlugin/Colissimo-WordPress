<?php

defined('ABSPATH') || die('Restricted Access');

class LpcOrderTracking extends LpcComponent {
    const APPLIED_CLOSEST_RELAY_META_KEY = 'lpc_applied_closest_relay';

    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcLabelInwardDownloadAccountAction */
    protected $labelInwardDownloadAccountAction;
    /** @var LpcCapabilitiesPerCountry */
    protected $lpcCapabilitiesPerCountry;
    /** @var LpcUnifiedTrackingApi */
    protected $unifiedTrackingApi;
    /** @var LpcPickupWebService */
    protected $lpcPickupWebService;

    public function __construct(
        ?LpcOutwardLabelDb $outwardLabelDb = null,
        ?LpcLabelInwardDownloadAccountAction $labelInwardDownloadAccountAction = null,
        ?LpcCapabilitiesPerCountry $lpcCapabilitiesPerCountry = null,
        ?LpcUnifiedTrackingApi $unifiedTrackingApi = null,
        ?LpcPickupWebService $lpcPickupWebService = null
    ) {
        $this->outwardLabelDb                   = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->labelInwardDownloadAccountAction = LpcRegister::get('labelInwardDownloadAccountAction', $labelInwardDownloadAccountAction);
        $this->lpcCapabilitiesPerCountry        = LpcRegister::get('capabilitiesPerCountry', $lpcCapabilitiesPerCountry);
        $this->unifiedTrackingApi               = LpcRegister::get('unifiedTrackingApi', $unifiedTrackingApi);
        $this->lpcPickupWebService              = LpcRegister::get('pickupWebService', $lpcPickupWebService);
    }

    public function getDependencies(): array {
        return ['outwardLabelDb', 'labelInwardDownloadAccountAction', 'capabilitiesPerCountry', 'unifiedTrackingApi', 'pickupWebService'];
    }

	public function init() {
		add_filter('woocommerce_account_orders_columns', [$this, 'addTrackingLinkTitle'], 10, 1);
		add_action('woocommerce_my_account_my_orders_column_order-tracking', [$this, 'addTrackingLinkData'], 10, 1);

        add_action('woocommerce_order_details_after_order_table', [$this, 'addTrackingInformation'], 10, 1);
        add_action('woocommerce_order_details_after_order_table', [$this, 'addReturnLabelDownload'], 11, 1);
        add_filter('wcpay_express_checkout_js_params', [$this, 'forcePhoneForExpressPayments'], 11, 1);
        add_action('woocommerce_before_order_object_save', [$this, 'applyClosestRelayForExpressPayments'], 10, 1);
    }

    public function addTrackingLinkTitle($columns) {
        $addTrackingColumn = 'no' !== LpcHelper::get_option('lpc_show_tracking_column_front', 'no');

		$newColumns = [];
		foreach ($columns as $key => $column) {
			if ('order-actions' === $key && $addTrackingColumn) {
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

	public function addTrackingInformation(WC_Order $order) {

		$orderId = $order->get_id();
		$labels  = $this->outwardLabelDb->getLabelsInfosForOrdersId([$orderId]);
		if (empty($labels)) {
			return;
		}

		$isWebsitePage = 'website_tracking_page' === LpcHelper::get_option('lpc_email_tracking_link', 'website_tracking_page');
		?>
		<h2 class="woocommerce-column__title"><?php esc_html_e('Colissimo tracking', 'wc_colissimo'); ?></h2>
		<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
			<thead>
				<tr>
					<?php if (count($labels) > 1) { ?>
						<th class="woocommerce-table__product-name parcel-number"><?php esc_html_e('Parcel', 'wc_colissimo'); ?></th>
					<?php } ?>
					<th class="woocommerce-table__product-table parcel-tracking"><?php esc_html_e('Tracking', 'wc_colissimo'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$i = 0;
				foreach ($labels as $oneLabel) {
					$i ++;
					?>
					<tr class="woocommerce-table__line-item order_item">
						<?php if (count($labels) > 1) { ?>
							<td class="woocommerce-table__parcel-number parcel-number">
								<?php echo esc_html(sprintf(__('Parcel n°%s', 'wc_colissimo'), $i)); ?>
							</td>
						<?php } ?>
						<td class="woocommerce-table__parcel-tracking parcel-tracking">
							<?php
							if ($isWebsitePage) {
								$trackingLink = get_site_url() . $this->unifiedTrackingApi->getTrackingPageUrlForOrder($orderId, $oneLabel->tracking_number);
							} else {
								$trackingLink = str_replace('{lpc_tracking_number}', $oneLabel->tracking_number, LpcAbstractShipping::LPC_LAPOSTE_TRACKING_LINK);
							}
							?>
							<a target="_blank" href="<?php echo esc_url($trackingLink); ?>"><?php echo esc_html($oneLabel->tracking_number); ?></a>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php
	}

	public function addReturnLabelDownload(WC_Order $order) {
		// Check if we allow return labels
		$returnGenerationType = LpcHelper::get_option('lpc_customers_download_return_label', 'no');
		if ('no' === $returnGenerationType || is_order_received_page()) {
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

    public function forcePhoneForExpressPayments(array $params): array {
        // We need it for relay shipments and don't know which shipping method will be used
        $params['checkout']['needs_payer_phone'] = true;

        return $params;
    }

    public function applyClosestRelayForExpressPayments(object $order): void {
        if (!$order->get_id() || 'woocommerce_payments' !== $order->get_payment_method()) {
            return;
        }

        // Only for express payments with Apple Pay or Google Pay
		$methodTitle = $order->get_payment_method_title();
        if (strpos($methodTitle, 'Google Pay') === false && strpos($methodTitle, 'Apple Pay') === false) {
            return;
        }

        // Only for relay shipments
        if (!LpcOrderQueries::hasShippingMethod($order, LpcRelay::ID)) {
            return;
        }

        $applied = $order->get_meta(self::APPLIED_CLOSEST_RELAY_META_KEY);
        if (!empty($applied)) {
            return;
        }

        $order->update_meta_data(self::APPLIED_CLOSEST_RELAY_META_KEY, 'yes');

        $closestRelay = $this->lpcPickupWebService->getDefaultPickupLocationInfoWS(
            [
                'address'     => $order->get_shipping_address_1(),
                'zipCode'     => $order->get_shipping_postcode(),
                'city'        => $order->get_shipping_city(),
                'countryCode' => $order->get_shipping_country(),
            ]
        );

        if (!empty($closestRelay)) {
            LpcLogger::debug(
                'Applying closest relay data to order for express payment',
                [
                    'orderID'      => $order->get_id(),
                    'closestRelay' => $closestRelay,
                ]
            );

            $order->set_shipping_address_1($closestRelay['adresse1']);
            $order->set_shipping_address_2($closestRelay['adresse2'] ?? '');
            $order->set_shipping_postcode($closestRelay['codePostal']);
            $order->set_shipping_city($closestRelay['localite']);
            $order->set_shipping_country($closestRelay['codePays']);
            $order->set_shipping_company($closestRelay['nom'] ?? '');

            $order->update_meta_data(LpcPickupSelection::PICKUP_LOCATION_ID_META_KEY, $closestRelay['identifiant']);
            $order->update_meta_data(LpcPickupSelection::PICKUP_LOCATION_LABEL_META_KEY, $closestRelay['nom']);
            $order->update_meta_data(LpcPickupSelection::PICKUP_PRODUCT_CODE_META_KEY, $closestRelay['typeDePoint']);
            $order->update_meta_data(LpcPickupSelection::PICKUP_LOCATION_DATA_META_KEY, json_encode($closestRelay));
        } else {
            LpcLogger::error(
                'No relay found near the shipping address with express payment',
                [
                    'orderID' => $order->get_id(),
                ]
            );
        }
    }
}
