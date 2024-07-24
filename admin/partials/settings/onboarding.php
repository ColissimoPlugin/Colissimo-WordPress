<?php
$faciliteUrl      = 'https://www.colissimo.entreprise.laposte.fr/contrat-facilite';
$privilegeUrl     = 'https://www.colissimo.entreprise.laposte.fr/contrat-privilege';
$colissimoWebsite = 'https://www.colissimo.entreprise.laposte.fr/';

$originAddress     = admin_url('admin.php?page=wc-settings&tab=lpc&section=main#lpc_pwd_webservices');
$labelFormat       = admin_url('admin.php?page=wc-settings&tab=lpc&section=label#lpc_calculate_shipping_before_taxes');
$printerConnection = admin_url('admin.php?page=wc-settings&tab=lpc&section=label#lpc_zpldpl_labels_port');
$packagingWeight   = admin_url('admin.php?page=wc-settings&tab=lpc&section=label#lpc_using_insurance_inward');
$customsOptions    = admin_url('admin.php?page=wc-settings&tab=lpc&section=custom');
$shopAddress       = admin_url('admin.php?page=wc-settings&tab=general');
$shippingTab       = admin_url('admin.php?page=wc-settings&tab=shipping');
$colissimoListing  = admin_url('admin.php?page=wc_colissimo_view');
$videoTutorials    = admin_url('admin.php?page=wc-settings&tab=lpc&section=video');
?>
<tr>
	<td class="forminp forminp-<?php echo esc_attr(sanitize_title($field['type'])); ?>">
		<p>
            <?php echo wp_kses_post(__('<u>Not yet registered on Colissimo?</u> Here is how to do it:', 'wc_colissimo')); ?>
		</p>
		<p>
			- <?php
            printf(
                __('Choose %s best matching your needs.', 'wc_colissimo'),
                '<a target="_blank" href="' . esc_url($args['contractTypes']) . '">' . __('the contract', 'wc_colissimo') . '</a>'
            );
            ?>
		</p>
		<p>
			- <?php
            printf(
                __('To register for the %1$s option, fill %2$s to create an account and receive your ids within a few days.', 'wc_colissimo'),
                '<a target="_blank" href="' . esc_url($faciliteUrl) . '">Colissimo Facilité</a>',
                '<a target="_blank" href="' . esc_url($args['faciliteForm']) . '">' . __('this commercial contact form', 'wc_colissimo') . '</a>'
            );
            ?>
		</p>
		<p>
			- <?php
            printf(
                __('To register for the %1$s option, fill %2$s to be contacted by a sales representative.', 'wc_colissimo'),
                '<a target="_blank" href="' . esc_url($privilegeUrl) . '">Colissimo Privilège</a>',
                '<a target="_blank" href="' . esc_url($args['privilegeForm']) . '">' . __('this commercial contact form', 'wc_colissimo') . '</a>'
            );
            ?>
		</p>
		<br>
		<p>
            <?php echo wp_kses_post(__('<u>Already registered on Colissimo?</u> Here is how to configure your plugin:', 'wc_colissimo')); ?>
		</p>
		<br>
		<b>1. <?php esc_html_e('I associate my account to the Colissimo plugin', 'wc_colissimo'); ?></b>
		<p>
            <?php
            esc_html_e('To connect the plugin, you must enter your Colissimo credentials in the "General" tab of this plugin.', 'wc_colissimo');
            echo ' ';
            printf(
                __('The ID must be a 6 digits number, this is the one you use to connect to %s.', 'wc_colissimo'),
                '<a target="_blank" href="' . esc_url($colissimoWebsite) . '">Colissimo Entreprise</a>'
            );
            ?>
		</p>
		<br>
		<br>
		<b>2. <?php esc_html_e('I configure my settings to simplify my shipping preparations thanks to Colissimo features', 'wc_colissimo'); ?></b>
		<p>- <?php
            printf(
                __('Enter %s to be able to generate labels', 'wc_colissimo'),
                '<a target="_blank" href="' . esc_url($originAddress) . '">' . __('your origin address', 'wc_colissimo') . '</a>'
            );
            ?>
		</p>
		<p>- <?php
            printf(
                __('%s of your labels (either PDF or format for thermal printing)', 'wc_colissimo'),
                '<a target="_blank" href="' . esc_url($labelFormat) . '">' . __('Choose the format', 'wc_colissimo') . '</a>'
            );
            ?>
		</p>
		<p>- <?php
            printf(
                __('In case of thermal printing, %s to your printer', 'wc_colissimo'),
                '<a target="_blank" href="' . esc_url($printerConnection) . '">' . __('configure the connection', 'wc_colissimo') . '</a>'
            );
            ?>
		</p>
		<p>- <?php
            printf(
                __('Enter %s for your parcels', 'wc_colissimo'),
                '<a target="_blank" href="' . esc_url($packagingWeight) . '">' . __('the packaging weight', 'wc_colissimo') . '</a>'
            );
            ?>
		</p>
		<p>- <?php
            printf(
                __('Enter %s for your shipments outside the European Union', 'wc_colissimo'),
                '<a target="_blank" href="' . esc_url($customsOptions) . '">' . __('your customs formalities', 'wc_colissimo') . '</a>'
            );
            ?>
		</p>
		<br>
		<br>
		<b>3. <?php esc_html_e('I make sure the WooCommerce settings are complete', 'wc_colissimo'); ?></b>
		<p>- <?php
            printf(
                __('%s must be complete', 'wc_colissimo'),
                '<a target="_blank" href="' . esc_url($shopAddress) . '">' . __('The shop address', 'wc_colissimo') . '</a>'
            );
            ?>
		</p>
		<p>- <?php esc_html_e('All the products must have a weight under their "Shipping" section', 'wc_colissimo'); ?></p>
		<p>- <?php esc_html_e('The products may have a custom HS code and country of manufacture under their "Attributes" section', 'wc_colissimo'); ?></p>
		<br>
		<br>
		<b>4. <?php esc_html_e('I configure my shipping rates', 'wc_colissimo'); ?></b>
		<p>- <?php
            printf(
                __('Head to %s', 'wc_colissimo'),
                '<a target="_blank" href="' . esc_url($shippingTab) . '">' . __('the "Shipping" tab', 'wc_colissimo') . '</a>'
            );
            ?>
		<p>- <?php esc_html_e('The Colissimo plugin automatically creates its zones based on shipping costs rules', 'wc_colissimo'); ?></p>
		<p>- <?php esc_html_e('Activate the shipping methods you want to show to your customers', 'wc_colissimo'); ?></p>
		<p>- <?php esc_html_e('You can customize the titles and shipping prices of the delivery solutions for your customers', 'wc_colissimo'); ?></p>
		<br>
		<p>
            <?php
            esc_html_e(
                'Beware: Colissimo doesn\'t handle parcels over 30kg, including packaging weight. Be careful about the weight unit and when you modify the weight ranges.',
                'wc_colissimo'
            );
            ?>
		</p>
		<br>
		<br>
		<b><?php esc_html_e('Everything is ready!', 'wc_colissimo'); ?></b>
		<p><?php
            printf(
                __('We have created a special page just for you, to view your new orders in %s', 'wc_colissimo'),
                '<a target="_blank" href="' . esc_url($colissimoListing) . '">WooCommerce > Colissimo</a>'
            );
            ?>
			<br>
			<br>
			<b><?php esc_html_e('Need help?', 'wc_colissimo'); ?></b>
		<p>
            <?php
            printf(
                __('You will find video tutorials on %s.', 'wc_colissimo'),
                '<a target="_blank" href="' . esc_url($videoTutorials) . '">' . __('this page', 'wc_colissimo') . '</a>'
            );
            ?>
		</p>
		<p>
            <?php
            printf(
                __('You can also contact the Colissimo support by phone at %1$s or the technical support by email at %2$s', 'wc_colissimo'),
                '<a href="tel:' . LPC_CONTACT_PHONE . '">' . LPC_CONTACT_PHONE . '</a>',
                '<a href="mailto:' . LPC_CONTACT_EMAIL . '">' . LPC_CONTACT_EMAIL . '</a>'
            );
            ?>
		</p>
	</td>
</tr>
