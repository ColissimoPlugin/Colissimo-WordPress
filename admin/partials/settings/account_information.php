<?php
defined('ABSPATH') || die('Restricted Access');
?>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label>
            <?php
            esc_html_e($args['title'], 'wc_colissimo');
            echo LpcHelper::tooltip(
                __('Services enabled on your Colissimo contract', 'wc_colissimo')
            );
            ?>
		</label>
	</th>
	<td class="forminp forminp-<?php echo esc_attr(sanitize_title($args['type'])); ?>">
		<ul>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Contract type:', 'wc_colissimo'); ?></span>
				<span class="colissimo-account-information-value"><?php echo esc_html($args['contractType']); ?></span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Out-of-home contract type:', 'wc_colissimo'); ?></span>
				<span class="colissimo-account-information-value"><?php echo esc_html($args['outOfHomeContract']); ?></span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Pickup neighbor-relay option:', 'wc_colissimo'); ?></span>
				<span class="colissimo-account-information-value"><?php esc_html_e($args['pickupNeighborRelay'], 'wc_colissimo'); ?></span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Mimosa option:', 'wc_colissimo'); ?></span>
				<span class="colissimo-account-information-value"><?php esc_html_e($args['mimosa'], 'wc_colissimo'); ?></span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Secured shipping option:', 'wc_colissimo'); ?></span>
				<span class="colissimo-account-information-value"><?php esc_html_e($args['securedShipping'], 'wc_colissimo'); ?></span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Hazardous materials feature:', 'wc_colissimo'); ?></span>
				<span class="colissimo-account-information-value"><?php esc_html_e(empty($args['hazmatCategories']) ? 'Deactivated' : 'Activated', 'wc_colissimo'); ?></span>
			</li>
            <?php if (!empty($args['hazmatCategories'])) { ?>
				<li>
					<ul class="lpc_hazmat_list">
                        <?php
                        foreach ($args['hazmatCategories'] as $category) {
                            echo '<li>';
                            esc_html_e($category['label'], 'wc_colissimo');
                            echo ' : ';
                            esc_html_e($category['active'] ? 'Activated' : 'Deactivated', 'wc_colissimo');
                            echo '</li>';
                        }
                        ?>
					</ul>
				</li>
            <?php } ?>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Estimated shipping date option:', 'wc_colissimo'); ?></span>
				<span class="colissimo-account-information-value"><?php esc_html_e($args['estimatedShippingDate'], 'wc_colissimo'); ?></span>
			</li>
            <?php if ('Activated' === $args['estimatedShippingDate'] && !empty($args['estimatedShippingDateDepotList'])) { ?>
				<li>
					<span class="colissimo-account-information-label"><?php esc_html_e('Your Colissimo deposit places:', 'wc_colissimo'); ?></span>
					<ul class="lpc_depot_list">
                        <?php
                        foreach ($args['estimatedShippingDateDepotList'] as $depot) {
                            echo '<li>' . esc_html($depot['codeRegate']) . ' - ' . esc_html($depot['libellepfc']) . '</li>';
                        }
                        ?>
					</ul>
				</li>
            <?php } ?>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Secured return option:', 'wc_colissimo'); ?></span>
				<span class="colissimo-account-information-value"><?php esc_html_e($args['securedReturn'], 'wc_colissimo'); ?></span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Return in mailbox option:', 'wc_colissimo'); ?></span>
				<span class="colissimo-account-information-value"><?php esc_html_e($args['returnMailbox'], 'wc_colissimo'); ?></span>
			</li>
			<li>
				<span class="colissimo-account-information-label"><?php esc_html_e('Return in post office option:', 'wc_colissimo'); ?></span>
				<span class="colissimo-account-information-value"><?php esc_html_e($args['returnPostOffice'], 'wc_colissimo'); ?></span>
			</li>
		</ul>
	</td>
</tr>
