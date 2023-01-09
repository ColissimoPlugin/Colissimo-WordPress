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

    public function __construct(
        LpcCapabilitiesPerCountry $capabilitiesPerCountry = null,
        LpcDbDefinition $dbDefinition = null,
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcInwardLabelDb $inwardLabelDb = null,
        LpcAdminNotices $adminNotices = null,
        LpcShippingZones $shippingZones = null,
        LpcShippingMethods $shippingMethods = null
    ) {
        $this->capabilitiesPerCountry = LpcRegister::get('capabilitiesPerCountry', $capabilitiesPerCountry);
        $this->dbDefinition           = LpcRegister::get('dbDefinition', $dbDefinition);
        $this->outwardLabelDb         = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->inwardLabelDb          = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
        $this->adminNotices           = LpcRegister::get('lpcAdminNotices', $adminNotices);
        $this->shippingZones          = LpcRegister::get('shippingZones', $shippingZones);
        $this->shippingMethods        = LpcRegister::get('shippingMethods', $shippingMethods);
    }

    public function getDependencies() {
        return ['capabilitiesPerCountry', 'dbDefinition', 'outwardLabelDb', 'inwardLabelDb', 'lpcAdminNotices'];
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
            'interval' => 15,
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
        if (is_multisite()) {
            global $wpdb;

            foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
                switch_to_blog($blog_id);
                $lpcVersionInstalled = get_option(self::LPC_DB_VERSION_OPTION_NAME, LPC_VERSION);
                $this->runUpdate($lpcVersionInstalled);
                update_option(self::LPC_DB_VERSION_OPTION_NAME, LPC_VERSION);
                restore_current_blog();
            }
        } else {
            $lpcVersionInstalled = LpcHelper::get_option(self::LPC_DB_VERSION_OPTION_NAME, LPC_VERSION);
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

            update_option(LpcOutwardLabelEmailManager::EMAIL_OUTWARD_TRACKING_OPTION, $newlpc_email_outward_tracking);
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
                update_option('lpc_expert_' . $country, $expert);
                update_option('lpc_domicileas_' . $country, $domicileas);
            }

            $companyName = LpcHelper::get_option('lpc_company_name');
            if (!empty($companyName)) {
                update_option('lpc_origin_company_name', $companyName);
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
            update_option(self::LPC_ORDERS_TO_MIGRATE_OPTION_NAME, json_encode($orderIdsToMigrate));
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
            update_option(self::LPC_MIGRATION13_DONE_OPTION_NAME, 1);

            return;
        }

        $orderIdsToMigrateForCurrentBatch = array_splice($orderIdsToMigrate, 0, 5);

        if (
            $this->outwardLabelDb->migrateDataFromLabelTableForOrderIds($orderIdsToMigrateForCurrentBatch)
            && $this->inwardLabelDb->migrateDataFromLabelTableForOrderIds($orderIdsToMigrateForCurrentBatch)
        ) {
            update_option(self::LPC_ORDERS_TO_MIGRATE_OPTION_NAME, json_encode($orderIdsToMigrate));
        }
    }
}
