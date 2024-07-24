<?php

class LpcHelper {
    const CONFIG_FILE = 'config_options.json';
    const ENCRYPTION_KEY = 'colissimo-key-encryption';
    const ENCRYPTION_METHOD = 'AES-128-CTR';
    const ENCRYPTION_OPTION = 0;
    const ENCRYPTION_IV = '1234567891011121';

    protected static $configOptions;

    public static function renderPartialInLayout($name, $args = []) {
        $content = self::renderPartial($name, $args);

        return self::renderPartial('layout.php', ['content' => $content]);
    }

    public static function renderPartial($name, $args = []) {
        $prefix = is_admin() ? 'admin' : 'public';
        $prefix = LPC_FOLDER . $prefix . DS . 'partials' . DS;

        $file = $prefix . $name;
        if (!file_exists($file)) {
            $prefix = LPC_INCLUDES . 'partials' . DS;
            $file   = $prefix . $name;
        }

        if (!file_exists($file) && defined('DOING_AJAX') && DOING_AJAX) {
            $prefix = LPC_PUBLIC . 'partials' . DS;
            $file   = $prefix . $name;
        }

        if (!file_exists($file)) {
            LpcLogger::warn('No such partial!', ['partial' => $name]);

            return null;
        }

        ob_start();
        include $file;

        return ob_get_clean();
    }

    public static function enqueueScript($handle, $srcAdmin = null, $srcFront = null, $dep = [], $localizeObject = '', $localizeVars = []) {
        if (null !== $srcAdmin) {
            self::enqueueScripts('admin_enqueue_scripts', $handle, $srcAdmin, $dep, $localizeObject, $localizeVars);
        }
        if (null !== $srcFront) {
            self::enqueueScripts('wp_enqueue_scripts', $handle, $srcFront, $dep, $localizeObject, $localizeVars);
        }
    }

    private static function enqueueScripts($hook, $handle, $src, $dep, $localizeObject, $localizeVars) {
        add_action(
            $hook,
            function () use ($handle, $src, $dep, $localizeObject, $localizeVars) {
                wp_register_script($handle, $src, $dep, LPC_VERSION, true);
                if (!empty($localizeObject)) {
                    wp_localize_script($handle, $localizeObject, $localizeVars);
                }
                wp_enqueue_script($handle);
            }
        );
    }

    public static function enqueueStyle($handle, $srcAdmin = null, $srcFront = null, $dep = []) {
        if (null !== $srcAdmin) {
            add_action(
                'admin_enqueue_scripts',
                function () use ($handle, $srcAdmin, $dep) {
                    wp_enqueue_style($handle, $srcAdmin, $dep, LPC_VERSION);
                }
            );
        }
        if (null !== $srcFront) {
            add_action(
                'wp_enqueue_scripts',
                function () use ($handle, $srcFront, $dep) {
                    wp_enqueue_style($handle, $srcFront, $dep, LPC_VERSION);
                }
            );
        }
    }

    public static function displayNotice($type, $message) {
        self::renderPartial(
            'notice.php',
            [
                'message' => $message,
                'type'    => $type,
            ]
        );
    }

    public static function displayNoticeException(Exception $e) {
        self::displayNotice('error', $e->getMessage());
    }

    public static function getVar(string $var, $default = '', string $type = 'string', string $source = 'REQUEST') {
        $source = strtoupper($source);

        switch ($source) {
            case 'GET':
                $input = &$_GET;
                break;
            case 'POST':
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $input = &$_POST;
                break;
            case 'FILES':
                $input = &$_FILES;
                break;
            case 'COOKIE':
                $input = &$_COOKIE;
                break;
            case 'ENV':
                $input = &$_ENV;
                break;
            case 'SERVER':
                $input = &$_SERVER;
                break;
            default:
                $input = &$_REQUEST;
                break;
        }

        if (!isset($input[$var])) {
            return $default;
        }

        $result = $input[$var];
        unset($input);

        if ('array' === $type) {
            $result = (array) $result;
        }

        if (in_array($source, ['POST', 'REQUEST', 'GET', 'COOKIE'])) {
            $result = self::stripSlashes($result);
        }

        return self::cleanVar($result, $type);
    }

    public static function stripSlashes($element) {
        if (is_array($element)) {
            foreach ($element as &$oneCell) {
                $oneCell = self::stripSlashes($oneCell);
            }
        } elseif (is_string($element)) {
            $element = stripslashes($element);
        }

        return $element;
    }

    public static function cleanVar($var, $type) {
        if (is_array($var)) {
            foreach ($var as $i => $val) {
                $var[$i] = self::cleanVar($val, $type);
            }

            return $var;
        }

        switch ($type) {
            case 'string':
                $var = strval($var);
                break;
            case 'int':
                $var = intval($var);
                break;
            case 'float':
                $var = floatval($var);
                break;
            case 'bool':
            case 'boolean':
                $var = boolval($var);
                break;
            case 'word':
                $var = preg_replace('#[^a-zA-Z_]#', '', $var);
                break;
            case 'cmd':
                $var = preg_replace('#[^a-zA-Z0-9_\.-]#', '', $var);
                $var = ltrim($var, '.');
                break;
            default:
                break;
        }

        if (!is_string($var)) {
            return $var;
        }

        $var = trim($var);

        if (!preg_match('//u', $var)) {
            // String contains invalid byte sequence, remove it
            $var = htmlspecialchars_decode(htmlspecialchars($var, ENT_IGNORE, 'UTF-8'));
        }

        return preg_replace('#<[a-zA-Z/]+[^>]*>#Ui', '', $var);
    }

