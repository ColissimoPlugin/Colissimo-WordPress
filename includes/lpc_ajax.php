<?php

class LpcAjax extends LpcComponent {
    protected $tasks = [];

    public function init() {
        // Ajax calls definition
        add_action('wp_ajax_' . LPC_COMPONENT, [$this, 'dispatch']); // Logged in users
        add_action('wp_ajax_nopriv_' . LPC_COMPONENT, [$this, 'dispatch']); // Visitors
    }

    public function dispatch() {
        $ajaxCall = LpcHelper::getVar('task');
        if (is_string($ajaxCall)) {
            if (isset($this->tasks[$ajaxCall])) {
                $f = $this->tasks[$ajaxCall];
                $result = $f();
            } else {
                $result = $this->makeAndLogError(['message' => sprintf(__('Unknown ajax call: %s', 'wc_colissimo'), $ajaxCall)]);
            }
        } else {
            $result = $this->makeAndLogError(['message' => __('Wrong ajax call type', 'wc_colissimo')]);
        }

        echo wp_json_encode($result);
        exit;
    }

    public function register($taskName, callable $f) {
        $this->tasks[$taskName] = $f;
    }

    public function makePayload($type, array $payload) {
        return array_merge(
            $payload,
            ['type' => $type]
        );
    }

    public function makeError(array $payload) {
        return $this->makePayload('error', $payload);
    }

    public function makeSuccess(array $payload) {
        return $this->makePayload('success', $payload);
    }

    public function makeAndLogError(array $payload) {
        LpcLogger::error($payload['message']);

        return $this->makePayload('error', $payload);
    }

    public function getUrlForTask($taskName) {
        return admin_url('admin-ajax.php?action=' . LPC_COMPONENT . '&task=' . $taskName);
    }
}
