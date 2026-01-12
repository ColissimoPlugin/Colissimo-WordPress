<tr class="<?php echo esc_attr($args['row_class'] ?? ''); ?>">
	<th scope="row">
		<label>
            <?php
            esc_html_e('Cut Off hours', 'wc_colissimo');
            echo LpcHelper::tooltip(
                __('Set the daily cut-off time for order processing. Orders placed after this time will be shipped the next business day.', 'wc_colissimo')
            );
            ?>
		</label>
	</th>
	<td>
		<template id="lpc_delivery_date_exception_template">
			<div class="exception-item">
				<input type="date" class="exception-date" aria-label="Exception date">
				<select class="exception-hour" aria-label="Exception cutt off hour">
                    <?php
                    foreach ($args['hours'] as $value => $time) {
                        echo '<option value="' . esc_attr($value) . '">' . esc_html($time) . '</option>';
                    }
                    ?>
				</select>
				<button class="remove-btn">×</button>
			</div>
		</template>

		<div class="lpc_cuttoff_container">
			<div class="lpc_cuttoff_content-wrapper">
				<!-- Weekly Schedule -->
				<div>
					<h2><?php esc_html_e('Weekly Schedule', 'wc_colissimo'); ?></h2>
					<div class="weekly-schedule">
						<table id="weeklyTable">
							<thead>
								<tr>
									<th><?php esc_html_e('Day', 'wc_colissimo'); ?></th>
									<th><?php esc_html_e('Cutt Off times for orders processing', 'wc_colissimo'); ?></th>
								</tr>
							</thead>
							<tbody>
                                <?php foreach ($args['days'] as $day) { ?>
									<tr>
										<td><?php esc_html_e($day, 'wc_colissimo'); ?></td>
										<td>
											<select class="day-select" data-day="<?php echo esc_attr($day); ?>">
                                                <?php
                                                foreach ($args['hours'] as $value => $time) {
                                                    echo '<option value="' . esc_attr($value) . '">' . esc_html($time) . '</option>';
                                                }
                                                ?>
											</select>
										</td>
									</tr>
                                <?php } ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Exceptions -->
				<div>
					<h2><?php esc_html_e('Exceptions', 'wc_colissimo'); ?></h2>
					<div class="exceptions-section">
						<button class="add-exception-btn" id="addExceptionBtn">
							<span class="plus-icon">+</span>
                            <?php esc_html_e('Add Exception', 'wc_colissimo'); ?>
						</button>
						<div class="exceptions-list" id="exceptionsList"></div>
					</div>
				</div>
			</div>

			<input type="hidden" id="cuttOffInitialValues" value="<?php echo esc_attr($args['values']); ?>" />
			<input type="hidden" id="scheduleData" name="lpc_delivery_date_cuttoff_times" />
		</div>
	</td>
</tr>
