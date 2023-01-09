<?php

defined('ABSPATH') || die('Restricted Access');

require_once LPC_INCLUDES . 'lpc_modal.php';

/**
 * Class Lpc_Settings_Tab to handle Colissimo tab in Woocommerce settings
 */
class LpcSettingsTab extends LpcComponent {
    const LPC_SETTINGS_TAB_ID = 'lpc';

    /**
     * @var array Options available
     */
    protected $configOptions;

    protected $seeLogModal;

    public function init() {
        // Add configuration tab in Woocommerce
        add_filter('woocommerce_settings_tabs_array', [$this, 'configurationTab'], 70);
        // Add configuration tab content
        add_action('woocommerce_settings_tabs_' . self::LPC_SETTINGS_TAB_ID, [$this, 'settingsPage']);
        // Save settings page
        add_action('woocommerce_update_options_' . self::LPC_SETTINGS_TAB_ID, [$this, 'saveLpcSettings']);
        // Settings tabs
        add_action('woocommerce_sections_' . self::LPC_SETTINGS_TAB_ID, [$this, 'settingsSections']);

        // Define the log modal field
        $this->initSeeLog();

        $this->initMailto();

        $this->initTelsupport();

        $this->initMultiSelectOrderStatus();

        $this->initSelectOrderStatusOnLabelGenerated();

        $this->initSelectOrderStatusOnPackageDelivered();

        $this->initSelectOrderStatusOnBordereauGenerated();

        $this->initSelectOrderStatusPartialExpedition();

        $this->initSelectOrderStatusDelivered();

        $this->initDisplayNumberInputWithWeightUnit();

        $this->initDisplaySelectAddressCountry();

        $this->initCheckStatus();

        $this->initDefaultCountry();

        $this->fixSavePassword();
    }

    protected function fixSavePassword() {
        add_filter('woocommerce_admin_settings_sanitize_option_lpc_pwd_webservices', [$this, 'fixWordPressSanitizePassword'], 10, 3);
    }

    protected function initSeeLog() {
        add_action('woocommerce_admin_field_seelog', [$this, 'displayDebugButton']);
    }

    protected function initMailto() {
        add_action('woocommerce_admin_field_mailto', [$this, 'displayMailtoButton']);
    }

    protected function initTelsupport() {
        add_action('woocommerce_admin_field_telsupport', [$this, 'displayTelsupportButton']);
    }

    protected function initCheckStatus() {
        add_action('woocommerce_admin_field_lpcstatus', [$this, 'displayStatusLink']);
    }

    protected function initMultiSelectOrderStatus() {
        add_action('woocommerce_admin_field_multiselectorderstatus', [$this, 'displayMultiSelectOrderStatus']);
    }

    protected function initSelectOrderStatusOnLabelGenerated() {
        add_action(
            'woocommerce_admin_field_selectorderstatusonlabelgenerated',
            [$this, 'displaySelectOrderStatusOnLabelGenerated']
        );
    }

    protected function initSelectOrderStatusOnPackageDelivered() {
        add_action(
            'woocommerce_admin_field_selectorderstatusonpackagedelivered',
            [$this, 'displaySelectOrderStatusOnPackageDelivered']
        );
    }

    protected function initSelectOrderStatusOnBordereauGenerated() {
        add_action(
            'woocommerce_admin_field_selectorderstatusonbordereaugenerated',
            [$this, 'displaySelectOrderStatusOnBordereauGenerated']
        );
    }

    protected function initSelectOrderStatusPartialExpedition() {
        add_action(
            'woocommerce_admin_field_selectorderstatuspartialexpedition',
            [$this, 'displaySelectOrderStatusPartialExpedition']
        );
    }

    protected function initSelectOrderStatusDelivered() {
        add_action(
            'woocommerce_admin_field_selectorderstatusdelivered',
            [$this, 'displaySelectOrderStatusDelivered']
        );
    }

    protected function initDisplayNumberInputWithWeightUnit() {
        add_action(
            'woocommerce_admin_field_numberinputwithweightunit',
            [$this, 'displayNumberInputWithWeightUnit']
        );
    }

    protected function initDisplaySelectAddressCountry() {
        add_action(
            'woocommerce_admin_field_addressCountry',
            [$this, 'displaySelectAddressCountry']
        );
    }

    protected function initDefaultCountry() {
        add_action(
            'woocommerce_admin_field_defaultcountry',
            [$this, 'defaultCountry']
        );
    }

    public function fixWordPressSanitizePassword($value, $option, $rawValue) {
        return $rawValue;
    }

