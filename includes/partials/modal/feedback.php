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
            jQuery('#<?php echo $this->elementId; ?>').trigger('click');
            jQuery('#lpc-feedback-close-button').on('click', function () {
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'lpc_feedback_dismissed'
                    }
                });
                jQuery('.modal-close').trigger('click');
            });
        }, 1000);
    });
</script>

<?php

$this->content = '<div id="feedback_prompt_container">';
$this->content .= '<div id="feedback_prompt_message">' . __('Would you like to help us improve our plugin by answering our questionnaire?', 'wc_colissimo') . '</div>';
$this->content .= '<button type="button" class="button-secondary" id="lpc-feedback-close-button">' . __('No, thanks', 'wc_colissimo') . '</button>';
$formUrl       = admin_url('admin.php?page=wc-settings&tab=lpc&section=feedback');
$this->content .= '<a href="' . $formUrl . '" class="button-primary">' . __('Sure, why not!', 'wc_colissimo') . '</a>';
$this->content .= '</div>';
