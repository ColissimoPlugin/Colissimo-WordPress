<?php
$trackingNumbers          = $args['lpc_tracking_numbers'] ?? [];
$labelFormat              = $args['lpc_label_formats'] ?? [];
$orderItems               = isset($args['lpc_order_items']) && is_array($args['lpc_order_items']) ? $args['lpc_order_items'] : [];
$weightUnity              = LpcHelper::get_option('woocommerce_weight_unit', '');
$currency                 = get_woocommerce_currency_symbol(get_woocommerce_currency());
$shippingCosts            = $args['lpc_shipping_costs'] ?? 0;
$bordereauLinks           = $args['lpc_bordereauLinks'] ?? [];
$customsDocumentsNeeded   = isset($args['lpc_customs_needed']) && $args['lpc_customs_needed'];
$sendingServiceNeeded     = isset($args['lpc_sending_service_needed']) && $args['lpc_sending_service_needed'];
$sendingServiceConfig     = $args['lpc_sending_service_config'] ?? 'partner';
$insured                  = $args['lpc_customs_insured'] ?? [];
$productCode              = $args['lpc_product_code'] ?? '';
$ddp                      = $args['lpc_ddp'] ?? false;
$onDemandServiceLink      = '<a href="' . $args['lpc_ondemand_service_url'] . '" target="_blank">' . __('here', 'wc_colissimo') . '</a>';
$onDemandMacLink          = '<a href="' . $args['lpc_ondemand_mac_url'] . '">Mac</a>';
$onDemandWindowsLink      = '<a href="' . $args['lpc_ondemand_windows_url'] . '">Windows</a>';
$collectionAllowed        = isset($args['lpc_collection_allowed']) && $args['lpc_collection_allowed'];
$totalQuantity            = 0;
$isMultiParcelsAuthorized = !empty($args['lpc_multi_parcels_authorized']);
$parcelsAmount            = empty($args['lpc_multi_parcels_amount']) ? 0 : $args['lpc_multi_parcels_amount'];
$multiParcelsExisting     = empty($args['lpc_multi_parcels_existing']) ? [] : $args['lpc_multi_parcels_existing'];
$parcelCurrentNumber      = count($multiParcelsExisting) + 1;
$multiParcelsLabels       = [
    'MASTER'   => __('Master parcel', 'wc_colissimo'),
    'FOLLOWER' => __('Follower parcel', 'wc_colissimo'),
];
$cn23Needed               = $args['lpc_cn23_needed'];
$defaultCustoms           = $args['lpc_default_customs_category'];
?>

