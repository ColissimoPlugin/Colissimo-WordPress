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
                <?php esc_html_e('If the secured return is enabled, only your customers will be able to generate return labels.', 'wc_colissimo'); ?>
				<br />
				<br />
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
	</td>
</tr>
