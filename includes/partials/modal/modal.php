<script type="text/template" id="tmpl-<?php echo $this->templateId; ?>">
	<div class="lpc-modal">
		<div class="wc-backbone-modal">
			<div class="wc-backbone-modal-content">
				<section class="wc-backbone-modal-main" role="main">
					<header class="wc-backbone-modal-header">
						<h1><?php echo esc_html_e($this->title, 'wc_colissimo'); ?></h1>
						<button class="modal-close modal-close-link dashicons dashicons-no-alt">
							<span class="screen-reader-text"><?php echo esc_html_e('Close modal panel', 'woocommerce'); ?></span>
						</button>
					</header>
					<article>
                        <?php echo $this->content; ?>
					</article>
				</section>
			</div>
			<div class="wc-backbone-modal-backdrop modal-close"></div>
		</div>
	</div>
</script>
