<?php
defined('ABSPATH') || die('Restricted Access');
?>
	<style>
		#<?php echo $this->elementId; ?>{
		display: none;
	}
		.lpc-modal .lpc-lib-modal .lpc-lib-modal-content{
			max-width: 500px !important;
			min-width: 300px !important;
			max-height: 200px;
		}

		#feedback_prompt_container{
			text-align: center;
		}

		#feedback_prompt_message{
			text-align: left;
			margin-bottom: 2rem;
		}

		#lpc-feedback-close-button{
			margin-right: 1rem;
		}
	</style>

	<script>
        jQuery(document).on('ready', function () {
            setTimeout(function () {
                const nonceParam = <?php echo wp_json_encode(LpcAdminInit::NONCE_DISMISS_FEEDBACK); ?>;
                const nonce = <?php echo wp_json_encode(wp_create_nonce(LpcAdminInit::NONCE_NAME_DISMISS_FEEDBACK)); ?>;

                jQuery('#<?php echo $this->elementId; ?>').trigger('click');
                jQuery('#lpc-feedback-close-button').on('click', function () {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'lpc_feedback_dismissed',
                            [nonceParam]: nonce
                        }
                    });
                    jQuery('.modal-close').trigger('click');
                });
            }, 1000);
        });
	</script>

<?php

$this->content = '<div id="feedback_prompt_container">';
$this->content .= '<div id="feedback_prompt_message">' . esc_html__('Would you like to help us improve our plugin by answering our questionnaire?', 'wc_colissimo') . '</div>';
$this->content .= '<button type="button" class="button-secondary" id="lpc-feedback-close-button">' . esc_html__('No, thanks', 'wc_colissimo') . '</button>';
$formUrl       = admin_url('admin.php?page=wc-settings&tab=lpc&section=feedback');
$this->content .= '<a href="' . esc_url($formUrl) . '" class="button-primary">' . esc_html__('Sure, why not!', 'wc_colissimo') . '</a>';
$this->content .= '</div>';
