<?php

require_once LPC_INCLUDES . 'lpc_component.php';

class LpcRegister {
    protected static $components = [];

    public static function register($key, LpcComponent $component) {
        if (!empty(self::$components[$key])) {
            throw new Exception('Component ' . $key . ' has already been registered!');
        }

        self::$components[$key] = $component;

        return $component;
    }

    /**
     * @throws Exception When the required object couldn't be found.
     */
    public static function get($key, $override = null) {
        if (empty($override)) {
            if (empty(self::$components[$key])) {
                throw new Exception('No such component ' . $key);
            }

            return self::$components[$key];
        } else {
            return $override;
        }
    }

    public static function init() {
        foreach (self::topoSort(self::$components) as $component) {
            $component->init();
        }
    }

    protected static function topoSort($components) {
        $stack  = [];
        $result = [];
        foreach ($components as $nodeId => $_) {
            $result = self::visit($nodeId, $stack, $result);
        }

        return array_map(
            function ($nodeId) {
                return self::get($nodeId);
            },
            $result
        );
    }

    private static function visit($nodeId, array $stack, array $result) {
        // Check is node already visited
        if (in_array($nodeId, $result)) {
            return $result;
        }
        // If node already in stack - we have circular dependency
        if (in_array($nodeId, $stack)) {
            throw new CircularDependencyException();
        }
        array_push($stack, $nodeId);
        $component = self::get($nodeId);
        foreach ($component->getDependencies() as $dependencyId) {
            $result = self::visit($dependencyId, $stack, $result);
        }
        array_pop($stack);
        array_push($result, $nodeId);

        return $result;
    }
}
