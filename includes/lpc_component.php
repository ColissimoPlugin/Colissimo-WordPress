<?php

abstract class LpcComponent {
    public function getDependencies(): array {
        return [];
    }

    public function init() {
    }
}