<div class="lpc__admin__order_banner">
	<div class="lpc__admin__order_banner__header">
		<div data-lpc-tab="label_listing" class="lpc__admin__order_banner__tab lpc__admin__order_banner__header__listing nav-tab nav-tab-active">
            <?php echo __('Labels listing', 'wc_colissimo'); ?>
		</div>
        <?php if (current_user_can('lpc_manage_labels')) { ?>
			<div data-lpc-tab="generate_label" class="lpc__admin__order_banner__tab lpc__admin__order_banner__header__generation nav-tab">
                <?php echo __('Labels generation', 'wc_colissimo'); ?>
			</div>
        <?php } ?>
        <?php if (current_user_can('lpc_manage_documents') && $customsDocumentsNeeded) { ?>
			<div data-lpc-tab="send_documents" class="lpc__admin__order_banner__tab lpc__admin__order_banner__header__documents nav-tab">
                <?php echo __('Customs documents', 'wc_colissimo'); ?>
			</div>
        <?php } ?>
        <?php if ($collectionAllowed) { ?>
			<div data-lpc-tab="on_demand" class="lpc__admin__order_banner__tab lpc__admin__order_banner__header__ondemand nav-tab">
                <?php echo __('Colissimo collection', 'wc_colissimo'); ?>
			</div>
        <?php } ?>
	</div>
	<div class="lpc__admin__order_banner__content lpc__admin__order_banner__generate_label" style="display: none">
		<div class="lpc__admin__order_banner__generate_label__div">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="check-column"><input type="checkbox" class="lpc__admin__order_banner__generate_label__item__check_all" checked="checked"></th>
						<th><?php echo __('Item', 'woocommerce'); ?></th>
						<th><?php echo sprintf(__('Unit price (%s)', 'wc_colissimo'), $currency); ?></th>
						<th><?php echo __('Quantity', 'wc_colissimo'); ?></th>
						<th><?php echo sprintf(__('Unit weight (%s)', 'wc_colissimo'), $weightUnity); ?></th>
					</tr>
				</thead>
				<tbody>
                    <?php
                    $allItemsId = [];
                    foreach ($orderItems as $oneItem) {
                        $totalQuantity += $oneItem['base_qty'];
                        $allItemsId[]  = $oneItem['id'];
                        ?>
						<tr>
							<td class="lpc__admin__order_banner__generate_label__item__td__checkbox check-column">
								<input type="checkbox"
									   data-item-id="<?php echo $oneItem['id']; ?>"
									   class="lpc__admin__order_banner__generate_label__item__checkbox"
                                    <?php echo empty($oneItem['qty']) ? '' : 'checked'; ?>
									   name="<?php echo $oneItem['id'] . '-checkbox'; ?>"
									   id="<?php echo $oneItem['id'] . '-checkbox'; ?>"
								></td>
							<td><?php echo $oneItem['name']; ?></td>
							<td><input type="number"
									   class="lpc__admin__order_banner__generate_label__item__price"
									   data-item-id="<?php echo $oneItem['id']; ?>"
									   value="<?php echo $oneItem['price']; ?>"
									   name="<?php echo $oneItem['id'] . '-price'; ?>"
									   min="0"
									   step="any"
									   readonly="readonly"
								></td>
							<td><input
										style="display: inline-block; width: 50px"
										type="number"
										class="lpc__admin__order_banner__generate_label__item__qty"
										data-item-id="<?php echo $oneItem['id']; ?>"
										value="<?php echo $oneItem['qty']; ?>"
										step="any"
										min="0"
										name="<?php echo $oneItem['id'] . '-qty'; ?>"
										id="<?php echo $oneItem['id'] . '-qty'; ?>"
								><span style="margin-left: 5px">/</span><span style="margin-left: 5px"><?php echo $oneItem['base_qty']; ?></span></td>
							<td><input type="number"
									   class="lpc__admin__order_banner__generate_label__item__weight"
									   data-item-id="<?php echo $oneItem['id']; ?>"
									   value="<?php echo $oneItem['weight']; ?>"
									   min="0"
									   step="any"
									   readonly="readonly"
									   name="<?php echo $oneItem['id'] . '-weight'; ?>"
								></td>
						</tr>
                    <?php } ?>
				</tbody>
			</table>
			<div class="lpc__admin__order_banner__generate_label__edit_value__container">
				<span class="woocommerce-help-tip" data-tip="
				<?php
                esc_attr_e(
                    'Editing prices and weights may create inconsistency between CN23 or labels and invoice. Edit these values only if you really need it.',
                    'wc_colissimo'
                );
                ?>
				">
				</span>
                <?php echo __('Edit prices and weights', 'wc_colissimo'); ?>
				<span class="lpc__admin__order_banner__generate_label__edit_value woocommerce-input-toggle woocommerce-input-toggle--disabled"></span>
			</div>
			<div class="lpc__admin__order_banner__generate_label__shipping_costs__container">
				<label for="lpc__admin__order_banner__generate_label__shipping_costs" class="lpc__admin__order_banner_label">
                    <?php echo sprintf(__('Shipping costs (%s)', 'wc_colissimo'), $currency); ?>
				</label>
				<input type="number"
					   min="0"
					   step="any"
					   class="lpc__admin__order_banner__generate_label__shipping_costs"
					   id="lpc__admin__order_banner__generate_label__shipping_costs"
					   name="lpc__admin__order_banner__generate_label__shipping_costs"
					   value="<?php echo $shippingCosts; ?>"
					   readonly="readonly"
				>
			</div>
			<div class="lpc__admin__order_banner__generate_label__package_weight__container">
				<label for="lpc__admin__order_banner__generate_label__package_weight" class="lpc__admin__order_banner_label">
                    <?php echo sprintf(__('Packaging weight (%s)', 'wc_colissimo'), $weightUnity); ?>
				</label>
				<input type="number"
					   min="0"
					   step="any"
					   class="lpc__admin__order_banner__generate_label__package_weight"
					   name="lpc__admin__order_banner__generate_label__package_weight"
					   id="lpc__admin__order_banner__generate_label__package_weight"
					   value="<?php echo $args['lpc_packaging_weight']; ?>"
					   readonly="readonly"
				>
			</div>
			<div class="lpc__admin__order_banner__generate_label__total_weight__container">
                <?php echo __('Total weight (items + packaging)', 'wc_colissimo'); ?> :
				<span class="lpc__admin__order_banner__generate_label__total_weight"></span><?php echo ' ' . $weightUnity; ?>
				<input type="hidden" name="lpc__admin__order_banner__generate_label__weight__unity" value="<?php echo $weightUnity; ?>">
				<input type="hidden" name="lpc__admin__order_banner__generate_label__total_weight__input">
			</div>
            <?php if ($ddp) { ?>
				<div class="lpc__admin__order_banner__generate_label__package_length__container">
					<label for="lpc__admin__order_banner__generate_label__package_length" class="lpc__admin__order_banner_label">
                        <?php esc_html_e('Package length (cm)', 'wc_colissimo'); ?>
					</label>
					<input type="number"
						   min="0"
						   step="any"
						   class="lpc__admin__order_banner__generate_label__package_length"
						   name="lpc__admin__order_banner__generate_label__package_length"
						   id="lpc__admin__order_banner__generate_label__package_length"
						   value=""
					>
				</div>
				<div class="lpc__admin__order_banner__generate_label__package_width__container">
					<label for="lpc__admin__order_banner__generate_label__package_width" class="lpc__admin__order_banner_label">
                        <?php esc_html_e('Package width (cm)', 'wc_colissimo'); ?>
					</label>
					<input type="number"
						   min="0"
						   step="any"
						   class="lpc__admin__order_banner__generate_label__package_width"
						   name="lpc__admin__order_banner__generate_label__package_width"
						   id="lpc__admin__order_banner__generate_label__package_width"
						   value=""
					>
				</div>
				<div class="lpc__admin__order_banner__generate_label__package_height__container">
					<label for="lpc__admin__order_banner__generate_label__package_height" class="lpc__admin__order_banner_label">
                        <?php esc_html_e('Package height (cm)', 'wc_colissimo'); ?>
					</label>
					<input type="number"
						   min="0"
						   step="any"
						   class="lpc__admin__order_banner__generate_label__package_height"
						   name="lpc__admin__order_banner__generate_label__package_height"
						   id="lpc__admin__order_banner__generate_label__package_height"
						   value=""
					>
				</div>
				<div class="lpc__admin__order_banner__generate_label__package_description__container">
					<label for="lpc__admin__order_banner__generate_label__package_description" class="lpc__admin__order_banner_label">
                        <?php esc_html_e('English description', 'wc_colissimo'); ?>
						<span class="woocommerce-help-tip"
							  data-tip="<?php esc_attr_e('The customs need a description of the package\'s content, in English.', 'wc_colissimo'); ?>">
						</span>
					</label>
					<input type="text"
						   class="lpc__admin__order_banner__generate_label__package_description"
						   name="lpc__admin__order_banner__generate_label__package_description"
						   id="lpc__admin__order_banner__generate_label__package_description"
						   value=""
					>
				</div>
            <?php } ?>
            <?php if ($sendingServiceNeeded) { ?>
				<div class="lpc__admin__order_banner__generate_label__sending_service__container">
					<label for="lpc__admin__order_banner__generate_label__sending_service">
                        <?php esc_attr_e('Sending service', 'wc_colissimo'); ?>
					</label>
					<select name="lpc__admin__order_banner__generate_label__sending_service"
							class="lpc__admin__order_banner__generate_label__sending_service"
							id="lpc__admin__order_banner__generate_label__sending_service">
						<option value="partner" <?php echo 'partner' === $sendingServiceConfig ? 'selected="selected"' : ''; ?>>
                            <?php esc_attr_e('Local postal service', 'wc_colissimo'); ?>
						</option>
						<option value="dpd" <?php echo 'dpd' === $sendingServiceConfig ? 'selected="selected"' : ''; ?>>DPD</option>
					</select>
				</div>
            <?php } ?>
			<div class="lpc__admin__order_banner__generate_label__non_machinable">
				<label for="lpc__admin__order_banner__generate_label__non_machinable__input">
                    <?php echo __('Non machinable package', 'wc_colissimo'); ?>
				</label>
                <?php if (in_array($productCode, ['BPR', 'A2P', 'BDP', 'CMT'])) { ?>
					<span class="woocommerce-help-tip" data-tip="
				<?php
                    echo __(
                        'The non-machinable option isn\'t available for this shipping method and destination country.',
                        'wc_colissimo'
                    );
                    ?>
				">
				</span>
                <?php } ?>
				<input type="checkbox"
					   name="lpc__admin__order_banner__generate_label__non_machinable__input"
					   id="lpc__admin__order_banner__generate_label__non_machinable__input"
                    <?php echo in_array($productCode, ['BPR', 'A2P', 'BDP', 'CMT']) ? 'disabled="disabled"' : ''; ?>>
				<p>
                    <?php echo sprintf(__('To determine if your package is non machinable you can visit this %s', 'wc_colissimo'),
                                       '<a target="_blank" href="https://www.colissimo.entreprise.laposte.fr/fr/expedier">' . __('documentation', 'wc_colissimo') . '</a>') ?>
				</p>
			</div>
			<div class="lpc__admin__order_banner__generate_label__insurance">
				<label for="lpc_use_insurance">
                    <?php echo __('Use Colissimo Insurance?', 'wc_colissimo'); ?>
				</label>
				<input type="checkbox" <?php echo 'yes' == LpcHelper::get_option('lpc_using_insurance') ? 'checked' : ''; ?>
					   name="lpc__admin__order_banner__generate_label__using__insurance__input"
					   class="lpc__admin__order_banner__generate_label__using__insurance__input"
					   id="lpc_use_insurance">
			</div>
			<div class="lpc__admin__order_banner__generate_label__insurance__amount">
				<label for="lpc_insurance_amount">
                    <?php echo __('Personalized amount of insurance:', 'wc_colissimo'); ?>
				</label>
				<select
						class="lpc__admin__order_banner__generate_label__insurrance__amount"
						name="lpc__admin__order_banner__generate_label__insurrance__amount"
						id="lpc_insurance_amount"
                    <?php echo 'yes' == LpcHelper::get_option('lpc_using_insurance') ? '' : ' disabled="true" '; ?>>
					<option value=""><?php esc_html_e('Choose an amount', 'wc_colissimo'); ?></option>
					<option value="150">150€</option>
					<option value="300">300€</option>
					<option value="500">500€</option>
					<option value="1000">1000€</option>
                    <?php
                    if (!in_array($productCode, LpcLabelGenerationPayload::PRODUCT_CODE_INSURANCE_RELAY)) {
                        ?>
						<option value="2000">2000€</option>
						<option value="5000">5000€</option>
                        <?php
                    }
                    ?>
				</select>
			</div>
            <?php if ($cn23Needed) { ?>
				<div class="lpc__admin__order_banner__generate_label__type">
					<label for="lpc_cn23_type">
                        <?php echo __('Customs category', 'wc_colissimo'); ?>
					</label>
					<select
							class="lpc__admin__order_banner__generate_label__cn23__type"
							name="lpc__admin__order_banner__generate_label__cn23__type"
							id="lpc_cn23_type">
						<option value="1" <?php echo 1 == $defaultCustoms ? 'selected' : ''; ?>><?php echo __('Gift', 'wc_colissimo'); ?></option>
						<option value="2" <?php echo 2 == $defaultCustoms ? 'selected' : ''; ?>><?php echo __('Commercial sample', 'wc_colissimo'); ?></option>
						<option value="3" <?php echo 3 == $defaultCustoms ? 'selected' : ''; ?>><?php echo __('Commercial shipment', 'wc_colissimo'); ?></option>
						<option value="4" <?php echo 4 == $defaultCustoms ? 'selected' : ''; ?>><?php echo __('Document', 'wc_colissimo'); ?></option>
						<option value="5" <?php echo 5 == $defaultCustoms ? 'selected' : ''; ?>><?php echo __('Other', 'wc_colissimo'); ?></option>
					</select>
				</div>
            <?php } ?>
            <?php if (1 < $totalQuantity && $isMultiParcelsAuthorized) { ?>
				<div class="lpc__admin__order_banner__generate_label__multi__parcels">
					<label for="lpc_multi_parcels">
                        <?php esc_html_e('Use the multi-parcels shipping', 'wc_colissimo'); ?>
						<span class="woocommerce-help-tip" data-tip="
						<?php
                        esc_attr_e(
                            'If you want your client to receive all the parcels at the same time, activate this option and specify the exact number of parcels.',
                            'wc_colissimo'
                        );
                        ?>
						">
						</span>
					</label>
					<input type="checkbox"
						   name="lpc__admin__order_banner__generate_label__multi__parcels__input"
						   class="lpc__admin__order_banner__generate_label__multi__parcels__input"
						   id="lpc_multi_parcels"
                        <?php echo empty($parcelsAmount) ? '' : 'checked="checked"'; ?>>
				</div>
				<div class="lpc__admin__order_banner__generate_label__multi__parcels__number">
                    <?php if (empty($parcelsAmount)) { ?>
						<label for="lpc_multi_parcels_number">
                            <?php esc_html_e('Number of parcels:', 'wc_colissimo'); ?>
						</label>
						<input type="number"
							   min="2"
							   max="<?php echo intval(min($totalQuantity, 4)); ?>"
							   name="lpc__admin__order_banner__generate_label__parcels_amount"
							   id="lpc_multi_parcels_number"
							   disabled="disabled">
                    <?php } elseif ($parcelCurrentNumber <= $parcelsAmount) { ?>
                        <?php echo sprintf(__('Parcel n°%1$s / %2$s', 'wc_colissimo'), $parcelCurrentNumber, $parcelsAmount); ?>
						<span class="woocommerce-help-tip" data-tip="
						<?php
                        esc_attr_e(
                            'If you made a mistake or if you want to change the total number of parcels, you can delete generated labels.',
                            'wc_colissimo'
                        );
                        ?>
						">
						</span>
                    <?php } else { ?>
                        <?php esc_html_e('All labels have been generated.', 'wc_colissimo'); ?>
                    <?php } ?>
				</div>
            <?php } ?>
			<div class="lpc__admin__order_banner__generate_label__generate-label-button__container">
				<select name="lpc__admin__order_banner__generate_label__outward_or_inward">
					<option value="outward"><?php echo __('Outward label', 'wc_colissimo'); ?></option>
					<option value="inward"><?php echo __('Inward label', 'wc_colissimo'); ?></option>
					<option value="both"><?php echo __('Outward and inward labels', 'wc_colissimo'); ?></option>
				</select>
				<button type="button" class="button button-primary lpc__admin__order_banner__generate_label__generate-label-button"><?php echo __(
                        'Generate',
                        'wc_colissimo'
                    ); ?></button>
			</div>
		</div>
	</div>
	<div class="lpc__admin__order_banner__content lpc__admin__order_banner__label_listing">
        <?php if (empty($trackingNumbers)) {
            $message = __(
                'You don\'t have any label for this order. To generate one, please check the "Labels generation" tab',
                'wc_colissimo'
            );

            echo '<br><div class="lpc__admin__order_banner__warning"><span>' . $message . '</span></div>';
        } else { ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php echo __('Outward labels', 'wc_colissimo'); ?></th>
						<th><?php echo __('Bordereau', 'wc_colissimo'); ?></th>
						<th><?php echo __('Inward labels', 'wc_colissimo'); ?></th>
					</tr>
				</thead>
				<tbody class="lpc__admin__order_banner__label_listing__body">
                    <?php
                    foreach ($trackingNumbers as $outwardTrackingNumber => $inwardTrackingNumbers) {
                        ?>
						<tr>
							<td>
                                <?php
                                if ('no_outward' !== $outwardTrackingNumber) {
                                    $trackingLink = $args['lpc_label_queries']->getOutwardLabelLink($args['postId'], $outwardTrackingNumber);
                                    ?>
									<a target="_blank" href="<?php echo esc_url($trackingLink); ?>">
                                        <?php
                                        esc_html_e($outwardTrackingNumber);

                                        $additionalInformation = [];
                                        if (in_array($outwardTrackingNumber, $insured)) {
                                            $additionalInformation[] = __('Insured', 'wc_colissimo');
                                        }

                                        if (!empty($multiParcelsExisting[$outwardTrackingNumber])) {
                                            $additionalInformation[] = $multiParcelsLabels[$multiParcelsExisting[$outwardTrackingNumber]];
                                        }

                                        if (!empty($additionalInformation)) {
                                            echo ' (' . implode(', ', $additionalInformation) . ')';
                                        }
                                        ?>
									</a>
                                    <?php
                                    $label = $args['outwardLabelDb']->getLabel($outwardTrackingNumber);

                                    if (!empty($label->status_id)) {
                                        $labelStatus = $args['colissimoStatus']->getStatusInfo($label->status_id)['label'];
                                        echo '<br />' . esc_html($labelStatus);
                                    }
                                    ?>
									<br>
                                    <?php echo $args['lpc_label_queries']->getOutwardLabelsActionsIcons(
                                        $outwardTrackingNumber,
                                        $labelFormat[$outwardTrackingNumber],
                                        $args['lpc_redirection']
                                    );
                                } ?>
							</td>
							<td>
                                <?php
                                if ('no_outward' !== $outwardTrackingNumber) {
                                    if (!empty($bordereauLinks[$outwardTrackingNumber])) {
                                        esc_html_e(sprintf(__('Bordereau n°%d', 'wc_colissimo'), $bordereauLinks[$outwardTrackingNumber]['id']));
                                        echo '<br>';
                                        echo $args['lpc_bordereau_queries']->getBordereauActionsIcons(
                                            $bordereauLinks[$outwardTrackingNumber]['link'],
                                            $bordereauLinks[$outwardTrackingNumber]['id'],
                                            $args['order_id'],
                                            $args['lpc_redirection']
                                        );
                                    }
                                }
                                ?>
							</td>
							<td>
                                <?php foreach ($inwardTrackingNumbers as $inwardTrackingNumber) { ?>
                                    <?php echo $inwardTrackingNumber; ?>
									<br>
                                    <?php
                                    echo $args['lpc_label_queries']->getInwardLabelsActionsIcons(
                                        $inwardTrackingNumber,
                                        $labelFormat[$inwardTrackingNumber],
                                        $args['lpc_redirection']
                                    ); ?>
									<br>
                                <?php } ?>
							</td>
						</tr>
                    <?php } ?>
				</tbody>
			</table>
        <?php } ?>
	</div>
	<div class="lpc__admin__order_banner__content lpc__admin__order_banner__send_documents" style="display: none">
		<template id="lpc__admin__order_banner__send_documents__template">
			<tr>
				<td>
					<select class="lpc__admin__order_banner__document__type">
                        <?php
                        if (!empty($args['lpc_documents_types'])) {
                            foreach ($args['lpc_documents_types'] as $documentType => $description) {
                                echo '<option value="' . esc_attr($documentType) . '">' . $description . '</option>';
                            }
                        }
                        ?>
					</select>
				</td>
				<td>
					<input
							type="file"
							name="lpc__customs_document[__PARCELNUMBER__][__TYPE__][]"
							class="lpc__admin__order_banner__document__file"
							disabled="disabled" />
				</td>
			</tr>
		</template>
		<table class="wp-list-table widefat striped">
			<thead>
                <?php foreach ($trackingNumbers as $outwardTrackingNumber => $inwardTrackingNumbers) { ?>
					<tr>
						<th><?php echo $outwardTrackingNumber; ?></th>
						<td class="lpc__admin__order_banner__send_documents__container">
							<table>
								<tbody class="lpc__admin__order_banner__send_documents__listing">
                                    <?php
                                    if (!empty($args['lpc_sent_documents'][$outwardTrackingNumber])) {
                                        foreach ($args['lpc_sent_documents'][$outwardTrackingNumber] as $oneDocument) {
                                            ?>
											<tr class="lpc__customs__sent__document">
												<td>
                                                    <?php esc_html_e($args['lpc_documents_types'][$oneDocument['documentType']]); ?>
												</td>
												<td>
                                                    <?php esc_html_e($oneDocument['documentName']); ?>
												</td>
											</tr>
                                            <?php
                                        }
                                    }
                                    ?>
								</tbody>
							</table>
							<div class="text-center">
								<button type="button"
										class="button lpc__admin__order_banner__send_documents__more"
										data-lpc-parcelnumber="<?php esc_attr_e($outwardTrackingNumber); ?>">
                                    <?php esc_html_e('Add an other document', 'wc_colissimo'); ?>
								</button>
								<button type="submit" class="button button-primary lpc__admin__order_banner__send_documents__listing__send_button">
                                    <?php esc_html_e('Submit the documents', 'wc_colissimo'); ?>
								</button>
							</div>
						</td>
					</tr>
                <?php } ?>
			</thead>
		</table>
		<div style="margin-top: 1rem;">
            <?php esc_html_e('In accordance with the customs regulation, it is necessary to provide documents related to the parcels for the customs.', 'wc_colissimo'); ?>
			<br />
            <?php esc_html_e('It is possible to send these documents through the parcel tracking tool or from here using the plugin.', 'wc_colissimo'); ?>
		</div>
	</div>
	<div class="lpc__admin__order_banner__content lpc__admin__order_banner__on_demand" style="display: none">
		<br /><br /><br />
		<div><?php echo sprintf(__('To benefit from the collection, connect to your account %s and access the collection section to subscribe to the service.', 'wc_colissimo'),
                                $onDemandServiceLink); ?></div>
		<div><?php echo sprintf(__('Here is the link for %s', 'wc_colissimo'), $onDemandMacLink); ?></div>
		<div><?php echo sprintf(__('Here is the link for %s', 'wc_colissimo'), $onDemandWindowsLink); ?></div>
		<br />
		<div><?php echo __('To benefit from on-demand collection, you must have subscribed to the service from your Colissimo space.', 'wc_colissimo'); ?></div>
		<div>
			<ol>
				<li><?php echo __('If you are a Facilité customer, go to your Colissimo space in the Collection section and make a subscription request.',
                                  'wc_colissimo'); ?></li>
				<li><?php echo __('If you are a Privilege customer, contact your usual sales contact so that he can give you access to the section.', 'wc_colissimo'); ?></li>
			</ol>
		</div>
	</div>
	<input type="hidden" name="lpc__admin__order_banner__generate_label__action" value="0">
	<input type="hidden" name="lpc__admin__order_banner__generate_label__items-id" value="<?php echo serialize($allItemsId); ?>">
</div>
