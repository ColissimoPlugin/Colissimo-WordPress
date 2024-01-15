<?php

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

define('LpcWcBlock_VERSION', '0.1.0');

/**
 * Class for integrating with WooCommerce Blocks
 */
class LpcWcBlock_Blocks_Integration implements IntegrationInterface {

    /**
     * The name of the integration.
     *
     * @return string
     */
    public function get_name() {
        return 'lpc_wc_block';
    }

    /**
     * When called invokes any initialization/setup for the integration.
     */
    public function initialize() {
        $this->registerMainIntegration();
    }

    /**
     * Registers the main JS file required to add filters and Slot/Fills.
     */
    public function registerMainIntegration() {
        $script_path = '/includes/js/blockCheckout/index.js';

        $script_url = plugins_url($script_path, __FILE__);

        $script_asset_path = dirname(__FILE__) . '/includes/js/blockCheckout/index.asset.php';
        $script_asset      = file_exists($script_asset_path)
            ? require $script_asset_path
            : [
                'dependencies' => [],
                'version'      => $this->get_file_version($script_path),
            ];

        wp_register_script(
            'lpc_wc_block-blocks-integration',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        wp_set_script_translations(
            'lpc_wc_block-blocks-integration',
            'wc_colissimo',
            basename(dirname(__FILE__)) . '/languages/'
        );
    }

    /**
     * Returns an array of script handles to enqueue in the frontend context.
     *
     * @return string[]
     */
    public function get_script_handles() {
        return ['lpc_wc_block-blocks-integration'];
    }

    /**
     * Returns an array of script handles to enqueue in the editor context.
     *
     * @return string[]
     */
    public function get_editor_script_handles() {
        return ['lpc_wc_block-blocks-integration'];
    }

    /**
     * An array of key, value pairs of data made available to the block on the client side.
     *
     * @return array
     */
    public function get_script_data() {
        $data = [
            'lpc_wc_block-active' => true,
        ];

        return $data;
    }

    /**
     * Get the file modified time as a cache buster if we're in dev mode.
     *
     * @param string $file Local path to the file.
     *
     * @return string The cache buster value to use for the given file.
     */
    protected function get_file_version($file) {
        if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && file_exists($file)) {
            return filemtime($file);
        }

        return LpcWcBlock_VERSION;
    }
}
