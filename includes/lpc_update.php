<?php

class LpcUpdate extends LpcComponent {
    const LPC_DB_VERSION_OPTION_NAME = 'lpc_db_version';

    // const for 1.3 updates
    const LPC_ORDERS_TO_MIGRATE_OPTION_NAME = 'lpc_migration13_orders_to_migrate';
    const LPC_MIGRATION13_HOOK_NAME = 'lpcMigrationHook13';
    const LPC_MIGRATION13_DONE_OPTION_NAME = 'lpc_migration13_done';

    /** @var LpcCapabilitiesPerCountry */
    protected $capabilitiesPerCountry;
    /** @var LpcDbDefinition */
    protected $dbDefinition;
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;
    /** @var LpcAdminNotices */
    protected $adminNotices;
    /** @var LpcShippingZones */
    protected $shippingZones;
    /** @var LpcShippingMethods */
    protected $shippingMethods;
    /** @var LpcBordereauDb */
    protected $bordereauDb;

    public function __construct(
        LpcCapabilitiesPerCountry $capabilitiesPerCountry = null,
        LpcDbDefinition $dbDefinition = null,
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcInwardLabelDb $inwardLabelDb = null,
        LpcAdminNotices $adminNotices = null,
        LpcShippingZones $shippingZones = null,
        LpcShippingMethods $shippingMethods = null,
        LpcBordereauDb $bordereauDb = null
    ) {
        $this->capabilitiesPerCountry = LpcRegister::get('capabilitiesPerCountry', $capabilitiesPerCountry);
        $this->dbDefinition           = LpcRegister::get('dbDefinition', $dbDefinition);
        $this->outwardLabelDb         = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->inwardLabelDb          = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
        $this->adminNotices           = LpcRegister::get('lpcAdminNotices', $adminNotices);
        $this->shippingZones          = LpcRegister::get('shippingZones', $shippingZones);
        $this->shippingMethods        = LpcRegister::get('shippingMethods', $shippingMethods);
        $this->bordereauDb            = LpcRegister::get('bordereauDb', $bordereauDb);
    }

    public function getDependencies(): array {
        return ['capabilitiesPerCountry', 'dbDefinition', 'outwardLabelDb', 'inwardLabelDb', 'lpcAdminNotices', 'shippingZones', 'shippingMethods', 'bordereauDb'];
    }

    public function init() {
        add_action(self::LPC_MIGRATION13_HOOK_NAME, [$this, 'doMigration13']);
        add_action('wp_loaded', [$this, 'update']);
        add_filter('cron_schedules', [$this, 'addCronIntervals']);
    }

    public function addCronIntervals($schedules) {
        $schedules['fifteen_seconds'] = [
            'interval' => 15,
            'display'  => __('Every Fifteen Seconds'),
        ];
        $schedules['fifteen_minutes'] = [
            'interval' => 15 * 60,
            'display'  => __('Every Fifteen Minutes'),
        ];

        return $schedules;
    }

    public function createCapabilities() {
        global $wp_roles;

        if (!class_exists('WP_Roles') || !isset($wp_roles)) {
            return;
        }

        // Only add the capabilities once to avoid erasing the User Role Editor modifications
        // If the admin already has them, it means we already applied the default capabilities
        $adminRole = $wp_roles->get_role('administrator');
        if ($adminRole->has_cap('lpc_manage_settings')) {
            return;
        }

        // By default, add all the capabilities to the admin and the main WooCommerce role
        $roles = ['administrator', 'shop_manager'];

        // If new capabilities are added, add them here and in an update script, update cannot enter this function
        $capabilities = [
            'lpc_manage_settings',
            'lpc_colissimo_listing',
            'lpc_colissimo_bandeau',
            'lpc_manage_documents',
            'lpc_manage_labels',
            'lpc_download_labels',
            'lpc_print_labels',
            'lpc_delete_labels',
            'lpc_send_emails',
            'lpc_manage_bordereau',
            'lpc_download_bordereau',
            'lpc_print_bordereau',
            'lpc_delete_bordereau',
        ];

        foreach ($roles as $role) {
            if (!isset($wp_roles->roles[$role])) {
                continue;
            }

            $roleObject = $wp_roles->get_role($role);

            foreach ($capabilities as $capability) {
                $roleObject->add_cap($capability);
            }
        }
    }

    public function update() {
        $lpcVersionInstalled = LpcHelper::get_option(self::LPC_DB_VERSION_OPTION_NAME, LPC_VERSION);
        if (LPC_VERSION === $lpcVersionInstalled) {
            return;
        }

        if (is_multisite()) {
            $currentBlog = get_current_blog_id();
            $sites       = get_sites();

            foreach ($sites as $site) {
                if (is_object($site)) {
                    $site = get_object_vars($site);
                }
                switch_to_blog($site['blog_id']);
                $lpcVersionInstalled = get_option(self::LPC_DB_VERSION_OPTION_NAME, LPC_VERSION);
                $this->runUpdate($lpcVersionInstalled);
                update_option(self::LPC_DB_VERSION_OPTION_NAME, LPC_VERSION);
            }

            switch_to_blog($currentBlog);
        } else {
            $this->runUpdate($lpcVersionInstalled);
            update_option(self::LPC_DB_VERSION_OPTION_NAME, LPC_VERSION);
        }
    }

