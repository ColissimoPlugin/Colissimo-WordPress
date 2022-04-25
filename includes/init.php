<?php

defined('ABSPATH') || die('Restricted Access');

require_once LPC_INCLUDES . 'lpc_ajax.php';
require_once LPC_INCLUDES . 'lpc_db_definition.php';

require_once LPC_ADMIN . 'lpc_admin_notices.php';
require_once LPC_INCLUDES . 'label' . DS . 'lpc_outward_label_db.php';
require_once LPC_INCLUDES . 'label' . DS . 'lpc_inward_label_db.php';
require_once LPC_INCLUDES . 'label' . DS . 'lpc_label_generation_api.php';
require_once LPC_INCLUDES . 'label' . DS . 'lpc_label_packager.php';
require_once LPC_INCLUDES . 'label' . DS . 'lpc_label_generation_inward.php';
require_once LPC_INCLUDES . 'label' . DS . 'lpc_label_generation_outward.php';
require_once LPC_INCLUDES . 'label' . DS . 'lpc_label_generation_auto.php';
require_once LPC_INCLUDES . 'label' . DS . 'lpc_label_purge.php';
require_once LPC_INCLUDES . 'label' . DS . 'lpc_inward_label_download.php';
require_once LPC_INCLUDES . 'label' . DS . 'email' . DS . 'lpc_inward_label_email_manager.php';
require_once LPC_INCLUDES . 'label' . DS . 'email' . DS . 'lpc_outward_label_email_manager.php';
require_once LPC_INCLUDES . 'lpc_register_wc_email.php';
require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_capabilities_per_country.php';
require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_shipping_methods.php';
require_once LPC_INCLUDES . 'shipping' . DS . 'lpc_shipping_zones.php';
require_once LPC_INCLUDES . 'lpc_order_statuses.php';
require_once LPC_INCLUDES . 'pick_up' . DS . 'lpc_pick_up_widget_api.php';
require_once LPC_INCLUDES . 'pick_up' . DS . 'lpc_relays_api.php';
require_once LPC_INCLUDES . 'tracking' . DS . 'lpc_colissimo_status.php';
require_once LPC_INCLUDES . 'tracking' . DS . 'lpc_unified_tracking_api.php';
require_once LPC_INCLUDES . 'tracking' . DS . 'lpc_update_statuses_action.php';
require_once LPC_INCLUDES . 'orders' . DS . 'lpc_order_queries.php';
require_once LPC_INCLUDES . 'bordereau' . DS . 'lpc_bordereau_generation_api.php';
require_once LPC_INCLUDES . 'bordereau' . DS . 'lpc_bordereau_generation.php';
require_once LPC_INCLUDES . 'invoices' . DS . 'lpc_invoice_generate_action.php';
require_once LPC_INCLUDES . 'lpc_cron.php';
require_once LPC_INCLUDES . 'lpc_update.php';
require_once LPC_INCLUDES . 'customs' . DS . 'lpc_customs_documents_api.php';


class LpcIncludeInit {

    public function __construct() {
        LpcRegister::register('lpcAdminNotices', new LpcAdminNotices());

        LpcRegister::register('outwardLabelDb', new LpcOutwardLabelDb());
        LpcRegister::register('inwardLabelDb', new LpcInwardLabelDb());
        LpcRegister::register('dbDefinition', new LpcDbDefinition());

        LpcRegister::register('ajaxDispatcher', new LpcAjax());
        LpcRegister::register('invoiceGenerateAction', new LpcInvoiceGenerateAction());

        LpcRegister::register('orderStatuses', new LpcOrderStatuses());
        LpcRegister::register('shippingMethods', new LpcShippingMethods());
        LpcRegister::register('pickupWidgetApi', new LpcPickUpWidgetApi());
        LpcRegister::register('relaysApi', new LpcRelaysApi());

        LpcRegister::register('capabilitiesPerCountry', new LpcCapabilitiesPerCountry());
        LpcRegister::register('shippingZones', new LpcShippingZones());

        LpcRegister::register('labelGenerationApi', new LpcLabelGenerationApi());

        LpcRegister::register('colissimoStatus', new LpcColissimoStatus());
        LpcRegister::register('unifiedTrackingApi', new LpcUnifiedTrackingApi());
        LpcRegister::register('updateStatusesAction', new LpcUpdateStatusesAction());

        LpcRegister::register('labelPackager', new LpcLabelPackager());

        LpcRegister::register('labelGenerationInward', new LpcLabelGenerationInward());
        LpcRegister::register('labelGenerationOutward', new LpcLabelGenerationOutward());
        LpcRegister::register('labelGenerationAuto', new LpcLabelGenerationAuto());
        LpcRegister::register('lpcInwardLabelEmailManager', new LpcInwardLabelEmailManager());
        LpcRegister::register('lpcOutwardLabelEmailManager', new LpcOutwardLabelEmailManager());
        LpcRegister::register('lpcRegisterWCEmail', new LpcRegisterWCEmail());

        LpcRegister::register('bordereauGenerationApi', new LpcBordereauGenerationApi());
        LpcRegister::register('bordereauGeneration', new LpcBordereauGeneration());

        LpcRegister::register('labelPurge', new LpcLabelPurge());

        LpcRegister::register('lpcCron', new LpcCron());

        LpcRegister::register('lpcUpdate', new LpcUpdate());

        LpcRegister::register('customsDocumentsApi', new LpcCustomsDocumentsApi());

        LpcRegister::register('labelInwardDownloadAccountAction', new LpcLabelInwardDownloadAccountAction());
    }

}
