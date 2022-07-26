<?php
if (file_exists(LPC_FOLDER . 'dev-tools' . DS . 'capabilities' . DS . 'lpc_capabilities_file.php')) {

    $capabilities = LpcRegister::get('capabilitiesDev');
    ?>
	<h1><?php esc_html_e('Import / export capabilities per country', 'wc_colissimo'); ?></h1>
	<div>
		<label><?php esc_html_e('Export capabilities by country to CSV', 'wc_colissimo'); ?></label>
		<a class="button"
		   href="<?php echo $capabilities->getUrlExport(LpcCapabilitiesPerCountry::FROM_FR); ?>">
            <?php echo sprintf(__('Export %s file', 'wc_colissimo'), LpcCapabilitiesPerCountry::FROM_FR); ?>
		</a>
		<a class="button"
		   href="<?php echo $capabilities->getUrlExport(LpcCapabilitiesPerCountry::FROM_DOM1); ?>">
            <?php echo sprintf(__('Export %s file', 'wc_colissimo'), LpcCapabilitiesPerCountry::FROM_DOM1); ?>
		</a>
	</div>
	<div>
		<label><?php esc_html_e('Import capabilities by country file', 'wc_colissimo'); ?></label>
		<form method="post" action="<?php echo $capabilities->getUrlImport(); ?>" enctype="multipart/form-data">
			<input type="file" name="<?php echo LpcCapabilitiesFile::CAPABILITIES_DEV_FILE; ?>">
			<input class="button" type="submit" value="<?php esc_html_e('Import', 'wc_colissimo'); ?>">
		</form>
	</div>

<?php } ?>