    public static function get_option($option, $default = '') {
        // Return the saved option if available
        $value = get_option($option);
        if ($value) {
            return $value;
        }

        // Return the default value if provided
        if ('' !== $default) {
            return $default;
        }

        // Load the default values
        if (null === self::$configOptions) {
            $configStructure = file_get_contents(LPC_RESOURCE_FOLDER . self::CONFIG_FILE);
            self::$configOptions = new ArrayObject(json_decode($configStructure, true));
        }

        // Return the default value if available
        foreach (self::$configOptions as $configTab) {
            foreach ($configTab as $configOption) {
                if (array_key_exists('id', $configOption) && $configOption['id'] === $option) {
                    if (array_key_exists('default', $configOption)) {
                        return $configOption['default'];
                    } else {
                        return '';
                    }
                }
            }
        }

        return '';
    }

    public static function dump($arg, $ajax = false, $indent = null) {
        ob_start();
        var_dump($arg);
        $result = ob_get_clean();

        if ($ajax) {
            file_put_contents(LPC_FOLDER . 'lpcdebug.txt', $result, FILE_APPEND);
        } else {
            if (null === $indent) {
                $indent = defined('WP_ADMIN') && WP_ADMIN;
            }
            $style = $indent ? 'margin-left: 220px;' : '';
            echo '<pre style="' . $style . '">' . htmlentities($result) . '</pre>';
        }
    }

    public static function getWooCommerceDir() {
        if (file_exists(WPMU_PLUGIN_DIR . DS . 'woocommerce' . DS . 'woocommerce.php')) {
            return WPMU_PLUGIN_DIR . '/woocommerce';
        }

        return WP_PLUGIN_DIR . '/woocommerce';
    }

    public static function endAjax($success = true, $data = []) {
        echo json_encode(
            [
                'type' => $success ? 'success' : 'error',
                'data' => $data,
            ]
        );
        exit;
    }

    public static function encryptPassword($password) {
        return openssl_encrypt($password, self::ENCRYPTION_METHOD, self::ENCRYPTION_KEY, self::ENCRYPTION_OPTION, self::ENCRYPTION_IV);
    }

    public static function decryptPassword($encryptedPassword) {
        return openssl_decrypt($encryptedPassword, self::ENCRYPTION_METHOD, self::ENCRYPTION_KEY, self::ENCRYPTION_OPTION, self::ENCRYPTION_IV);
    }

    public static function getPasswordWebService() {
        return self::decryptPassword(self::get_option('lpc_pwd_webservices'));
    }

    public static function replaceAccents(string $text) {
        return str_replace(
            ['’', 'é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ô', 'ö', 'î', 'ï', 'ù', 'û', 'ü', 'ç', 'ÿ', 'É', 'È', 'Ê', 'Ë', 'À', 'Â', 'Ä', 'Ô', 'Ö', 'Î', 'Ï', 'Ù', 'Û', 'Ü', 'Ç', 'Ÿ'],
            ['\'', 'e', 'e', 'e', 'e', 'a', 'a', 'a', 'o', 'o', 'i', 'i', 'u', 'u', 'u', 'c', 'y', 'E', 'E', 'E', 'E', 'A', 'A', 'A', 'O', 'O', 'I', 'I', 'U', 'U', 'U', 'C', 'Y'],
            $text
        );
    }

    public static function tooltip(string $text): string {
        return '<span class="woocommerce-help-tip" data-tip="' . esc_attr($text) . '"></span>';
    }

    public static function getMatchingPackaging(float $numberOfProducts, float $cartWeight, array $productDimensions) {
        $packagings = self::get_option('lpc_packagings', []);
        usort($packagings, function ($a, $b) {
            return $a['priority'] > $b['priority'] ? 1 : - 1;
        });

        foreach ($packagings as $packaging) {
            if (!empty($packaging['max_products']) && $numberOfProducts > $packaging['max_products']) {
                continue;
            }

            if (!empty($packaging['max_weight']) && $cartWeight > $packaging['max_weight']) {
                continue;
            }

            $packagingDimensions = [
                $packaging['length'],
                $packaging['width'],
                $packaging['depth'],
            ];
            foreach ($productDimensions as $dimensions) {
                if (!self::isPackagingFitting($packagingDimensions, $dimensions)) {
                    continue 2;
                }
            }

            return $packaging;
        }

        return null;
    }

    private static function isPackagingFitting(array $packagingDimensions, array $productDimensions): bool {
        sort($packagingDimensions);
        sort($productDimensions);

        if (empty($productDimensions[0])) {
            return true;
        }

        foreach ($productDimensions as $key => $dimension) {
            if ($dimension > $packagingDimensions[$key]) {
                return false;
            }
        }

        return true;
    }
}
