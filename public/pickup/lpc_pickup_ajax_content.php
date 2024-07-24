<?php

class LpcPickupAjaxContent extends LpcComponent {

    protected $lpcPickupWebService;
    protected $lpcPickupWidget;

    public function __construct(LpcPickupWebService $lpcPickupWebService = null, LpcPickupWidget $lpcPickupWidget = null) {
        $this->lpcPickupWebService = LpcRegister::get('pickupWebService', $lpcPickupWebService);
        $this->lpcPickupWidget     = LpcRegister::get('pickupWidget', $lpcPickupWidget);
    }

    public function getDependencies(): array {
        return ['pickupWebService', 'pickupWidget'];
    }

    public function init() {
        add_action('wp_ajax_lpc_pickup_ajax_content', [$this, 'sendPickupContent']);
        add_action('wp_ajax_nopriv_lpc_pickup_ajax_content', [$this, 'sendPickupContent']);
    }

    public function sendPickupContent() {
        if ('widget' === LpcHelper::get_option('lpc_pickup_map_type', 'widget')) {
            $content = $this->lpcPickupWidget->getWidgetModal(true, true);
        } else {
            $content = $this->lpcPickupWebService->getWebserviceModal(true);
        }

        LpcHelper::endAjax(true, ['content' => $content]);
    }
}
