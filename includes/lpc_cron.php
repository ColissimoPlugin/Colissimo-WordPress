<?php

class LpcCron extends LpcComponent {

    const CRON_START_HOUR = 8;
    const CRON_END_HOUR = 20;

    protected $unifiedTrackingApi;
    protected $lpcLabelPurge;

    public function __construct(LpcUnifiedTrackingApi $lpcUnifiedTrackingApi = null, LpcLabelPurge $lpcLabelPurge = null) {
        $this->unifiedTrackingApi = LpcRegister::get('unifiedTrackingApi', $lpcUnifiedTrackingApi);
        $this->lpcLabelPurge      = LpcRegister::get('labelPurge', $lpcLabelPurge);
    }

    public function getDependencies() {
        return ['unifiedTrackingApi', 'labelPurge'];
    }

    public function init() {
        $this->updateAllStatuses();
        $this->purgeLabels();
    }

    protected function updateAllStatuses() {
        // Define action
        add_action(
            'update_colissimo_statuses',
            function () {
                $now = new WC_DateTime();
                $now->setTimezone(new DateTimeZone(wc_timezone_string()));
                $actualHour = $now->date('G');

                if (null !== $actualHour && $actualHour >= self::CRON_START_HOUR && $actualHour < self::CRON_END_HOUR) {
                    $this->unifiedTrackingApi->updateAllStatuses();
                }
            }
        );

        // Define event
        register_activation_hook(
            LPC_FOLDER . 'index.php',
            function () {
                if (!wp_next_scheduled('update_colissimo_statuses')) {
                    wp_schedule_event(time(), 'hourly', 'update_colissimo_statuses');
                }
            }
        );

        // Deactivation
        register_deactivation_hook(
            LPC_FOLDER . 'index.php',
            function () {
                wp_clear_scheduled_hook('update_colissimo_statuses');
            }
        );
    }

    protected function purgeLabels() {
        // Define action
        add_action(
            'purge_colissimo_labels',
            function () {
                $this->lpcLabelPurge->purgeReadyLabels();
            }
        );

        // Define event
        register_activation_hook(
            LPC_FOLDER . 'index.php',
            function () {
                if (!wp_next_scheduled('purge_colissimo_labels')) {
                    wp_schedule_event(time(), 'daily', 'purge_colissimo_labels');
                }
            }
        );

        // Deactivation
        register_deactivation_hook(
            LPC_FOLDER . 'index.php',
            function () {
                wp_clear_scheduled_hook('purge_colissimo_labels');
            }
        );
    }
}
