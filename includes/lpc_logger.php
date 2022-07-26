<?php

/**
 * Class LpcLogger
 */
class LpcLogger {
    /**
     * Number of lines displayed when seeing the logs
     */
    const LOG_LINES_NB = 1000;
    const LOG_FILE = LPC_FOLDER . 'logs' . DS . 'colissimo.log';
    const MAX_DETAILS_DEPTH = 7;

    const NONE_LEVEL = 0;
    const ERROR_LEVEL = 1;
    const WARN_LEVEL = 2;
    const INFO_LEVEL = 3;
    const DEBUG_LEVEL = 4;

    protected $logFile;


    public static function error($message, array $details = null) {
        self::log(self::ERROR_LEVEL, $message, $details);
    }

    public static function warn($message, array $details = null) {
        self::log(self::WARN_LEVEL, $message, $details);
    }

    public static function warning($message, array $details = null) {
        self::warn($message, $details);
    }

    public static function debug($message, array $details = null) {
        self::log(self::DEBUG_LEVEL, $message, $details);
    }

    public static function info($message, array $details = null) {
        self::log(self::INFO_LEVEL, $message, $details);
    }

    /**
     * Method used to add messages to the log file.
     *
     * @param string     $type
     * @param            $message
     * @param array|null $details
     */
    protected static function log($type, $message, array $details = null) {
        $log = (int) LpcHelper::get_option('lpc_log', 0);

        if (empty($log)) {
            return;
        }

        $content = $message;
        if (null !== $details) {
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

        if (file_exists(self::LOG_FILE)) {
            $logFileContent  = file_get_contents(self::LOG_FILE);
            $logFileEachLine = explode(PHP_EOL, $logFileContent);

            while (10000 < count($logFileEachLine)) {
                array_shift($logFileEachLine);
            }
        } else {
            $logFileEachLine = [];
        }

        file_put_contents(
            self::LOG_FILE,
            implode(PHP_EOL, $logFileEachLine) . "\r\n<log>" . date('Y-m-d H:i:s', current_time('timestamp')) . ' - ' . $levelType . ' : ' . $content
        );
    }

    /**
     * Returns the X last lines of the log file
     *
     * @return bool|string
     */
    public static function get_logs($lines = null) {

        if (!file_exists(self::LOG_FILE)) {
            return __('The log file is empty', 'wc_colissimo');
        }

        if (null == $lines) {
            $lines = self::LOG_LINES_NB;
        }

        $f = fopen(self::LOG_FILE, 'rb');
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

        return trim(implode('<br><br>', array_reverse(explode("\r\n<log>", $logFile))));
    }
}
