<?php

/**
 * Class LpcModal
 */
class LpcModal {
    protected $templateId;
    protected $content;
    protected $title;
    protected $elementId;

    public function __construct($content, $title = null, $templateId = null) {
        if (empty($templateId)) {
            $templateId = uniqid();
        }

        $this->templateId = $templateId;
        $this->elementId  = 'lpc' . random_int(1000, 9999);
        $this->content    = $content;
        $this->title      = $title;
    }

    public function registerScripts() {
        wp_register_script(
            'lpc_modal',
            plugins_url('/js/modal.js', __FILE__),
            [],
            LPC_VERSION,
            true
        );
        wp_register_style('lpc_modal', plugins_url('/css/modal.css', __FILE__), [], LPC_VERSION);
    }

    public function enqueueScripts() {
        wp_enqueue_script('lpc_modal');
        wp_enqueue_style('lpc_modal');
        wp_enqueue_style('dashicons');
    }

    public function loadScripts() {
        $this->registerScripts();
        $this->enqueueScripts();
    }

    public function setContent($content) {
        $this->content = $content;

        return $this;
    }

    public function echo_modal() {
        include LPC_INCLUDES . 'partials' . DS . 'modal' . DS . 'modal.php';

        return $this;
    }

    public function echo_button($buttonContent = null, $callback = null) {
        if (null === $buttonContent) {
            $buttonContent = __('Apply', 'wc_colissimo');
        }

        if (!empty($callback)) {
            $callback = 'data-lpc-callback="' . esc_attr($callback) . '"';
        }

        include LPC_INCLUDES . 'partials' . DS . 'modal' . DS . 'button.php';

        return $this;
    }

    public function echo_link($aContent = null, $callback = null) {
        if (null === $aContent) {
            $aContent = __('Apply', 'wc_colissimo');
        }

        if (!empty($callback)) {
            $callback = 'data-lpc-callback="' . esc_attr($callback) . '"';
        }

        include LPC_INCLUDES . 'partials' . DS . 'modal' . DS . 'link.php';

        return $this;
    }

    public function echo_modalAndButton($buttonContent = null) {
        return $this->echo_button($buttonContent)->echo_modal();
    }

    public function open_modal($partial) {
        include LPC_INCLUDES . 'partials' . DS . 'modal' . DS . $partial . '.php';

        return $this->echo_link()->echo_modal();
    }
}
