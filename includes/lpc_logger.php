<?php
defined('ABSPATH') || die('Restricted Access');

class LpcLogger {
    /**
     * Number of lines displayed when seeing the logs
     */
    const LOG_BLOCKS_NB = 300;
    const MAX_LOG_BLOCKS = 1000;
    const MAX_DETAILS_DEPTH = 7;
    const MAX_LOGS_LIFE_IN_DAYS = 14;
    const ALL_LINES = - 1;

    const ERROR_LEVEL = 1;
    const WARN_LEVEL = 2;
    const DEBUG_LEVEL = 4;

    public static function error(string $message, array $details = []): void {
        $debug                  = debug_backtrace();
        $details['stack_trace'] = [];
        foreach ($debug as $step) {
            if (empty($step['file']) || empty($step['line'])) {
                continue;
            }

            $details['stack_trace'][] = $step['file'] . ' => ' . $step['line'];
        }

        self::log(self::ERROR_LEVEL, $message, $details);
    }

    public static function warn(string $message, array $details = []): void {
        self::log(self::WARN_LEVEL, $message, $details);
    }

    public static function debug(string $message, array $details = []): void {
        self::log(self::DEBUG_LEVEL, $message, $details);
    }

    /**
     * Method used to save messages to the logs.
     */
    protected static function log(string $type, string $content, array $details = []): void {
        $log = (int) LpcHelper::get_option('lpc_log', 0);
        if (empty($log)) {
            return;
        }

        if (!empty($details)) {
            $content .= '<br />' . wp_json_encode($details, 0, self::MAX_DETAILS_DEPTH);
        }

        $levelType = '';
        switch ($type) {
            case self::ERROR_LEVEL:
                $levelType = 'ERROR';
                break;
            case self::WARN_LEVEL:
                $levelType = 'WARN';
                break;
            case self::DEBUG_LEVEL:
                $levelType = 'DEBUG';
                break;
        }

        $logs = json_decode(LpcHelper::get_option('lpc_logs', '[]'), true);

        // Only keep logs X days old
        $logsLifeLimit = strtotime('-' . self::MAX_LOGS_LIFE_IN_DAYS . ' days');
        foreach ($logs as $oneLog) {
            if ($oneLog['time'] >= $logsLifeLimit) {
                break;
            }

            array_shift($logs);
        }

        // Don't go past a maximum number of logs to not clog database
        if (count($logs) > self::MAX_LOG_BLOCKS) {
            array_shift($logs);
        }

        $time   = current_time('timestamp');
        $logs[] = [
            'time'    => $time,
            'content' => date('Y-m-d H:i:s', $time) . ' - ' . $levelType . ' : ' . $content,
        ];

        update_option('lpc_logs', json_encode($logs), false);
    }

    /**
     * Returns the X last lines of the log file
     */
    public static function get_logs(?string $downloadLink = null, int $lines = self::LOG_BLOCKS_NB): string {
        $logs = json_decode(LpcHelper::get_option('lpc_logs', '[]'), true);
        if (empty($logs)) {
            return __('The logs file is empty', 'wc_colissimo');
        }

        $link = '';
        if (!empty($downloadLink)) {
            $link = '<a id="colissimo_settings_logs_download_link" href="' . esc_url($downloadLink) . '">' . esc_html__('Download logs', 'wc_colissimo') . '</a>';
        }

        $result = '';
        $logs   = array_reverse($logs);
        foreach ($logs as $oneLog) {
            $result .= $oneLog['content'] . '<br /><br />';
            $lines --;

            if (empty($lines)) {
                break;
            }
        }

        return $link . $result;
    }
}