    protected function runUpdate($versionInstalled) {
        if (LpcHelper::get_option(self::LPC_MIGRATION13_DONE_OPTION_NAME, false) !== false) {
            $this->adminNotices->add_notice(
                'label_migration',
                'notice-success',
                __('Colissimo Official plugin: the labels migration is done!', 'wc_colissimo')
            );

            delete_option(self::LPC_MIGRATION13_DONE_OPTION_NAME);
        }

        // Update from version under 1.3
        if (version_compare($versionInstalled, '1.3') === - 1) {
            $this->capabilitiesPerCountry->saveCapabilitiesPerCountryInDatabase();
            $this->dbDefinition->defineTableLabel();
            $this->handleMigration13();
        }

        // Update from version under 1.5
        if (version_compare($versionInstalled, '1.5') === - 1) {
            $this->capabilitiesPerCountry->saveCapabilitiesPerCountryInDatabase();
            $this->shippingZones->addCustomZonesOrUpdateOne('Zone France');
        }

        // Update from version under 1.6
        if (version_compare($versionInstalled, '1.6') === - 1) {
            $currentlpc_email_outward_tracking = LpcHelper::get_option(LpcOutwardLabelEmailManager::EMAIL_OUTWARD_TRACKING_OPTION, 'no');

            if ('yes' === $currentlpc_email_outward_tracking) {
                $newlpc_email_outward_tracking = LpcOutwardLabelEmailManager::ON_OUTWARD_LABEL_GENERATION_OPTION;
            } else {
                $newlpc_email_outward_tracking = 'no';
            }

            update_option(LpcOutwardLabelEmailManager::EMAIL_OUTWARD_TRACKING_OPTION, $newlpc_email_outward_tracking, false);
        }

        // Update from version under 1.6.4
        if (version_compare($versionInstalled, '1.6.4') === - 1) {
            $this->outwardLabelDb->updateToVersion164();
        }

        // Update from version under 1.6.5
        if (version_compare($versionInstalled, '1.6.5') === - 1) {
            $this->outwardLabelDb->updateToVersion165();
        }

        // Update from version under 1.6.8
        if (version_compare($versionInstalled, '1.6.8') === - 1) {
            $this->capabilitiesPerCountry->saveCapabilitiesPerCountryInDatabase();
            $this->createCapabilities();
            $this->shippingMethods->moveAlwaysFreeOption();
        }

        // Update from version under 1.7.1
        if (version_compare($versionInstalled, '1.7.1') === - 1) {
            foreach (WC_Shipping_Zones::get_zones() as $zone) {
                if ('France' === $zone['zone_name']) {
                    $newZone = WC_Shipping_Zones::get_zone($zone['id']);
                    $newZone->set_zone_name('Zone France');
                    $newZone->save();
                }
            }

            $this->capabilitiesPerCountry->saveCapabilitiesPerCountryInDatabase();
        }

        // Update from version under 1.7.2
        if (version_compare($versionInstalled, '1.7.2') === - 1) {
            $this->capabilitiesPerCountry->saveCapabilitiesPerCountryInDatabase();
            $this->outwardLabelDb->updateToVersion172();
            $countries  = [
                'SendingService_austria',
                'SendingService_germany',
                'SendingService_italy',
                'SendingService_luxembourg',
            ];
            $expert     = LpcHelper::get_option('lpc_expert_SendingService', 'partner');
            $domicileas = LpcHelper::get_option('lpc_domicileas_SendingService', 'partner');
            foreach ($countries as $country) {
                update_option('lpc_expert_' . $country, $expert, false);
                update_option('lpc_domicileas_' . $country, $domicileas, false);
            }

            $companyName = LpcHelper::get_option('lpc_company_name');
            if (!empty($companyName)) {
                update_option('lpc_origin_company_name', $companyName, false);
            }
        }

        // Update from version under 1.7.4
        if (version_compare($versionInstalled, '1.7.4') === - 1) {
            update_option('lpc_parent_id_webservices', '');
            $this->inwardLabelDb->updateToVersion174();

            $mapType = LpcHelper::get_option('lpc_pickup_map_type');
            if (empty($mapType)) {
                $isWebservice = LpcHelper::get_option('lpc_prUseWebService', 'no');
                update_option('lpc_pickup_map_type', !empty($isWebservice) && 'yes' === $isWebservice ? 'gmaps' : 'widget');
            }
        }

        // Update from version under 1.8.2
        if (version_compare($versionInstalled, '1.8.2') === - 1) {
            $this->outwardLabelDb->updateToVersion182();
            $this->inwardLabelDb->updateToVersion182();
            $this->bordereauDb->updateToVersion182();
        }

        // Update from version under 1.9.2
        if (version_compare($versionInstalled, '1.9.2') === - 1) {
            $passwordAlreadyEncrypted = LpcHelper::get_option('lpc_pwd_encrypted', 0);
            if (empty($passwordAlreadyEncrypted)) {
                update_option(
                    'lpc_pwd_webservices',
                    LpcHelper::encryptPassword(
                        LpcHelper::get_option('lpc_pwd_webservices')
                    )
                );
                update_option('lpc_pwd_encrypted', 1);
            }
            $this->outwardLabelDb->updateToVersion192();
            $this->inwardLabelDb->updateToVersion192();
        }

        // Update from version under 1.9.4
        if (version_compare($versionInstalled, '1.9.4') === - 1) {
            $noShippingClassUpdated = LpcHelper::get_option('lpc_no_shipping_class_updated', 0);
            if (empty($noShippingClassUpdated)) {
                update_option('lpc_no_shipping_class_updated', 1);
                $this->addNoShippingClass();
            }
        }

        // Update from version under 2.0.0
        if (version_compare($versionInstalled, '2.0.0') === - 1) {
            $this->capabilitiesPerCountry->saveCapabilitiesPerCountryInDatabase();
        }

        // Update from version under 2.2.0
        if (version_compare($versionInstalled, '2.2.0') === - 1) {
            $returnByClient = LpcHelper::get_option('lpc_customers_download_return_label', 'no');
            if ('no' !== $returnByClient) {
                update_option('lpc_customers_download_return_label', 'yes', false);
            }
        }
    }

