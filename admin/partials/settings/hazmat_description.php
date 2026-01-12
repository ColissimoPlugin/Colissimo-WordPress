</tbody>
</table>
<div class="forminp-lpc_hazmat_description">
	<p>
        <?php
        esc_html_e(
            'The hazmat option lets you specify which parcel contains hazardous materials, ensuring that your goods are protected and safe during all the shipment.',
            'wc_colissimo'
        );
        ?>
	</p>
	<p>
        <?php
        esc_html_e(
            'There are a total of 5 categories of hazardous materials that can be specified on your products and/or product categories. The categories are as follows:',
            'wc_colissimo'
        );
        ?>
	</p>
	<table>
		<thead>
			<tr>
				<th><?php esc_html_e('Category', 'wc_colissimo'); ?></th>
				<th><?php esc_html_e('Maximum quantity per parcel', 'wc_colissimo'); ?></th>
				<th><?php esc_html_e('Extra cost', 'wc_colissimo'); ?></th>
				<th><?php esc_html_e('Activated', 'wc_colissimo'); ?></th>
			</tr>
		</thead>
		<tbody>
            <?php foreach ($args['hazmatCategories'] as $category) { ?>
				<tr>
					<td><?php esc_html_e($category['label'], 'wc_colissimo'); ?></td>
					<td><?php echo empty($category['max_weight']) ? '30kg' : esc_html($category['max_weight'] . 'g'); ?></td>
					<td><?php echo esc_html($category['extra_cost']); ?> €</td>
					<td><?php esc_html_e($category['active'] ? 'Yes' : 'No', 'wc_colissimo'); ?></td>
				</tr>
            <?php } ?>
		</tbody>
	</table>
</div>
<table class="form-table">
	<tbody>
