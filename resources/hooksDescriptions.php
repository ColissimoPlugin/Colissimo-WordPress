<div style="margin-bottom: 50px;">
	<p>
		This plugin allows you to modify the information sent to Colissimo when generating a label.<br>
		Please do not attempt to customize the generated labels if you're not an experienced developer.
	</p>
	<div>
		<h3>lpc_payload_letter_sender</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the sender's information during the generation of a label.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_sender', function($payloadSender, $orderNumber, $isReturnLabel){
		$payloadSender['phoneNumber'] = '0612345678';
		return $payloadSender;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_letter_service_commercial_name</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the commercial name during the generation of a label.
			It corresponds to the "Name of your store company" option for the origin address in the plugin's settings.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_service_commercial_name', function($commercialName){
		$commercialName = 'My store';
		return $commercialName;
	}, 10, 1);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_contract_number</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the Colissimo account ID during the generation of a label.
			It corresponds to the "ID" option in the plugin's settings.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_contract_number', function($contractNumber, $orderNumber, $isReturnLabel){
		$contractNumber = '123456';
		return $contractNumber;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_letter_addressee</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the recipient's information during the generation of a label.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_addressee', function($payloadAddressee, $orderNumber, $isReturnLabel){
		$payloadAddressee['address']['phoneNumber'] = '0612345678';
		return $payloadAddressee;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_letter_parcel_weight</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the total weight of the parcel (products + packaging) during the generation of a label.
			The weight is in kilograms.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_parcel_weight', function($totalWeight, $orderNumber, $isReturnLabel){
		$totalWeight = '1.5';
		return $totalWeight;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_letter_parcel_pickup_location_id</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the pickup location ID during the generation of a label.
			This ID is stored using a postmeta with the key "_lpc_meta_pickUpLocationId".</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_parcel_pickup_location_id', function($pickupLocationId, $orderNumber, $isReturnLabel){
		$pickupLocationId = '23456';
		return $pickupLocationId;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_letter_service_product_code</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the internal Colissimo product code during the generation of a label.
			Each product code corresponds to a shipping method.</p>
		<p>You can find the complete list <a target="_blank" href="https://www.colissimo.fr/doc-colissimo/redoc-sls/en#section/Available-Shipment-products/Product-codes">here</a>
		</p>
		<p>Note that the available product codes depend on the destination.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_service_product_code', function($productCode, $orderNumber, $isReturnLabel){
		$productCode = 'COL';
		return $productCode;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_letter_service_deposit_date</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the date planned for the parcel's deposit date.
			The default value takes into account the option "Average preparation delay" in the plugin's settings.
			The value returned must be a DateTime object.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_service_deposit_date', function($depositDate, $orderNumber, $isReturnLabel){
		$depositDate = new \DateTime();
		return $depositDate;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_delay</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the "Average preparation delay" value during the generation of a label.
			The value returned should be an integer representing the number of days.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_delay', function($delay, $orderNumber, $isReturnLabel){
		$delay = 2;
		return $delay;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_output_format</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the output format of the generated label.
			The available formats are listed <a target="_blank" href="https://www.colissimo.fr/doc-colissimo/redoc-sls/en#section/Label-format/Printed-materials">here</a></p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_output_format', function($outputFormat, $orderNumber, $isReturnLabel){
		$outputFormat = 'PDF_10x15_300dpi';
		return $outputFormat;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_letter_service_order_number</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the order number sent to Colissimo during the generation of a label.
			The generated label will still be linked to the original order on your website.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_service_order_number', function($orderNumber, $orderNumber2, $isReturnLabel){
		$orderNumber = '123456';
		return $orderNumber;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_letter_parcel_using_insurance</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the "Use insurance" option during the generation of a label.
			The value returned should be either "yes" or "no".
			Note that if the insurance isn't available for the chosen shipping method or country, it won't be added.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_parcel_using_insurance', function($usingInsurance, $orderNumber, $isReturnLabel){
		$usingInsurance = 'yes';
		return $usingInsurance;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_letter_parcel_insurance_value</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the insurance amount during the generation of a label.
			The available insurance values are listed <a target="_blank"
														 href="https://www.colissimo.fr/doc-colissimo/redoc-sls/en#section/Available-Shipment-options/Insured-value:-tag-insuranceValue">here</a>
		</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_parcel_insurance_value', function($insuranceValue, $orderNumber, $isReturnLabel){
		$insuranceValue = '1000';
		return $insuranceValue;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_letter_parcel_instructions</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the customer's shipping notes sent when generating a label.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_parcel_instructions', function($instructions, $orderNumber, $isReturnLabel){
		$instructions = 'Please leave the parcel in the mailbox.';
		return $instructions;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_letter_customs_declarations</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the customs declarations sent when generating a label.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_customs_declarations', function($customsDeclarations, $orderNumber, $isReturnLabel){
		$customsDeclarations['contents']['category']['value'] = '3';
		return $customsDeclarations;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_letter_service_total_amount</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the total shipping cost declared in the customs declaration when generating a label.
			This amount must be in euros and not free for the customs declaration to be valid.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_service_total_amount', function($totalAmount, $orderNumber, $isReturnLabel){
		$totalAmount = 10.50;
		return $totalAmount;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_eori_number</h3>
		<p>includes/label/lpc_label_generation_payload.php</p>
		<p>This filter allows you to modify the EORI number sent for the customs declaration when generating a label.
			By default, the EORI number has a dedicated option in the plugin's settings for the UK and the rest.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_eori_number', function($eoriNumber, $orderNumber, $isReturnLabel){
		$eoriNumber = 'GB123456789012345';
		return $eoriNumber;
	}, 10, 3);
		</pre>
	</div>
	<div>
		<h3>lpc_pdf_label</h3>
		<p>admin/labels/download/lpc_label_outward_download_action.php</p>
		<p>This filter allows you to modify the PDF content when downloading a label from the Colissimo listing.
			By default, the PDF contains the label, the customs declaration and the invoice.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_pdf_label', function($filesToMerge, $label, $labelFilename, $invoiceFilename, $cn23Filename){
		$filesToMerge = [];
		$filesToMerge[] = $labelFilename;
		$filesToMerge[] = $invoiceFilename;
		$filesToMerge[] = $cn23Filename;

		return $filesToMerge;
	}, 10, 5);
		</pre>
	</div>
	<div>
		<h3>lpc_payload_letter_parcel_weight_checkout</h3>
		<p>includes/shipping/lpc_abstract_shipping.php</p>
		<p>This filter allows you to modify the parcel's total weight (products + packaging) when calculating the shipping price on the checkout.
			$totalWeight may be in grams or kilograms depending on the WooCommerce settings.
			$package contains the order information (shipping address, products, etc...).</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_payload_letter_parcel_weight_checkout', function($totalWeight, $package){
		$totalWeight = 100;
		return $totalWeight;
	}, 10, 2);
		</pre>
	</div>
	<div>
		<h3>lpc_unified_tracking_api_change_order_status</h3>
		<p>includes/tracking/lpc_unified_tracking_api.php</p>
		<p>This filter is used when the order statuses are updated based on the shipping status.
			The dedicated option is "Order status when order is delivered" in the plugin's settings.
			To not change the order status, return an empty string.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_unified_tracking_api_change_order_status', function($newOrderStatus, $order){
		$newOrderStatus = 'wc-completed';
		return $newOrderStatus;
	}, 10, 2);
		</pre>
	</div>
	<div>
		<h3>lpc_update_delivery_status_period</h3>
		<p>includes/orders/lpc_order_queries.php</p>
		<p>This filter is used when the orders delivery statuses are refreshed.
			By default the plugin takes the orders of the 90 previous days that haven't been delivered yet, you can change the number of days with this filter.</p>
		<p>Example:</p>
		<pre>
	add_filter('lpc_update_delivery_status_period', function($timePeriod){
		$timePeriod = '-30 days';
		return $timePeriod;
	});
		</pre>
	</div>
</div>
