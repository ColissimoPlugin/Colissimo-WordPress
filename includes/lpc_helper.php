<?php

/**
 * Class LpcHelper
 */
class LpcHelper {

    const CONFIG_FILE = 'config_options.json';

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

    /**
     * @param        $var
     * @param        $default
     * @param string $type
     * @param string $hash
     *
     * @return array|string
     */
    public static function getVar($var, $default = '', $type = 'string', $hash = 'REQUEST') {

        // TODO: Handle the hash if needed
        $input = $_REQUEST;

        $result = isset($input[$var]) ? $input[$var] : $default;

        // TODO: Handle the type filtering
        if ('string' == $type) {
            $result = (string) $result;
        }

        return wp_unslash($result);
    }

    /**
     * @param        $option
     * @param mixed  $default
     *
     * @return array|string
     */
    public static function get_option($option, $default = '') {
        // Return the saved option if available
        if (get_option($option)) {
            return get_option($option);
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
}