    /** Functions for update to 1.3 **/
    protected function handleMigration13() {
        $this->adminNotices->add_notice(
            'label_migration',
            'notice-success',
            sprintf(
                __(
                    'Thanks for updating Colissimo Official plugin to version %s. This version needs to modify the database structure and it will take a few minutes. While the migration is being done, you can use the plugin as usual but you won\'t be able to see the labels in the Colissimo listing. Please contact the Colissimo support if they are still not visible in a few hours.',
                    'wc_colissimo'
                ),
                LPC_VERSION
            )
        );

        // If we have to retry the migration, we don't erase orders ids to migrate
        if (!LpcHelper::get_option(self::LPC_ORDERS_TO_MIGRATE_OPTION_NAME, false)) {
            $orderIdsToMigrate = $this->outwardLabelDb->getOldTableOrdersToMigrate();
            update_option(self::LPC_ORDERS_TO_MIGRATE_OPTION_NAME, json_encode($orderIdsToMigrate), false);
        }

        if (!wp_next_scheduled(self::LPC_MIGRATION13_HOOK_NAME)) {
            wp_schedule_event(time(), 'fifteen_seconds', self::LPC_MIGRATION13_HOOK_NAME);
        }
    }

    public function doMigration13() {
        $orderIdsToMigrate = json_decode(LpcHelper::get_option(self::LPC_ORDERS_TO_MIGRATE_OPTION_NAME));

        if (0 === count($orderIdsToMigrate)) {
            $timestamp = wp_next_scheduled(self::LPC_MIGRATION13_HOOK_NAME);
            wp_unschedule_event($timestamp, self::LPC_MIGRATION13_HOOK_NAME);
            delete_option(self::LPC_ORDERS_TO_MIGRATE_OPTION_NAME);
            update_option(self::LPC_MIGRATION13_DONE_OPTION_NAME, 1, false);

            return;
        }

        $orderIdsToMigrateForCurrentBatch = array_splice($orderIdsToMigrate, 0, 5);

        if (
            $this->outwardLabelDb->migrateDataFromLabelTableForOrderIds($orderIdsToMigrateForCurrentBatch)
            && $this->inwardLabelDb->migrateDataFromLabelTableForOrderIds($orderIdsToMigrateForCurrentBatch)
        ) {
            update_option(self::LPC_ORDERS_TO_MIGRATE_OPTION_NAME, json_encode($orderIdsToMigrate), false);
        }
    }

    private function addNoShippingClass() {
        foreach (WC_Shipping_Zones::get_zones() as $oneZone) {
            $zone = WC_Shipping_Zones::get_zone($oneZone['id']);

            $existingShippingMethods = array_map(
                function ($v) {
                    return $v->id;
                },
                $zone->get_shipping_methods()
            );

            foreach ($existingShippingMethods as $shippingMethodInstanceId => $shippingMethodNamekey) {
                $methodOptionKey = 'woocommerce_' . $shippingMethodNamekey . '_' . $shippingMethodInstanceId . '_settings';
                $methodSettings  = LpcHelper::get_option($methodOptionKey, 'no');

                if (empty($methodSettings['shipping_rates'])) {
                    continue;
                }

                foreach ($methodSettings['shipping_rates'] as $key => $rate) {
                    if (!in_array(LpcAbstractShipping::LPC_ALL_SHIPPING_CLASS_CODE, $rate['shipping_class'])) {
                        $methodSettings['shipping_rates'][$key]['shipping_class'][] = LpcAbstractShipping::LPC_NO_SHIPPING_CLASS_CODE;
                    }
                }

                update_option($methodOptionKey, $methodSettings);
            }
        }
    }
}