    /**
     * Define the "seelogs" field type for the main configuration page
     *
     * @param $field object containing parameters defined in the config_options.json
     */
    public function displayDebugButton($field) {
        $modalContent = '<pre>' . LpcLogger::get_logs() . '</pre>';
        $modal        = new LpcModal($modalContent, 'Colissimo logs', 'lpc-debug-log');
        $modal->loadScripts();
        include LPC_FOLDER . 'admin' . DS . 'partials' . DS . 'settings' . DS . 'debug.php';
    }

    public function displayMailtoButton($field) {
        if (empty($field['email'])) {
            $field['email'] = LPC_CONTACT_EMAIL;
        }
        include LPC_FOLDER . 'admin' . DS . 'partials' . DS . 'settings' . DS . 'mailto.php';
    }

    public function displayTelsupportButton($field) {
        include LPC_FOLDER . 'admin' . DS . 'partials' . DS . 'settings' . DS . 'supportButton.php';
    }

    public function displayStatusLink($field) {
        include LPC_FOLDER . 'admin' . DS . 'partials' . DS . 'settings' . DS . 'lpc_status.php';
    }

    public function displayMultiSelectOrderStatus() {
        $args                    = [];
        $args['id_and_name']     = 'lpc_generate_label_on';
        $args['label']           = 'Generate label on';
        $args['values']          = array_merge(['disable' => __('Disable', 'wc_colissimo')], wc_get_order_statuses());
        $args['selected_values'] = get_option($args['id_and_name']);
        $args['multiple']        = true;
        echo LpcHelper::renderPartial('settings' . DS . 'select_field.php', $args);
    }

    public function displaySelectOrderStatusOnLabelGenerated() {
        $args                    = [];
        $args['id_and_name']     = 'lpc_order_status_on_label_generated';
        $args['label']           = 'Order status once label is generated';
        $args['values']          = array_merge(
            ['unchanged_order_status' => __('Keep order status as it is', 'wc_colissimo')],
            wc_get_order_statuses()
        );
        $args['selected_values'] = get_option($args['id_and_name']);
        echo LpcHelper::renderPartial('settings' . DS . 'select_field.php', $args);
    }

    public function displaySelectOrderStatusOnPackageDelivered() {
        $args                    = [];
        $args['id_and_name']     = 'lpc_order_status_on_package_delivered';
        $args['label']           = 'Order status once the package is delivered';
        $args['values']          = wc_get_order_statuses();
        $args['selected_values'] = get_option($args['id_and_name']);
        echo LpcHelper::renderPartial('settings' . DS . 'select_field.php', $args);
    }

    public function displaySelectOrderStatusOnBordereauGenerated() {
        $args                    = [];
        $args['id_and_name']     = 'lpc_order_status_on_bordereau_generated';
        $args['label']           = 'Order status once bordereau is generated';
        $args['values']          = array_merge(
            ['unchanged_order_status' => __('Keep order status as it is', 'wc_colissimo')],
            wc_get_order_statuses()
        );
        $args['selected_values'] = get_option($args['id_and_name']);
        echo LpcHelper::renderPartial('settings' . DS . 'select_field.php', $args);
    }

    public function displaySelectOrderStatusPartialExpedition() {
        $args                    = [];
        $args['id_and_name']     = 'lpc_status_on_partial_expedition';
        $args['label']           = 'Order status when order is partially shipped';
        $args['values']          = array_merge(
            ['unchanged_order_status' => __('Keep order status as it is', 'wc_colissimo')],
            wc_get_order_statuses()
        );
        $args['selected_values'] = get_option($args['id_and_name'], 'wc-lpc_partial_expedition');
        echo LpcHelper::renderPartial('settings' . DS . 'select_field.php', $args);
    }

    public function displaySelectOrderStatusDelivered() {
        $args                    = [];
        $args['id_and_name']     = 'lpc_status_on_delivered';
        $args['label']           = 'Order status when order is delivered';
        $args['values']          = array_merge(
            ['unchanged_order_status' => __('Keep order status as it is', 'wc_colissimo')],
            wc_get_order_statuses()
        );
        $args['selected_values'] = get_option($args['id_and_name'], LpcOrderStatuses::WC_LPC_DELIVERED);
        echo LpcHelper::renderPartial('settings' . DS . 'select_field.php', $args);
    }

    public function displayNumberInputWithWeightUnit() {
        $args                = [];
        $args['id_and_name'] = 'lpc_packaging_weight';
        $args['label']       = 'Packaging weight (%s)';
        $args['value']       = get_option($args['id_and_name']);
        $args['desc']        = 'The packaging weight will be added to the products weight on label generation.';
        echo LpcHelper::renderPartial('settings' . DS . 'number_input_weight.php', $args);
    }

