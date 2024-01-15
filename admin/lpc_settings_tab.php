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

    /** @var LpcAdminNotices */
    protected $adminNotices;
    /** @var LpcPickUpWidgetApi */
    private $pickUpWidgetApi;
    /** @var LpcAccountApi */
    private $accountApi;
    /** @var LpcSettingsLogsDownload */
    private $settingsLogsDownload;

    public function __construct(
        LpcAdminNotices $adminNotices = null,
        LpcPickUpWidgetApi $pickUpWidgetApi = null,
        LpcAccountApi $accountApi = null,
        LpcSettingsLogsDownload $settingsLogsDownload = null
    ) {
        $this->adminNotices         = LpcRegister::get('lpcAdminNotices', $adminNotices);
        $this->pickUpWidgetApi      = LpcRegister::get('pickupWidgetApi', $pickUpWidgetApi);
        $this->accountApi           = LpcRegister::get('accountApi', $accountApi);
        $this->settingsLogsDownload = LpcRegister::get('settingsLogsDownload', $settingsLogsDownload);
    }

    public function getDependencies() {
        return ['lpcAdminNotices', 'pickupWidgetApi', 'accountApi', 'settingsLogsDownload'];
    }

    public function init() {
        // Add configuration tab in Woocommerce
        add_filter('woocommerce_settings_tabs_array', [$this, 'configurationTab'], 70);
        // Add configuration tab content
        add_action('woocommerce_settings_tabs_' . self::LPC_SETTINGS_TAB_ID, [$this, 'settingsPage']);
        // Save settings page
        add_action('woocommerce_update_options_' . self::LPC_SETTINGS_TAB_ID, [$this, 'saveLpcSettings']);
        // Settings tabs
        add_action('woocommerce_sections_' . self::LPC_SETTINGS_TAB_ID, [$this, 'settingsSections']);
        // Invalid weight warning
        add_action('load-woocommerce_page_wc-settings', [$this, 'warningPackagingWeight']);
        // Invalid credentials warning
        add_action('load-woocommerce_page_wc-settings', [$this, 'warningCredentials']);
        // DIVI breaking the pickup map in widget mode
        add_action('load-woocommerce_page_wc-settings', [$this, 'warningDivi']);
        // CGV not accepted warning
        add_action('load-woocommerce_page_wc-settings', [$this, 'warningCgv']);

        $this->initOnboarding();
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
        $this->initMultiSelectRelayType();
        $this->fixSavePassword();
        $this->initVideoTutorials();
    }

    protected function initVideoTutorials() {
        add_action('woocommerce_admin_field_videotutorials', [$this, 'displayVideoTutorials']);
    }

    protected function fixSavePassword() {
        add_filter('woocommerce_admin_settings_sanitize_option_lpc_pwd_webservices', [$this, 'encryptPassword'], 10, 3);
    }

    protected function initOnboarding() {
        add_action('woocommerce_admin_field_onboarding', [$this, 'displayOnboarding']);
    }

    protected function initSeeLog() {
        add_action('woocommerce_admin_field_lpcmodal', [$this, 'displayModalButton']);
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

    public function encryptPassword($value, $option, $rawValue) {
        // password is already encrypt if not changed, so we don't touch it
        if (LpcHelper::get_option('lpc_pwd_webservices') === $rawValue) {
            return $rawValue;
        }

        return LpcHelper::encryptPassword($rawValue);
    }

    public function displayOnboarding($field) {
        wp_register_style('lpc_onboarding', plugins_url('/css/settings/lpc_settings_home.css', __FILE__), [], LPC_VERSION);
        wp_enqueue_style('lpc_onboarding');
        include LPC_FOLDER . 'admin' . DS . 'partials' . DS . 'settings' . DS . 'onboarding.php';
    }

    /**
     * Define the "lpcmodal" field type for the main configuration page
     *
     * @param $field object containing parameters defined in the config_options.json
     */
    public function displayModalButton($field) {
        wp_register_style('lpc_settings_support', plugins_url('/css/settings/lpc_settings_support.css', __FILE__), [], LPC_VERSION);
        wp_enqueue_style('lpc_settings_support');
        if ('hooks' === $field['content']) {
            $modalContent = file_get_contents(LPC_FOLDER . 'resources' . DS . 'hooksDescriptions.php');
        } else {
            $modalContent = '<pre>' . LpcLogger::get_logs(null, $this->settingsLogsDownload->getUrl()) . '</pre>';
        }
        $modal = new LpcModal($modalContent, __($field['title'], 'wc_colissimo'), 'lpc-' . $field['content']);
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
        $args['desc']        = __('The packaging weight will be added to the products weight on label generation.', 'wc_colissimo');
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

        $countryCodes = array_merge(LpcCapabilitiesPerCountry::DOM1_COUNTRIES_CODE, LpcCapabilitiesPerCountry::FRANCE_COUNTRIES_CODE);

        $args['values'][''] = '---';

        foreach ($countries as $countryCode => $countryName) {
            if (in_array($countryCode, $countryCodes)) {
                $args['values'][$countryCode] = $countryName;
            }
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

    public function displayVideoTutorials() {
        $args                = [];
        $args['id_and_name'] = 'lpc_video_tutorials';
        $args['label']       = 'Video tutorials';
        echo LpcHelper::renderPartial('settings' . DS . 'video_tutorials.php', $args);
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
            $section = 'home';
        }

        WC_Admin_Settings::output_fields($this->configOptions[$section]);
    }

    /**
     * Tabs of the configuration page
     */
    public function settingsSections() {
        $currentTab = $this->getCurrentSection();

        $sections = [
            'home'     => __('Home', 'wc_colissimo'),
            'main'     => __('General', 'wc_colissimo'),
            'label'    => __('Label', 'wc_colissimo'),
            'shipping' => __('Shipping methods', 'wc_colissimo'),
            'custom'   => __('Custom', 'wc_colissimo'),
            'ddp'      => __('DDP', 'wc_colissimo'),
            'support'  => __('Support', 'wc_colissimo'),
            'video'    => __('Video tutorials', 'wc_colissimo'),
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
            $currentSection = $this->getCurrentSection();
            $this->checkColissimoCredentials($currentSection);
            WC_Admin_Settings::save_fields($this->configOptions[$currentSection]);
            // Handle relay types reset
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            if (empty($_REQUEST['_wpnonce']) || !wp_verify_nonce(wp_unslash($_REQUEST['_wpnonce']), 'woocommerce-settings')) {
                die('Invalid Token');
            }
            if ('shipping' == $currentSection && !isset($_POST['lpc_relay_point_type'])) {
                $relayTypeOption = [
                    'id'   => 'lpc_relay_point_type',
                    'type' => 'multiselectrelaytype',
                ];
                WC_Admin_Settings::save_fields([$relayTypeOption], ['lpc_relay_point_type' => ['A2P', 'BPR', 'CMT', 'PCS', 'BDP']]);
            }
        } catch (Exception $exc) {
            LpcLogger::error(
                'Can\'t save field setting.',
                [
                    'error'   => $exc->getMessage(),
                    'options' => $this->configOptions,
                ]
            );
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

        return empty($current_section) ? 'home' : $current_section;
    }

    public function warningPackagingWeight() {
        $currentTab = LpcHelper::getVar('tab');

        if ('lpc' !== $currentTab) {
            return;
        }

        $packagingWeight = wc_get_weight(LpcHelper::get_option('lpc_packaging_weight', '0'), 'kg');

        if ($packagingWeight > 1) {
            WC_Admin_Settings::add_error(
                __(
                    'The packaging weight you configured is high, the shipping methods may not show up on your store if the packaging weight + the cart weight are greater than 30kg.',
                    'wc_colissimo'
                )
            );
        }
    }

    private function checkColissimoCredentials(string $currentSection) {
        if ('main' !== $currentSection) {
            return;
        }

        $oldLogin    = LpcHelper::get_option('lpc_id_webservices');
        $newLogin    = LpcHelper::getVar('lpc_id_webservices');
        $oldPassword = LpcHelper::get_option('lpc_pwd_webservices');
        $newPassword = LpcHelper::getVar('lpc_pwd_webservices');

        if ($oldLogin === $newLogin && $oldPassword === $newPassword) {
            return;
        }

        // Reset accepted CGV status when credentials change
        update_option('lpc_accepted_cgv', false);

        $token = $this->pickUpWidgetApi->authenticate($newLogin, $newPassword);

        if (!empty($token)) {
            WC_Admin_Settings::add_message(__('Valid Colissimo credentials', 'wc_colissimo'));
        }

        $this->logCredentialsValidity($token);
    }

    public function warningCredentials() {
        $currentTab = LpcHelper::getVar('tab');

        if ('lpc' !== $currentTab) {
            return;
        }

        $testedCredentials = LpcHelper::get_option('lpc_current_credentials_tested');

        if (!$testedCredentials) {
            $login    = LpcHelper::get_option('lpc_id_webservices');
            $password = LpcHelper::getPasswordWebService();

            if (empty($login) || empty($password)) {
                $this->adminNotices->add_notice(
                    'credentials_validity',
                    'notice-info',
                    __('Please enter your Colissimo credentials to be able to generate labels and show the pickup map.', 'wc_colissimo')
                );

                return;
            }

            $token = $this->pickUpWidgetApi->authenticate($login, $password);
            $this->logCredentialsValidity($token);
        }

        $validCredentials = LpcHelper::get_option('lpc_current_credentials_valid');

        if (!$validCredentials) {
            WC_Admin_Settings::add_error(
                __(
                    'Your ID must be a 6 digits number and your credentials must correspond to an account on https://www.colissimo.entreprise.laposte.fr with a valid Facilité or Privilège contract.',
                    'wc_colissimo'
                ) . "\n" .
                __('Your Colissimo credentials are incorrect, you won\'t be able to generate labels or show the pickup map to your customers.', 'wc_colissimo')
            );
        }
    }

    public function warningDivi() {
        $currentTab = LpcHelper::getVar('tab');

        if ('lpc' !== $currentTab) {
            return;
        }

        $mapType = LpcHelper::get_option('lpc_pickup_map_type', 'widget');
        if ('widget' !== $mapType) {
            return;
        }

        $theme = wp_get_theme();
        if ('Divi' !== $theme->name || !function_exists('et_get_option')) {
            return;
        }

        global $shortname;
        $option = et_get_option($shortname . '_enable_jquery_body', 'on');
        if ('on' !== $option) {
            return;
        }

        WC_Admin_Settings::add_error(
            __(
                'The DIVI option General => Performance => Defer jQuery And jQuery Migrate is activated. Please disable it to prevent DIVI from breaking the Colissimo widget, or change the pickup map type.',
                'wc_colissimo'
            )
        );
    }

    public function warningCgv() {
        $currentTab = LpcHelper::getVar('tab');

        if ('lpc' !== $currentTab) {
            return;
        }

        if (!$this->accountApi->isCgvAccepted()) {
            $this->adminNotices->add_notice(
                'cgv_invalid',
                'notice-error',
                '<span style="color:red;font-weight: bold;">' .
                __(
                    'We have detected that you have not yet signed the latest version of our GTC. Your consent is necessary in order to continue using Colissimo services. We therefore invite you to sign them on your Colissimo entreprise space, by clicking on the link below:',
                    'wc_colissimo'
                ) . '<br/><a href="https://www.colissimo.entreprise.laposte.fr" target="_blank">' . __('Sign the GTC', 'wc_colissimo') . '</a>'
                . '</span>'
            );
        }
    }

    private function logCredentialsValidity($token) {
        update_option('lpc_current_credentials_tested', true);
        update_option('lpc_current_credentials_valid', !empty($token));
    }

    protected function initMultiSelectRelayType() {
        add_action('woocommerce_admin_field_multiselectrelaytype', [$this, 'displayMultiSelectRelayType']);
    }

    public function displayMultiSelectRelayType() {
        $relayTypesValues = [
            'fr'    => [
                'label'  => 'France',
                'values' => [
                    'A2P' => __('Pickup station', 'wc_colissimo'),
                    'BPR' => __('Post office', 'wc_colissimo'),
                ],
            ],
            'inter' => [
                'label'  => __('International', 'wc_colissimo'),
                'values' => [
                    'CMT' => __('Relay point', 'wc_colissimo'),
                    'PCS' => __('Pickup station', 'wc_colissimo'),
                    'BDP' => __('Post office', 'wc_colissimo'),
                ],
            ],
        ];

        $args                    = [];
        $args['id_and_name']     = 'lpc_relay_point_type';
        $args['label']           = 'Type of displayed relays';
        $args['tips']            = 'Only applicable for map type other than Colissimo widget';
        $args['values']          = $relayTypesValues;
        $args['selected_values'] = get_option($args['id_and_name']);
        $args['multiple']        = true;
        $args['optgroup']        = true;
        echo LpcHelper::renderPartial('settings' . DS . 'select_field.php', $args);
    }
}
