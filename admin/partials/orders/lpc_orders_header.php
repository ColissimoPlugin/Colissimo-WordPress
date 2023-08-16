<nav class="nav-tab-wrapper woo-nav-tab-wrapper" style="margin-bottom: 1rem">
	<a href="<?php echo esc_url(admin_url('admin.php?page=wc_colissimo_view&tab=orders')); ?>"
	   class="nav-tab <?php echo 'orders' === $args['tab'] ? 'nav-tab-active' : ''; ?>">
        <?php esc_html_e('Orders', 'wc_colissimo'); ?>
	</a>
	<a href="<?php echo esc_url(admin_url('admin.php?page=wc_colissimo_view&tab=slip-creation')); ?>"
	   class="nav-tab <?php echo 'slip-creation' === $args['tab'] ? 'nav-tab-active' : ''; ?>">
        <?php esc_html_e('Slip creation', 'wc_colissimo'); ?>
	</a>
	<a href="<?php echo esc_url(admin_url('admin.php?page=wc_colissimo_view&tab=slip-history')); ?>"
	   class="nav-tab <?php echo 'slip-history' === $args['tab'] ? 'nav-tab-active' : ''; ?>">
        <?php esc_html_e('Slip history', 'wc_colissimo'); ?>
	</a>
</nav>
