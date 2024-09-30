<tr id="lpc_secured_return_container">
	<th scope="row">
		<label>
            <?php esc_html_e('Activate secured return', 'wc_colissimo'); ?>
		</label>
	</th>
	<td>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e('Activate secured return', 'wc_colissimo'); ?></span></legend>
			<label for="lpc_secured_return">
				<input name="lpc_secured_return" id="lpc_secured_return" type="checkbox" value="1" <?php disabled(!$args['secured_return']) . checked($args['checked']); ?>>
			</label>
			<p class="description">
                <?php
                esc_html_e(
                    'Generate a QR code that your clients can scan at a post office to print a label. This format is used to secure the return parcel deposit.',
                    'wc_colissimo'
                );
                ?>
				<br />
                <?php esc_html_e('Only active for return labels generated from the client\'s order page.', 'wc_colissimo'); ?>
				<br />
                <?php
                wp_kses(
                    printf(
                        __('This option depends on the service activation in your Colissimo client space [%s]', 'wc_colissimo'),
                        '<a href="' . $args['services_url'] . '" target="_blank">' . __('activate the service', 'wc_colissimo') . '</a>'
                    ),
                    [
                        'a' => [
                            'href' => [],
                            'target' => [],
                        ],
                    ]
                );
                ?>
			</p>
		</fieldset>
		<script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                const frontReturn = document.getElementById('lpc_customers_download_return_label');
                const securedReturnContainer = document.getElementById('lpc_secured_return_container');
                const frontReturnDelayContainer = document.getElementsByClassName('wc-settings-row-lpc_customers_download_return_label_days_container')[0];
                const balReturnContainer = document.getElementsByClassName('wc-settings-row-lpc_bal_return_container')[0];

                frontReturn.addEventListener('change', function () {
                    const selectedValue = frontReturn.value;
                    if ('no' === selectedValue) {
                        securedReturnContainer.style.display = 'none';
                        frontReturnDelayContainer.style.display = 'none';
                        balReturnContainer.style.display = 'none';
                    } else {
                        securedReturnContainer.style.display = 'table-row';
                        frontReturnDelayContainer.style.display = 'table-row';
                        balReturnContainer.style.display = 'table-row';
                    }
                });

                frontReturn.dispatchEvent(new Event('change'));
            });
		</script>
	</td>
</tr>
