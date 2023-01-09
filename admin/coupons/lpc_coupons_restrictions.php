<?php

class LpcCouponsRestrictions extends LpcComponent {

    public function init() {
        add_action('woocommerce_coupon_options_usage_restriction', [$this, 'addRestriction'], 10, 2);
        add_action('woocommerce_coupon_options_save', [$this, 'saveRestriction'], 10, 2);
    }

    public function addRestriction($coupon_get_id, $coupon) {
        $default = $coupon->get_meta('lpc_coupon_restriction');
        if (empty($default)) {
            $default = '';
        }

        $options     = [];
        $options[''] = __('No method', 'wc_colissimo');
        $options     = array_merge($options, LpcRegister::get('shippingMethods')->getAllShippingMethodsWithName());

        woocommerce_wp_select(
            [
                'id'                => 'lpc_coupon_restriction',
                'name'              => 'lpc_coupon_restriction[]',
                'label'             => __('Exclude shipping methods', 'wc_colissimo'),
                'description'       => __('When the user will enter this coupon code, the following delivery methods won\'t be available.', 'wc_colissimo'),
                'desc_tip'          => true,
                'options'           => $options,
                'custom_attributes' => ['multiple' => 'multiple'],
                'value'             => $default,
            ]);
    }

    public function saveRestriction($post_id, $coupon) {
        if (!isset($_REQUEST['woocommerce_meta_nonce'])) {
            return;
        }
        if (!wp_verify_nonce(
            sanitize_text_field(wp_unslash($_REQUEST['woocommerce_meta_nonce'])),
            'woocommerce_save_data'
        )) {
            return;
        }

        if (isset($_POST['lpc_coupon_restriction'])) {
            $values = array_map('sanitize_text_field', wp_unslash($_POST['lpc_coupon_restriction']));
            $values = in_array('', $values) ? [''] : $values;
            $coupon->update_meta_data('lpc_coupon_restriction', $values);
            $coupon->save_meta_data();
        } elseif (!empty($coupon->get_meta('lpc_coupon_restriction'))) {
            delete_post_meta($post_id, 'lpc_coupon_restriction');
        }
    }
}
