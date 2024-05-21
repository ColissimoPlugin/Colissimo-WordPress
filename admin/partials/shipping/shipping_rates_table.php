<?php
$shippingMethod   = $args['shippingMethod'];
$shippingClasses  = $args['shippingClasses'];
$currentRates     = $shippingMethod->get_option('shipping_rates', []);
$importTipMessage = __('The file you import must be a CSV with the columns min_weight, max_weight, min_price, max_price, shipping_class, price', 'wc_colissimo');
?>
<tr valign="top">
	<th scope="row" class="titledesc"><label><?php esc_html_e('Shipping rates', 'wc_colissimo'); ?></label></th>
	<td class="forminp" id="<?php echo $shippingMethod->id; ?>_shipping_rates" style="overflow: auto;">
		<fieldset>
			<table class="shippingrows widefat" cellspacing="0">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox"></td>
                        <?php
                        $currency    = get_woocommerce_currency();
                        $currencyTxt = ' (' . $currency . ' ' . get_woocommerce_currency_symbol($currency) . ')';
                        $weightUnit  = ' (' . LpcHelper::get_option('woocommerce_weight_unit', '') . ')';
                        ?>
						<th>
                            <?php esc_html_e(__('From weight', 'wc_colissimo') . $weightUnit); ?>
                            <?php echo LpcHelper::tooltip(__('Included', 'wc_colissimo')); ?>
						</th>
						<th>
                            <?php esc_html_e(__('To weight', 'wc_colissimo') . $weightUnit); ?>
                            <?php echo LpcHelper::tooltip(__('Excluded', 'wc_colissimo')); ?>
						</th>
						<th>
                            <?php esc_html_e(__('From cart price', 'wc_colissimo') . $currencyTxt); ?>
                            <?php echo LpcHelper::tooltip(__('Included', 'wc_colissimo')); ?>
						</th>
						<th>
                            <?php esc_html_e(__('To cart price', 'wc_colissimo') . $currencyTxt); ?>
                            <?php echo LpcHelper::tooltip(__('Excluded', 'wc_colissimo')); ?>
						</th>
						<th>
                            <?php esc_html_e('Shipping class', 'wc_colissimo'); ?>
						</th>
						<th>
                            <?php esc_html_e('Price', 'wc_colissimo'); ?>
						</th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th colspan="7" id="lpc_shipping_rates_actions">
							<div style="margin-bottom: 8px;">
								<button type="button" class="add button" id="lpc_shipping_rates_add" style="margin-left: 8px">
                                    <?php esc_html_e('Add rate', 'wc_colissimo'); ?>
								</button>
								<button type="button" class="remove button" id="lpc_shipping_rates_remove">
                                    <?php esc_html_e('Delete selected', 'wc_colissimo'); ?>
								</button>
							</div>

							<div>
								<input type="file" id="lpc_shipping_rates_import" name="lpc_shipping_rates_import">
                                <?php echo LpcHelper::tooltip($importTipMessage); ?>
								<button lpc-ajax-url="<?php echo esc_url($args['importUrl']); ?>"
										type="button"
										class="button"
										id="lpc_shipping_rates_import_button">
                                    <?php esc_html_e('Import a CSV file', 'wc_colissimo'); ?>
								</button>
								<button data-lpc-ajax-url="<?php echo esc_url($args['importDefaultUrl']); ?>"
										type="button"
										class="button"
										id="lpc_shipping_rates_import_default_button">
                                    <?php esc_html_e('Replace by Colissimo prices', 'wc_colissimo'); ?>
								</button>
								<a class="button lpc__admin__shipping__rates__actions__export"
								   href="<?php echo esc_url($args['exportUrl']); ?>"
								   id="lpc_shipping_rates_export">
                                    <?php esc_html_e(__('Export', 'wc_colissimo')); ?>
								</a>
							</div>
						</th>
					</tr>
				</tfoot>
				<tbody class="table_rates">
                    <?php
                    // From version 1.4, every shipping rates can have multiple shipping classes
                    $isFromPre14Configuration = false;
                    array_walk(
                        $currentRates,
                        function (&$rate) use (&$isFromPre14Configuration) {
                            if (isset($rate['shipping_class']) && !is_array($rate['shipping_class'])) {
                                $isFromPre14Configuration = true;
                                $rate['shipping_class']   = [$rate['shipping_class']];
                            }
                        }
                    );

                    // Migration process from version 1.2 and 1.3
                    if ($isFromPre14Configuration) {
                        $result             = [];
                        $alreadyProcessedId = [];

                        foreach ($currentRates as $i => $rate) {
                            if (isset($rate['shipping_class'])) {
                                if (in_array($i, $alreadyProcessedId)) {
                                    continue;
                                }

                                $alreadyProcessedId[] = $i;
                                $tmpRate              = $rate;

                                foreach ($currentRates as $testKey => $testRate) {
                                    if (
                                        $testRate['min_price'] === $rate['min_price']
                                        && $testRate['max_price'] === $rate['max_price']
                                        && $testRate['min_weight'] === $rate['min_weight']
                                        && $testRate['max_weight'] === $rate['max_weight']
                                        && $testRate['price'] === $rate['price']
                                        && !in_array(
                                            LpcAbstractShipping::LPC_ALL_SHIPPING_CLASS_CODE,
                                            $testRate['shipping_class']
                                        )
                                        && !in_array(
                                            LpcAbstractShipping::LPC_ALL_SHIPPING_CLASS_CODE,
                                            $rate['shipping_class']
                                        )
                                        && !in_array($testKey, $alreadyProcessedId)
                                    ) {
                                        $tmpRate['shipping_class'] = array_merge(
                                            $tmpRate['shipping_class'],
                                            $testRate['shipping_class']
                                        );

                                        $alreadyProcessedId[] = $testKey;
                                    }
                                }

                                $result[] = $tmpRate;
                            }
                        }

                        if (!empty($result)) {
                            $currentRates = $result;
                        }
                    }

                    $counter = 0;
                    $len     = count($currentRates);

                    foreach ($currentRates as $i => $rate) {
                        // Migration process from version 1.1 or lower
                        if (isset($rate['weight'])) {
                            if ('yes' === $shippingMethod->get_instance_option('use_cart_price', 'no')) {
                                // The old configuration only had the "weight" name, that could contain either a weight or a price so it's normal to do price = weight
                                $rate['min_price'] = $rate['weight'];
                                $rate['max_price'] = $i === $len - 1
                                    ? $shippingMethod->get_instance_option('max_weight', 99999)
                                    : $currentRates[$counter + 1]['weight'];

                                $rate['min_weight'] = 0;
                                $rate['max_weight'] = 99999;
                            } else {
                                $rate['min_weight'] = $rate['weight'];
                                $rate['max_weight'] = $i === $len - 1
                                    ? $shippingMethod->get_instance_option('max_weight', 99999)
                                    : $currentRates[$counter + 1]['weight'];

                                $rate['min_price'] = 0;
                                $rate['max_price'] = 99999;
                            }

                            $counter ++;
                        }
                        ?>
						<tr>
							<td class="check-column"><input type="checkbox" /></td>
							<td style="text-align: center">
								<input type="number"
									   class="input-number regular-input"
									   step="any"
									   min="0"
									   value="<?php echo isset($rate['min_weight']) ? esc_attr($rate['min_weight']) : ''; ?>"
									   name="shipping_rates[<?php echo $i; ?>][min_weight]" />
							</td>
							<td style="text-align: center">
								<input type="number"
									   class="input-number regular-input"
									   step="any"
									   min="0"
									   value="<?php echo isset($rate['max_weight']) ? esc_attr($rate['max_weight']) : ''; ?>"
									   name="shipping_rates[<?php echo $i; ?>][max_weight]" />
							</td>
							<td style="text-align: center">
								<input type="number"
									   class="input-number regular-input"
									   step="any"
									   min="0"
									   value="<?php echo isset($rate['min_price']) ? esc_attr($rate['min_price']) : ''; ?>"
									   name="shipping_rates[<?php echo $i; ?>][min_price]" />
							</td>
							<td style="text-align: center">
								<input type="number"
									   class="input-number regular-input"
									   step="any"
									   min="0"
									   value="<?php echo isset($rate['max_price']) ? esc_attr($rate['max_price']) : ''; ?>"
									   name="shipping_rates[<?php echo $i; ?>][max_price]" />
							</td>
							<td style="text-align: center">
								<select style="width: auto; max-width: 10rem"
										name="shipping_rates[<?php echo $i; ?>][shipping_class][]"
										multiple="multiple"
										class="lpc__shipping_rates__shipping_class__select">
									<option value="<?php echo LpcAbstractShipping::LPC_ALL_SHIPPING_CLASS_CODE; ?>"
                                        <?php selected(
                                            empty($rate['shipping_class']) || in_array(
                                                LpcAbstractShipping::LPC_ALL_SHIPPING_CLASS_CODE,
                                                $rate['shipping_class']
                                            )
                                        ); ?>>
                                        <?php esc_html_e('All products', 'wc_colissimo'); ?>
									</option>
                                    <?php
                                    foreach ($shippingClasses as $oneClass) {
                                        echo '<option value="' . esc_attr($oneClass->term_id) . '" ' . selected(
                                                isset($rate['shipping_class']) && in_array(
                                                    $oneClass->term_id,
                                                    $rate['shipping_class']
                                                ),
                                                true,
                                                false
                                            )
                                             . '>' . esc_html($oneClass->name) . '</option>';
                                    }
                                    ?>
								</select>
							</td>
							<td style="text-align: center">
								<input type="number"
									   class="input-number regular-input"
									   step="any"
									   min="0"
									   required
									   value="<?php echo esc_attr($rate['price']); ?>"
									   name="shipping_rates[<?php echo $i; ?>][price]" />
							</td>
						</tr>
                        <?php $counter ++;
                    } ?>
				</tbody>
			</table>
			<div style="margin-top: 14px;">
                <?php
                if (LpcSignDDP::ID === $shippingMethod->id) {
                    esc_html_e(
                        __(
                            'This shipping method lets you configure the "Colissimo with signature - DDP Option" shipping that is available and shown only for the following countries: Australia, Bahrain, Canada, China, Egypt, Hong Kong, Indonesia, Japan, Kuwait, Mexico, Oman, Philippines, Saudi Arabia, Singapore, South Africa, South Korea, Switzerland, Thailand, United Arab Emirates, United Kingdom, United States (USA).',
                            'wc_colissimo'
                        )
                    );
                    echo '<br />';
                    esc_html_e(
                        __(
                            'It is restricted to commercial shipments between 160€ and 1050€ included.',
                            'wc_colissimo'
                        )
                    );
                } elseif (LpcExpertDDP::ID === $shippingMethod->id) {
                    esc_html_e(
                        __(
                            'This shipping method lets you configure the "Colissimo International - DDP Option" shipping that is available and shown only for the following countries: Australia, Bahrain, Canada, China, Egypt, Hong Kong, Indonesia, Japan, Kuwait, Mexico, Oman, Philippines, Saudi Arabia, Singapore, South Africa, South Korea, Switzerland, Thailand, United Arab Emirates, United States (USA).',
                            'wc_colissimo'
                        )
                    );
                }
                ?>
			</div>
		</fieldset>
	</td>
</tr>
<div id="lpc_shipping_classes_example" style="display: none">
	<option selected="selected" value="<?php echo LpcAbstractShipping::LPC_ALL_SHIPPING_CLASS_CODE; ?>">
        <?php esc_html_e('All products', 'wc_colissimo'); ?>
	</option>
    <?php
    foreach ($shippingClasses as $oneClass) {
        echo '<option value="' . esc_attr($oneClass->term_id) . '">' . esc_html($oneClass->name) . '</option>';
    }
    ?>
</div>
<style>
	label{
		font-weight: bold;
	}

	fieldset{
		margin-bottom: 1rem;
	}

	.select2{
		width: auto !important;
	}
</style>
