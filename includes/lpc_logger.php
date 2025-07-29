<?php

/**
 * Class LpcLogger
 */
class LpcLogger {
    /**
     * Number of lines displayed when seeing the logs
     */
    const LOG_LINES_NB = 1000;
    const LOGS_FILE_NAME = 'general.log';
    const MAX_DETAILS_DEPTH = 7;
    const MAX_LOGS_LIFE_IN_DAYS = 14;

    const NONE_LEVEL = 0;
    const ERROR_LEVEL = 1;
    const WARN_LEVEL = 2;
    const INFO_LEVEL = 3;
    const DEBUG_LEVEL = 4;

    public static function getLogsDirPath(): string {
        return wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'colissimo' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
    }

    public static function getLogsPath(): string {
        return self::getLogsDirPath() . self::LOGS_FILE_NAME;
    }

    public static function error($message, array $details = []) {
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

    public static function warn($message, array $details = []) {
        self::log(self::WARN_LEVEL, $message, $details);
    }

    public static function debug($message, array $details = []) {
        self::log(self::DEBUG_LEVEL, $message, $details);
    }

    public static function info($message, array $details = []) {
        self::log(self::INFO_LEVEL, $message, $details);
    }

    /**
     * Method used to add messages to the log file.
     *
     * @param string     $type
     * @param string     $message
     * @param array|null $details
     */
    protected static function log($type, $message, array $details = []) {
        $log = (int) LpcHelper::get_option('lpc_log', 0);

        if (empty($log)) {
            return;
        }

        $content = $message;
        if (!empty($details)) {
            $content .= PHP_EOL . wp_json_encode($details, 0, self::MAX_DETAILS_DEPTH);
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
            case self::INFO_LEVEL:
                $levelType = 'INFO';
                break;
        }

        $logsPath = self::getLogsPath();
        if (file_exists($logsPath)) {
            $logFileContent  = file_get_contents($logsPath);
            $logFileEachLine = explode(PHP_EOL, $logFileContent);

            $logsLifeLimit = strtotime('-' . self::MAX_LOGS_LIFE_IN_DAYS . ' days');
            foreach ($logFileEachLine as $oneLine) {
                if (strpos($oneLine, '<log>') !== 0) {
                    array_shift($logFileEachLine);
                    continue;
                }

                $date = substr($oneLine, 5, 10);
                if (strtotime($date) >= $logsLifeLimit) {
                    break;
                }

                array_shift($logFileEachLine);
            }
        } else {
            $logsDirPath = self::getLogsDirPath();
            if (!is_dir($logsDirPath)) {
                mkdir($logsDirPath, 0755, true);
            }

            $logFileEachLine = [];
        }
        file_put_contents(
            $logsPath,
            implode(PHP_EOL, $logFileEachLine) . "\r\n<log>" . date('Y-m-d H:i:s', current_time('timestamp')) . ' - ' . $levelType . ' : ' . $content
        );
    }

    /**
     * Returns the X last lines of the log file
     *
     * @return bool|string
     */
    public static function get_logs($lines = null, $downloadLink = '') {
        $logsPath = self::getLogsPath();
        if (!file_exists($logsPath)) {
            return __('The logs file is empty', 'wc_colissimo');
        }

        $link = '';
        if (!empty($downloadLink)) {
            $link = '<a id="colissimo_settings_logs_download_link" href="' . esc_url($downloadLink) . '">' . __('Download logs', 'wc_colissimo') . '</a>';
        }

        if (null == $lines) {
            $lines = self::LOG_LINES_NB;
        }

        $f = fopen($logsPath, 'rb');
        fseek($f, - 1, SEEK_END);
        if (fread($f, 1) != PHP_EOL) {
            $lines --;
        }

        $logFile = '';
        while (ftell($f) > 0 && $lines >= 0) {
            // Figure out how far back we should jump
            $seek = min(ftell($f), 4096);
            fseek($f, - $seek, SEEK_CUR);

            // Get the line
            $logFile = ($chunk = fread($f, $seek)) . $logFile;
            fseek($f, - mb_strlen($chunk, '8bit'), SEEK_CUR);

            // Move to previous line
            $lines -= substr_count($chunk, PHP_EOL);
        }

        fclose($f);

        return $link . trim(implode('<br><br>', array_reverse(explode("\r\n<log>", $logFile))));
    }
}
