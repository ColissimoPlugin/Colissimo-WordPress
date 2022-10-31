<?php

defined('ABSPATH') || die('Restricted Access');
if (!class_exists('LPC_TCPDF')) {
    require_once LPC_FOLDER . DS . 'lib' . DS . 'tcpdf' . DS . 'lpc_tcpdf.php';
}

class LpcInvoiceGenerateAction extends LpcComponent {

    public function __construct() {
    }

    public function getDependencies() {
        return [];
    }

    public function init() {
    }

    public function generateInvoice($orderId, $filename, $destination) {
        try {
            $order = new WC_Order($orderId);
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
        $output .= wc_get_email_order_items(
            $order,
            [
                'show_sku'      => false,
                'show_image'    => false,
                'image_size'    => [32, 32],
                'plain_text'    => '',
                'sent_to_admin' => false,
            ]
        );

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
						<td class="td" style="text-align:left; ' . ((1 === $i) ? 'border-top-width: 3px;' : '') . '">' . $total['value'] . '</td>
					</tr>';
            }
        }
        if ($order->get_customer_note()) {
            $output .= '<tr>
					<th class="td" scope="row" colspan="2" style="text-align:left;">Note:</th>
					<td class="td" style="text-align:left;">' . wptexturize($order->get_customer_note()) . '</td>
				</tr>';
        }
        $output .= '</tfoot></table></div>';

        return $output;
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
