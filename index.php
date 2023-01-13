<?php
/**
 * Plugin Name: Colissimo shipping methods for WooCommerce
 * Description: This extension gives you the possibility to use the Colissimo shipping methods in WooCommerce
 * Version: 1.7.5
 * Author: Colissimo
 * Author URI: https://www.colissimo.entreprise.laposte.fr/fr
 * Text Domain: wc_colissimo
 *
 * WC requires at least: 3.6.5
 * WC tested up to: 6.5.1
 *
 * @package wc_colissimo
 *
 * License: GNU General Public License v3.0
 */

defined('ABSPATH') || die('Restricted Access');
// Make sure WooCommerce is active before declaring the shipping methods
if (
    file_exists(WPMU_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'woocommerce' . DIRECTORY_SEPARATOR . 'woocommerce.php')
    || in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))
    || (is_multisite() && in_array('woocommerce/woocommerce.php',
                                   apply_filters('active_plugins', array_keys(get_site_option('active_sitewide_plugins')))))
) {

    // Load defines
    if (!defined('DS')) {
        define('DS', DIRECTORY_SEPARATOR);
    }

    define('LPC_COMPONENT', basename(plugin_dir_path(__FILE__)));
    define('LPC_FOLDER', plugin_dir_path(__FILE__));
    define('LPC_RESOURCE_FOLDER', LPC_FOLDER . DS . 'resources' . DS);
    define('LPC_INCLUDES', LPC_FOLDER . 'includes' . DS);
    define('LPC_ADMIN', LPC_FOLDER . 'admin' . DS);
    define('LPC_PUBLIC', LPC_FOLDER . 'public' . DS);
    define('LPC_CONTACT_EMAIL', 'ecommerce@acyba.com');
    define('LPC_CONTACT_PHONE', '0241742088');

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $pluginData = get_plugin_data(LPC_FOLDER . DS . 'index.php');
    define('LPC_VERSION', $pluginData['Version']);

    class LpcInit {
        protected $register;

        /**
         * LpcInit constructor.
         */
        public function __construct() {
            $this->loadLanguages();

            require_once LPC_INCLUDES . 'lpc_register.php';
            $this->register = new LpcRegister();

            require_once LPC_INCLUDES . 'init.php';
            new LpcIncludeInit();

            // Load the logger class
            require_once LPC_INCLUDES . 'lpc_logger.php';
            require_once LPC_INCLUDES . 'lpc_helper.php';

            if (defined('WP_ADMIN') && WP_ADMIN) {
                require_once LPC_ADMIN . 'init.php';
                new LpcAdminInit();

                if (defined('DOING_AJAX') && DOING_AJAX) {
                    // needed for ajax calls from front
                    require_once LPC_PUBLIC . 'init.php';
                    new LpcPublicInit();
                }
            } else {
                require_once LPC_PUBLIC . 'init.php';
                new LpcPublicInit();
            }

            $this->register->init();
            $this->initCapabilities();
            $this->register_rewrite_rules();
            $this->checkCompatibilty();
            $this->checkCron();
            $this->handleDDP();
        }

        private function handleDDP() {
            add_action('woocommerce_after_order_object_save', [$this, 'saveDdpInformation']);
        }

        public function saveDdpInformation($order) {
            $orderId  = $order->get_id();
            $usingDdp = get_post_meta($orderId, 'lpc_using_ddp', true);

            if (in_array($usingDdp, [0, 1])) {
                return;
            }

            $shipping_methods  = $order->get_shipping_methods();
            $shippingMethodIds = array_map(
                function (WC_Order_item_Shipping $v) {
                    return ($v->get_method_id());
                },
                $shipping_methods
            );

            update_post_meta($orderId, 'lpc_using_ddp', 0);
            if (empty($shippingMethodIds)) {
                return;
            }

            if (strpos(array_pop($shippingMethodIds), 'ddp') === false) {
                return;
            }

            $extraCost = LpcHelper::get_option('lpc_extracost_' . $order->get_shipping_country(), 0);
            update_post_meta($orderId, 'lpc_ddp_cost', $extraCost);
            update_post_meta($orderId, 'lpc_using_ddp', 1);
        }

        public function initCapabilities() {
            register_activation_hook(
                LPC_FOLDER . 'index.php',
                function () {
                    $lpcUpdate = new LpcUpdate();
                    $lpcUpdate->createCapabilities();
                }
            );
        }

        protected function register_rewrite_rules() {
            require_once LPC_PUBLIC . 'tracking' . DS . 'lpc_tracking_page.php';
            require_once LPC_PUBLIC . 'order' . DS . 'lpc_bal_return.php';

            add_action(
                'init',
                function () {
                    LpcTrackingPage::addRewriteRule();
                    LpcBalReturn::addRewriteRule();
                }
            );

            register_deactivation_hook(LPC_FOLDER . 'index.php', 'flush_rewrite_rules');
            register_activation_hook(
                LPC_FOLDER . 'index.php',
                function () {
                    LpcTrackingPage::addRewriteRule();
                    LpcBalReturn::addRewriteRule();

                    flush_rewrite_rules();
                }
            );
        }

        protected function checkCompatibilty() {
            require_once LPC_ADMIN . 'lpc_compatibility.php';
            LpcCompatibility::checkCDI();
            LpcCompatibility::checkJQueryMigrate();
            LpcCompatibility::checkJQueryMigrateWP56();
        }

        protected function loadLanguages() {
            add_action(
                'init',
                function () {
                    load_plugin_textdomain('wc_colissimo', false, basename(dirname(__FILE__)) . '/languages/');
                }
            );
        }

        // Be sure that CRON task are running
        protected function checkCron() {
            if (!wp_next_scheduled('update_colissimo_statuses')) {
                wp_schedule_event(time(), 'hourly', 'update_colissimo_statuses');
            }
        }
    }

    new LpcInit();
}
