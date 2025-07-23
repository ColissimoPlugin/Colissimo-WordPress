<?php
echo esc_html__('Relay point', 'wc_colissimo') . ': ';
echo esc_html(empty($args['pickUpLocationLabel']) ? __('Unknown', 'wc_colissimo') : ucfirst(strtolower($args['pickUpLocationLabel']))) . '<br />';
echo esc_html__('ID', 'wc_colissimo') . ': #';
echo esc_html(empty($args['pickUpLocationId']) ? __('Unknown', 'wc_colissimo') : $args['pickUpLocationId']) . '<br />';
echo esc_html__('Type', 'wc_colissimo') . ': ';
echo esc_html(empty($args['pickUpLocationType']) ? __('Unknown', 'wc_colissimo') : $args['pickUpLocationType']);
