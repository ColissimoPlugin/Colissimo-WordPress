<?php

defined('ABSPATH') || die('Restricted Access');

require_once LPC_ADMIN . 'lpc_settings_tab.php';
require_once LPC_ADMIN . 'pickup' . DS . 'lpc_pickup_relay_point_on_order.php';
require_once LPC_ADMIN . 'pickup' . DS . 'lpc_admin_pickup_web_service.php';
require_once LPC_ADMIN . 'pickup' . DS . 'lpc_admin_pickup_widget.php';
require_once LPC_ADMIN . 'labels' . DS . 'download' . DS . 'lpc_label_packager_download_action.php';
require_once LPC_ADMIN . 'labels' . DS . 'download' . DS . 'lpc_label_inward_download_action.php';
require_once LPC_ADMIN . 'labels' . DS . 'download' . DS . 'lpc_label_outward_download_action.php';
require_once LPC_ADMIN . 'labels' . DS . 'print' . DS . 'lpc_label_print_action.php';
require_once LPC_ADMIN . 'labels' . DS . 'print' . DS . 'lpc_thermal_label_print_action.php';
require_once LPC_ADMIN . 'labels' . DS . 'deletion' . DS . 'lpc_label_outward_delete_action.php';
require_once LPC_ADMIN . 'labels' . DS . 'deletion' . DS . 'lpc_label_inward_delete_action.php';
require_once LPC_ADMIN . 'labels' . DS . 'import' . DS . 'lpc_label_outward_import_action.php';
require_once LPC_ADMIN . 'labels' . DS . 'lpc_label_queries.php';
require_once LPC_ADMIN . 'orders' . DS . 'lpc_orders_table.php';
require_once LPC_ADMIN . 'orders' . DS . 'lpc_admin_order_affect.php';
require_once LPC_ADMIN . 'orders' . DS . 'lpc_admin_order_banner.php';
require_once LPC_ADMIN . 'bordereau' . DS . 'lpc_bordereau_download_action.php';
require_once LPC_ADMIN . 'bordereau' . DS . 'lpc_bordereau_queries.php';
require_once LPC_ADMIN . 'bordereau' . DS . 'lpc_bordereau_delete_action.php';
require_once LPC_ADMIN . 'bordereau' . DS . 'lpc_bordereau_print_action.php';
require_once LPC_ADMIN . 'coupons' . DS . 'lpc_coupons_restrictions.php';
require_once LPC_ADMIN . 'labels' . DS . 'generate' . DS . 'lpc_label_inward_generate_action.php';
require_once LPC_ADMIN . 'labels' . DS . 'generate' . DS . 'lpc_label_outward_generate_action.php';
require_once LPC_ADMIN . 'lpc_compatibility.php';
require_once LPC_ADMIN . 'orders' . DS . 'lpc_woo_orders_table_action.php';
require_once LPC_ADMIN . 'orders' . DS . 'lpc_woo_orders_table_bulk_actions.php';
require_once LPC_ADMIN . 'shipping' . DS . 'lpc_shipping_rates.php';
if (file_exists(LPC_FOLDER . 'dev-tools' . DS . 'capabilities' . DS . 'lpc_capabilities_file.php')) {
    require_once LPC_FOLDER . 'dev-tools' . DS . 'capabilities' . DS . 'lpc_capabilities_file.php';
}

class LpcAdminInit {

    public function __construct() {
        // Add left menu
        add_action('admin_menu', [$this, 'add_menus'], 99);
        add_action('admin_menu', [$this, 'add_dev_tool'], 99);
        LpcRegister::register('settingsTab', new LpcSettingsTab());
        LpcRegister::register('pickupRelayPointOnOrder', new LpcPickupRelayPointOnOrder());

        if ('widget' === LpcHelper::get_option('lpc_pickup_map_type', 'widget')) {
            LpcRegister::register('adminPickupWidget', new LpcAdminPickupWidget());
        } else {
            LpcRegister::register('adminPickupWebService', new LpcAdminPickupWebService());
        }

        LpcRegister::register('labelPackagerDownloadAction', new LpcLabelPackagerDownloadAction());
        LpcRegister::register('labelInwardDownloadAction', new LpcLabelInwardDownloadAction());
        LpcRegister::register('labelOutwardDownloadAction', new LpcLabelOutwardDownloadAction());
        LpcRegister::register('labelPrintAction', new LpcLabelPrintAction());
        LpcRegister::register('thermalLabelPrintAction', new LpcThermalLabelPrintAction());
        LpcRegister::register('bordereauDownloadAction', new LpcBordereauDownloadAction());
        LpcRegister::register('bordereauDeleteAction', new LpcBordereauDeleteAction());
        LpcRegister::register('bordereauPrintAction', new LpcBordereauPrintAction());
        LpcRegister::register('bordereauQueries', new LpcBordereauQueries());
        LpcRegister::register('labelOutwardDeleteAction', new LpcLabelOutwardDeleteAction());
        LpcRegister::register('labelInwardDeleteAction', new LpcLabelInwardDeleteAction());
        LpcRegister::register('lpcAdminOrderAffect', new LpcAdminOrderAffect());
        LpcRegister::register('LpcLabelOutwardGenerateAction', new LpcLabelOutwardGenerateAction());
        LpcRegister::register('LpcLabelInwardGenerateAction', new LpcLabelInwardGenerateAction());
        LpcRegister::register('labelQueries', new LpcLabelQueries());
        LpcRegister::register('lpcAdminOrderBanner', new LpcAdminOrderBanner());
        LpcRegister::register('labelOutwardImport', new LpcLabelOutwardImportAction());
        LpcRegister::register('LpcCouponsRestrictions', new LpcCouponsRestrictions());
        LpcRegister::register('wooOrdersTableAction', new LpcWooOrdersTableAction());
        LpcRegister::register('wooOrdersTableBulkActions', new LpcWooOrdersTableBulkActions());
        LpcRegister::register('shippingRates', new LpcShippingRates());

        if (file_exists(LPC_FOLDER . 'dev-tools' . DS . 'capabilities' . DS . 'lpc_capabilities_file.php')) {
            LpcRegister::register('capabilitiesDev', new LpcCapabilitiesFile());
        }

        LpcHelper::enqueueScript('lpc_admin_notices', plugins_url('/js/lpc_admin_notices.js', __FILE__), null, ['jquery-core']);

        add_action('admin_notices', [$this, 'lpc_notifications']);
        add_filter('set-screen-option', [$this, 'lpc_set_option'], 10, 3);
        add_action('woocommerce_settings_page_init', [$this, 'lpc_load_settings_script']);
        add_action('add_meta_boxes_shop_order', [$this, 'lpc_add_meta_boxes']);
    }

