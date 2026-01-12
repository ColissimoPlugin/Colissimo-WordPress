<?php
defined('ABSPATH') || die('Restricted Access');

abstract class LpcComponent {
    public function getDependencies(): array {
        return [];
    }

    public function init() {
    }
}