    public function defaultCountry($defaultArgs) {
        $args           = [];
        $countries_obj  = new WC_Countries();
        $args['values'] = $countries_obj->__get('countries');

        $value = LpcHelper::get_option('lpc_default_country_for_product', '');

        $args['id_and_name']     = 'lpc_default_country_for_product';
        $args['label']           = $defaultArgs['title'];
        $args['desc']            = $defaultArgs['desc'];
        $args['selected_values'] = $value;

        echo LpcHelper::renderPartial('settings' . DS . 'select_field.php', $args);
    }

    public function displaySelectAddressCountry($defaultArgs) {
        $args          = [];
        $countries_obj = new WC_Countries();
        $countries     = $countries_obj->__get('countries');

        $countriesCode = array_merge(LpcCapabilitiesPerCountry::DOM1_COUNTRIES_CODE, LpcCapabilitiesPerCountry::FRANCE_COUNTRIES_CODE);

        $args['values'][''] = '---';

        foreach ($countriesCode as $countryCode) {
            $args['values'][$countryCode] = $countries[$countryCode];
        }

        $value = LpcHelper::get_option($defaultArgs['id'], '');
        if (empty($value)) {
            $value = '';
        }

        $args['id_and_name']     = $defaultArgs['id'];
        $args['label']           = $defaultArgs['title'];
        $args['desc']            = $defaultArgs['desc'];
        $args['selected_values'] = $value;

        echo LpcHelper::renderPartial('settings' . DS . 'select_field.php', $args);
    }

    /**
     * Build tab
     *
     * @param $tab
     *
     * @return mixed
     */
    public function configurationTab($tab) {
        if (!current_user_can('lpc_manage_settings')) {
            return $tab;
        }

        $tab[self::LPC_SETTINGS_TAB_ID] = 'Colissimo Officiel';

        return $tab;
    }

    /**
     * Content of the configuration page
     */
    public function settingsPage() {
        if (empty($this->configOptions)) {
            $this->initConfigOptions();
        }

        $section = $this->getCurrentSection();
        if (!in_array($section, array_keys($this->configOptions))) {
            $section = 'main';
        }

        WC_Admin_Settings::output_fields($this->configOptions[$section]);
    }

    /**
     * Tabs of the configuration page
     */
    public function settingsSections() {
        $currentTab = $this->getCurrentSection();

        $sections = [
            'main'     => __('General', 'wc_colissimo'),
            'label'    => __('Label', 'wc_colissimo'),
            'shipping' => __('Shipping methods', 'wc_colissimo'),
            'custom'   => __('Custom', 'wc_colissimo'),
            'ddp'      => __('DDP', 'wc_colissimo'),
            'support'  => __('Support', 'wc_colissimo'),
        ];

        echo '<ul class="subsubsub">';

        $array_keys = array_keys($sections);

        foreach ($sections as $id => $label) {
            $url       = admin_url('admin.php?page=wc-settings&tab=' . self::LPC_SETTINGS_TAB_ID . '&section=' . sanitize_title($id));
            $class     = $currentTab === $id ? 'current' : '';
            $separator = end($array_keys) === $id ? '' : '|';
            echo '<li><a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a> ' . esc_html($separator) . ' </li>';
        }

        echo '</ul><br class="clear" />';
    }

    /**
     * Save using Woocomerce default method
     */
    public function saveLpcSettings() {
        if (empty($this->configOptions)) {
            $this->initConfigOptions();
        }

        try {
            $currentTab = $this->getCurrentSection();
            WC_Admin_Settings::save_fields($this->configOptions[$currentTab]);
        } catch (Exception $exc) {
            LpcLogger::error("Can't save field setting.", $this->configOp);
        }
    }

    /**
     * Initialize configuration options from resource file
     */
    protected function initConfigOptions() {
        $configStructure = file_get_contents(LPC_RESOURCE_FOLDER . LpcHelper::CONFIG_FILE);
        $tempConfig      = json_decode($configStructure, true);

        $currentTab = $this->getCurrentSection();

        foreach ($tempConfig[$currentTab] as &$oneField) {
            if (!empty($oneField['title'])) {
                $oneField['title'] = __($oneField['title'], 'wc_colissimo');
            }

            if (!empty($oneField['desc'])) {
                $oneField['desc'] = __($oneField['desc'], 'wc_colissimo');
            }

            if (!empty($oneField['options'])) {
                foreach ($oneField['options'] as &$oneOption) {
                    $oneOption = __($oneOption, 'wc_colissimo');
                }
            }
        }

        $this->configOptions = $tempConfig;
    }

    protected function getCurrentSection() {
        global $current_section;

        return empty($current_section) ? 'main' : $current_section;
    }
}
