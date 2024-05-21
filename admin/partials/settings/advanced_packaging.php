<tr>
	<th scope="row">
		<label>
            <?php
            esc_html_e('Advanced packagings', 'wc_colissimo');
            echo LpcHelper::tooltip(
                __('If a packaging fits the cart, it will be used for the shipping price calculation. Otherwise, the default packaging weight will be used.', 'wc_colissimo')
            );
            ?>
		</label>
	</th>
	<td>
		<div id="lpc_packaging_weight">
			<table>
				<thead<?php echo empty($args['packagings']) ? ' style="display: none;"' : ''; ?>>
					<tr>
						<th class="lpc_column_checkbox"><input type="checkbox" id="lpc_packaging_all" /></th>
						<th class="lpc_column_priority">
                            <?php
                            esc_html_e('Priority', 'wc_colissimo');
                            echo LpcHelper::tooltip(__('If multiple packagings fit the cart, the one with the highest priority will be used.', 'wc_colissimo'));
                            ?>
						</th>
						<th class="lpc_column_name"><?php esc_html_e('Name', 'wc_colissimo'); ?></th>
						<th class="lpc_column_packaging_weight">
                            <?php
                            echo esc_html(sprintf(__('Packaging weight (%s)', 'wc_colissimo'), $args['weightUnit']));
                            echo LpcHelper::tooltip(__('The packaging weight will be added to the products weight on label generation.', 'wc_colissimo'));
                            ?>
						</th>
						<th class="lpc_column_max_products">
                            <?php
                            esc_html_e('Max nb of products', 'wc_colissimo');
                            echo LpcHelper::tooltip(__('The maximum number of products the package can contain.', 'wc_colissimo'));
                            ?>
						</th>
						<th class="lpc_column_max_weight">
                            <?php
                            echo esc_html(sprintf(__('Maximum weight (%s)', 'wc_colissimo'), $args['weightUnit']));
                            echo LpcHelper::tooltip(__('The maximum weight the package can hold.', 'wc_colissimo'));
                            ?>
						</th>
						<th class="lpc_column_extra_cost">
                            <?php
                            esc_html_e('Extra cost', 'wc_colissimo');
                            echo LpcHelper::tooltip(
                                __('If the packaging used has an extra cost, it will be added to the calculated shipping price for the customer.', 'wc_colissimo')
                            );
                            ?>
						</th>
						<th class="lpc_column_dimensions"><?php esc_html_e('Dimensions (cm)', 'wc_colissimo'); ?></th>
						<th class="lpc_column_actions"><?php esc_html_e('Actions', 'wc_colissimo'); ?></th>
					</tr>
				</thead>
				<tbody>
                    <?php
                    foreach ($args['packagings'] as $packaging) {
                        ?>
						<tr class="lpc_packaging_row" data-lpc-packaging="<?php echo esc_attr(json_encode($packaging)); ?>">
							<td class="lpc_column_checkbox">
								<input type="checkbox" />
							</td>
							<td class="lpc_column_priority">
								<button type="button" class="button button-secondary packaging_priority_up">
									<span class="dashicons dashicons-arrow-up-alt2"></span>
								</button>
								<button type="button" class="button button-secondary packaging_priority_down">
									<span class="dashicons dashicons-arrow-down-alt2"></span>
								</button>
							</td>
							<td class="lpc_column_name">
								<div><?php esc_html_e($packaging['name']); ?></div>
							</td>
							<td class="lpc_column_packaging_weight">
								<div><?php esc_html_e(floatval($packaging['weight']) . $args['weightUnit']); ?></div>
							</td>
							<td class="lpc_column_max_products">
								<div><?php echo empty($packaging['max_products']) ? '∞' : intval($packaging['max_products']); ?></div>
							</td>
							<td class="lpc_column_max_weight">
								<div><?php echo empty($packaging['max_weight']) ? '∞' : floatval($packaging['max_weight']) . $args['weightUnit']; ?></div>
							</td>
							<td class="lpc_column_extra_cost">
								<div><?php echo empty($packaging['extra_cost']) ? '0' : floatval($packaging['extra_cost']); ?>€</div>
							</td>
							<td class="lpc_column_dimensions">
								<div><?php esc_html_e(floatval($packaging['length']) . 'x' . floatval($packaging['width']) . 'x' . floatval($packaging['depth'])); ?></div>
							</td>
							<td class="lpc_column_actions">
								<span class="dashicons dashicons-edit lpc_packaging_edit" title="<?php esc_attr_e('Edit', 'wc_colissimo'); ?>"></span>
								<span class="dashicons dashicons-trash lpc_packaging_delete" title="<?php esc_attr_e('Delete', 'wc_colissimo'); ?>"></span>
							</td>
						</tr>
                        <?php
                    }
                    ?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="9">
                            <?php $args['modal']->echo_modalAndButton(__('Add a new packaging', 'wc_colissimo')); ?>
							<button type="button"
									class="button button-secondary"
									id="lpc_delete_packaging"<?php echo empty($args['packagings']) ? ' style="display: none;"' : ''; ?>>
                                <?php esc_html_e('Delete packagings', 'wc_colissimo'); ?>
							</button>
						</td>
					</tr>
				</tfoot>
			</table>
			<input type="hidden" id="lpc_packaging_nonce" value="<?php echo esc_attr(wp_create_nonce('lpc_packaging_nonce')); ?>" />
		</div>
	</td>
</tr>
