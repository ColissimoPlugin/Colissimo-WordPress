<?php

defined('ABSPATH') || die('Restricted Access');
if (!class_exists('LPC_TCPDF')) {
    require_once LPC_FOLDER . DS . 'lib' . DS . 'tcpdf' . DS . 'lpc_tcpdf.php';
}

class LpcInvoiceGenerateAction extends LpcComponent {
    /** @var LpcAjax */
    protected $ajaxDispatcher;

    public function __construct(?LpcAjax $ajaxDispatcher = null) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
    }

    public function getDependencies(): array {
        return ['ajaxDispatcher'];
    }

    public function init() {
    }

    public function generateInvoice($orderId, $filename, $destination) {
        try {
            $order = wc_get_order($orderId);
            $pdf   = new LPC_TCPDF(LpcTcpdfConfig\PDF_PAGE_ORIENTATION, LpcTcpdfConfig\PDF_UNIT, LpcTcpdfConfig\PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetMargins(LpcTcpdfConfig\PDF_MARGIN_LEFT, 5, LpcTcpdfConfig\PDF_MARGIN_RIGHT);
            $pdf->SetHeaderMargin(LpcTcpdfConfig\PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(LpcTcpdfConfig\PDF_MARGIN_FOOTER);
            $pdf->SetPrintHeader(false);
            $pdf->SetPrintFooter(false);
            $pdf->addPage();
            $pdf->writeHTML($this->printBlanklines(2));
            $pdf->writeHTML($this->printStoreInformationInHTML($order));
            $pdf->writeHTML($this->printBlanklines(2));
            $pdf->writeHTML($this->printOrderTitle($order));
            $pdf->writeHTML($this->printBlanklines(5));
            $pdf->writeHTML($this->printProductsInHTML($order));
            $pdf->writeHTML($this->printBlanklines(4));
            $pdf->writeHTML($this->printCustomerInformationInHTML($order));
            $pdf->Output($filename, $destination);
        } catch (Exception $e) {
            header('HTTP/1.0 404 Not Found');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    public function printOrderTitle($order) {
        $output = '<h3>' . sprintf(__('[Order #%s]', 'wc_colissimo'), $order->get_id()) . ' (' . wc_format_datetime($order->get_date_created()) . ')</h3>';

        return $output;
    }

    public function printBlanklines($nbLines) {
        $output = '';
        for ($i = 0; $i < $nbLines; $i ++) {
            $output .= '<br/>';
        }

        return $output;
    }

    public function printProductsInHTML($order) {
        // Table header
        $output = '<div style="margin-bottom: 40px;">';
        $output .= '<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif;" border="1">';
        $output .= '<thead><tr style="font-weight: bold; background-color: #eeeeee">';
        $output .= '<th class="td" scope="col" style="text-align:left;">' . __('Product', 'wc_colissimo') . '</th>';
        $output .= '<th class="td" scope="col" style="text-align:left;">' . __('Quantity', 'wc_colissimo') . '</th>';
        $output .= '<th class="td" scope="col" style="text-align:left;">' . __('Price', 'wc_colissimo') . '</th>';
        $output .= '</tr></thead>';

        // Products using email invoice structure
        $output .= '<tbody>';
        $output .= $this->printProductArray($order);

        $output .= '</tbody>';

        // Totals, payment and delivery methods
        $output .= '<tfoot>';
        $totals = $order->get_order_item_totals();

        if ($totals) {
            $i = 0;
            foreach ($totals as $total) {
                $i ++;
                $style = '';
                if (count($totals) === $i) {
                    $style = ' background-color: #eeeeee; font-weight: bold;';
                }
                $output .= '<tr>
						<th class="td" scope="row" colspan="2" style="text-align:left; ' . $style . ((1 === $i) ? 'border-top-width: 3px;' : '') . '">' . $total['label'] . '</th>
						<td class="td" style="text-align:left; ' . ((1 === $i) ? 'border-top-width: 3px;' : '') . '">' . strip_tags($total['value']) . '</td>
					</tr>';
            }
        }
        if ($order->get_customer_note()) {
            $output .= '<tr>
					<th class="td" scope="row" colspan="2" style="text-align:left;">' . __('Note', 'wc_colissimo') . '</th>
					<td class="td" style="text-align:left;">' . wptexturize($order->get_customer_note()) . '</td>
				</tr>';
        }
        $output .= '</tfoot></table></div>';

        return $output;
    }

    /** Based on WooCommerce email templates without email improvements */
    protected function printProductArray(WC_Order $order): string {
        $productsArray = '';
        $items         = $order->get_items();
        foreach ($items as $item_id => $item) {
            /**
             * Email Order Item Visibility hook.
             *
             * This filter allows you to control the visibility of order items in emails.
             *
             * @param bool                  $visible Whether the item is visible in the email.
             * @param WC_Order_Item_Product $item    The order item object.
             *
             * @since 2.1.0
             */
            if (!apply_filters('woocommerce_order_item_visible', true, $item)) {
                continue;
            }

            /**
             * Email Order Item Thumbnail hook.
             *
             * @param string                $item_class The item CSS class
             * @param WC_Order_Item_Product $item       The item being displayed.
             * @param WC_Order              $order      The order object.
             *
             * @since 2.1.0
             */
            $productsArray .= '<tr class="' . esc_attr(apply_filters('woocommerce_order_item_class', 'order_item', $item, $order)) . '">';
            // Item name
            $productsArray .= '<td class="td font-family text-align-left" style="vertical-align: middle; word-wrap:break-word;">';
            /**
             * Order Item Name hook.
             *
             * @param string                $item_name The item name HTML.
             * @param WC_Order_Item_Product $item      The item being displayed.
             *
             * @since 2.1.0
             */
            $productsArray .= wp_kses_post(apply_filters('woocommerce_order_item_name', $item->get_name(), $item, false));
            $productsArray .= '</td>';

            // Item quantity
            $productsArray .= '<td class="td font-family text-align-left" style="vertical-align:middle;">';
            $qty           = $item->get_quantity();
            $refunded_qty  = $order->get_qty_refunded_for_item($item_id);

            if ($refunded_qty) {
                $qty_display = '<del>' . esc_html($qty) . '</del> <ins>' . esc_html($qty - ($refunded_qty * - 1)) . '</ins>';
            } else {
                $qty_display = esc_html($qty);
            }
            /**
             * Email Order Item Quantity hook.
             *
             * @param int           $quantity Item quantity.
             * @param WC_Order_Item $item     Item object.
             *
             * @since 2.4.0
             */
            $productsArray .= wp_kses_post(apply_filters('woocommerce_email_order_item_quantity', $qty_display, $item));
            $productsArray .= '</td>';

            // Item price
            $productsArray .= '<td class="td font-family text-align-left" style="vertical-align:middle;">';
            $productsArray .= wp_kses_post($order->get_formatted_line_subtotal($item));
            $productsArray .= '</td>
			</tr>';
        }

        return $productsArray;
    }

    protected function printCustomerInformationInHTML(WC_Order $order) {
        $html_output      = '<table><tr>';
        $billing_address2 = '';
        if ($order->get_billing_address_2()) {
            $billing_address2 = $order->get_billing_address_2() . '<br>';
        }
        $shipping_address2 = '';
        if ($order->get_shipping_address_2()) {
            $shipping_address2 = $order->get_shipping_address_2() . '<br>';
        }

        // Billing address
        $html_output .= '<td><div>';
        $html_output .= '<span style="font-weight: bold;text-decoration: underline;">' . __('Billing address', 'wc_colissimo') . '</span><br>';
        if (!empty($order->get_billing_company())) {
            $html_output .= $order->get_billing_company() . '<br>';
        }
        $html_output .= $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . '<br>';
        $html_output .= $order->get_billing_address_1() . '<br>';
        $html_output .= $billing_address2 . $order->get_billing_postcode() . ' <br>';
        $html_output .= $order->get_billing_city() . ' <br>';
        $html_output .= WC()->countries->countries[$order->get_billing_country()] . ' <br>';
        $html_output .= '</div></td>';
        $html_output .= '<td></td>';

        // Shipping address
        $html_output .= '<td><div style="float:right;">';
        $html_output .= '<span style="font-weight: bold;text-decoration: underline;">' . __('Shipping address', 'wc_colissimo') . '</span><br>';
        if (!empty($order->get_shipping_company())) {
            $html_output .= $order->get_shipping_company() . '<br>';
        }
        $html_output .= $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() . '<br>';
        $html_output .= $order->get_shipping_address_1() . '<br>';
        $html_output .= $shipping_address2 . $order->get_shipping_postcode() . ' <br>';
        $html_output .= $order->get_shipping_city() . ' <br>';
        $html_output .= WC()->countries->countries[$order->get_shipping_country()] . ' <br>';
        $html_output .= '</div></td>';

        $html_output .= '</tr></table>';

        return $html_output;
    }

    protected function printStoreInformationInHTML(WC_Order $order) {
        $store_address2 = '';
        if (get_option('woocommerce_store_address_2')) {
            $store_address2 = get_option('woocommerce_store_address_2') . '<br>';
        }

        $vatNumberOutput = '';
        if ('GB' === $order->get_shipping_country()) {
            $vatNumber = LpcHelper::get_option('lpc_vat_number', 0);

            if (0 === $vatNumber) {
                LpcLogger::warn('No VAT number set in config');
            } else {
                $vatNumberOutput = 'N° TVA : ' . $vatNumber;
            }
        }

        $output_html = '<div><span style="font-weight: bold;font-size: 1.2em;">' . LpcHelper::get_option('lpc_origin_company_name', '') . '</span><br>';
        $output_html .= get_option('woocommerce_store_address') . '<br>';
        $output_html .= $store_address2 . get_option('woocommerce_store_postcode') . ' <br>';
        $output_html .= get_option('woocommerce_store_city') . ' <br>';
        $output_html .= WC()->countries->countries[get_option('woocommerce_default_country')] . ' <br>';
        $output_html .= $vatNumberOutput . '</div>';

        return $output_html;
    }
}
