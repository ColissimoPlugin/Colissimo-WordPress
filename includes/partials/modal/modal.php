<script type="text/template" id="tmpl-<?php echo $this->templateId; ?>">
	<div class="lpc-modal">
		<div class="lpc-lib-modal">
			<div class="lpc-lib-modal-content">
				<section class="lpc-lib-modal-main" role="main">
					<header class="lpc-lib-modal-header">
						<h1><?php echo $this->title; ?></h1>
						<button class="modal-close modal-close-link dashicons dashicons-no-alt">
							<span class="screen-reader-text"><?php echo esc_html_e('Close modal panel', 'woocommerce'); ?></span>
						</button>
					</header>
					<article class="lpc-lib-modal-article">
                        <?php echo $this->content; ?>
					</article>
				</section>
			</div>
			<div class="lpc-lib-modal-backdrop modal-close"></div>
		</div>
	</div>
</script>