    /**
     * Add Colissimo sub-menu to WC in the WP left menu
     */
    public function add_menus() {
        $hook = add_submenu_page(
            'woocommerce',
            'Colissimo',
            'Colissimo',
            'lpc_colissimo_listing',
            'wc_colissimo_view',
            [$this, 'router']
        );

        add_action("load-$hook", [$this, 'lpc_load_orders_table']);
    }

    public function add_dev_tool() {
        if (!file_exists(LPC_FOLDER . 'dev-tools' . DS . 'capabilities' . DS . 'lpc_capabilities_file.php')) {
            return;
        }
        $capabilitiesDev = new LpcCapabilitiesFile();

        $hook = add_submenu_page(
            'woocommerce',
            'Colissimo',
            'Devtool',
            'lpc_colissimo_listing',
            'wc_colissimo_devtool',
            [$capabilitiesDev, 'display']
        );
    }

    public function router() {
        $lpcOrdersTable = new LpcOrdersTable();
        $args           = [];
        $args['table']  = $lpcOrdersTable;
        $args['get']    = $_GET;

        echo LpcHelper::renderPartial('orders' . DS . 'lpc_orders_list_table.php', $args);
    }

    public function lpc_notifications() {
        $lpc_admin_notices = LpcRegister::get('lpcAdminNotices');
        $notifications     = [
            'inward_label_sent',
            'outward_label_generate',
            'inward_label_generate',
            'cdi_warning',
            'outward_label_delete',
            'inward_label_delete',
            'label_migration',
            'jquery_warning',
            'jquery_migrate_wp56',
            'lpc_notice',
            'bordereau_delete',
            'insurance_unavailable_for_country',
            'shipment_change',
            'country_capaibilities_import',
            'shipping_statuses_updated',
        ];
        foreach ($notifications as $oneNotification) {
            $notice_content = $lpc_admin_notices->get_notice($oneNotification);
            if ($notice_content) {
                echo $notice_content;
            }
        }
    }

    public function lpc_load_orders_table() {
        // Add JS
        LpcHelper::enqueueScript(
            'lpc_orders_table',
            plugins_url('/js/orders/lpc_orders_table.js', LPC_ADMIN . 'init.php'),
            null,
            ['jquery-core']
        );

        LpcLabelQueries::enqueueLabelsActionsScript();

        // Add CSS
        LpcHelper::enqueueStyle(
            'lpc_orders_table',
            plugins_url('/css/orders/lpc_orders_table.css', LPC_ADMIN . 'init.php'),
            null
        );

        // Add screen options
        $option = 'per_page';

        $args = [
            'label'   => __('Orders per page', 'wc_colissimo'),
            'default' => 25,
            'option'  => 'lpc_orders_per_page',
        ];

        add_screen_option($option, $args);
    }

    public function lpc_set_option($status, $option, $value) {
        if ('lpc_orders_per_page' == $option) {
            return $value;
        }

        return $status;
    }

    public function lpc_load_settings_script() {
        if ('shipping' !== LpcHelper::getVar('tab')) {
            return;
        }

        $instanceId = LpcHelper::getVar('instance_id');
        if (empty($instanceId)) {
            return;
        }

        LpcHelper::enqueueStyle('lpc_styles', plugins_url('/css/shipping/lpc_shipping_rates.css', __FILE__));
        LpcHelper::enqueueScript(
            'lpc_shipping_rates',
            plugins_url('/' . LPC_COMPONENT . '/admin/js/shipping/lpc_shipping_rates.js'),
            null,
            ['jquery-core'],
            'lpcShippingRates',
            [
                'pleaseSelectFile'    => __('Please select a file', 'wc_colissimo'),
                'errorWhileImporting' => __('Error while saving imported rates', 'wc_colissimo'),
            ]
        );
    }

    public function lpc_add_meta_boxes($post) {
        if (!current_user_can('lpc_colissimo_bandeau')) {
            return;
        }

        // Colissimo Banner
        $adminOrderBanner = LpcRegister::get('lpcAdminOrderBanner');
        add_meta_box(
            'lpc_banner-box',
            '<img src="' . plugins_url('/images/colissimo_cropped.png', LPC_INCLUDES . 'init.php') . '" height="25">',
            [$adminOrderBanner, 'bannerContent'],
            'shop_order',
            'normal',
            'default',
            ['post' => $post]
        );
    }
}
