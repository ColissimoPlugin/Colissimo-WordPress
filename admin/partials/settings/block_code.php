<tr>
	<th scope="row">
		<label>
            <?php
            esc_html_e('Set secured code during delivery', 'wc_colissimo');
            echo wc_help_tip(
                __(
                    'To benefit from the secured code during delivery, you must contact your Colissimo advisor and they will activate it for you. This option will be visible in your Colissimo Box enterprise space.',
                    'wc_colissimo'
                )
            );
            ?>
		</label>
	</th>
	<td>
		<input type="checkbox" disabled="disabled" <?php checked($args['block_code']); ?> />
        <?php if (!$args['block_code']) { ?>
			<style>
				.wc-settings-row-lpc_domicileas_block_code_min, .wc-settings-row-lpc_domicileas_block_code_max{
					display: none;
				}
			</style>
        <?php } ?>
	</td>
</tr>
