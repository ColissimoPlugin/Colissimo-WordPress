<?php $relay = $args['relay']; ?>
<?php if (!empty($relay)) { ?>
	<blockquote id="lpc_pick_up_info" data-pickup-id="<?php echo esc_attr($relay['identifiant']); ?>">
        <?php echo esc_html($relay['nom']); ?><br />
        <?php echo esc_html($relay['adresse1']); ?><br />
        <?php echo esc_html($relay['codePostal']); ?> <?php echo esc_html($relay['localite']); ?><br />
        <?php echo esc_html($relay['libellePays']); ?>
	</blockquote>
<?php } ?>
