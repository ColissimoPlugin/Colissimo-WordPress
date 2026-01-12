<?php

class LpcAdminProduct extends LpcComponent {
    public function init() {
        add_action(
            'current_screen',
            function ($currentScreen) {
                if ('post' === $currentScreen->base && 'product' === $currentScreen->post_type) {
                    $catSlugs = implode('", "', array_keys(LpcLabelGenerationPayload::HAZMAT_CATEGORIES));

                    global $wpdb;
                    // phpcs:disable
                    $terms = $wpdb->get_results('SELECT term_id, slug FROM ' . $wpdb->terms . ' WHERE slug IN ("' . $catSlugs . '")');
                    // phpcs:enable

                    $categories = [];
                    foreach ($terms as $term) {
                        $categories[$term->term_id] = LpcLabelGenerationPayload::HAZMAT_CATEGORIES[$term->slug]['max_weight'];
                    }

                    $weightUnit = LpcHelper::get_option('woocommerce_weight_unit', 'kg');

                    $weightToGrams = 1;
                    switch ($weightUnit) {
                        case 'kg':
                            $weightToGrams = 1000;
                            break;
                        case 'lbs':
                            $weightToGrams = 453.592;
                            break;
                        case 'oz':
                            $weightToGrams = 28.3495;
                            break;
                    }

                    LpcHelper::enqueueScript(
                        'lpc_product',
                        plugins_url('/js/products/lpc_product.js', LPC_ADMIN . 'init.php'),
                        null,
                        ['jquery-core'],
                        'lpcProduct',
                        [
                            'hazmat_attribute'       => LpcLabelGenerationPayload::HAZMAT_ATTRIBUTE,
                            'hazmat_categories'      => $categories,
                            'weight_to_grams_factor' => $weightToGrams,
                            'weight_limit_exceeded'  => __(
                                'This product\'s weight exceeds the limit allowed per parcel for this hazardous materials category: %1$sg / %2$sg',
                                'wc_colissimo'
                            ),
                        ]
                    );
                }
            }
        );
    }
}
