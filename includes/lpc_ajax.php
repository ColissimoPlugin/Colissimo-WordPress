<?php
defined('ABSPATH') || die('Restricted Access');

class LpcAjax extends LpcComponent {
    private const NONCE_NAME = '_lpcnonce';

    protected array $tasks = [];

    public function init() {
        // Ajax calls definition
        add_action('wp_ajax_' . LPC_COMPONENT, [$this, 'dispatch']); // Logged in users
        add_action('wp_ajax_nopriv_' . LPC_COMPONENT, [$this, 'dispatch']); // Visitors
    }

    public function dispatch() {
        $ajaxCall = LpcHelper::getVar('task');
        if (!is_string($ajaxCall)) {
            $result = $this->makeAndLogError(['message' => __('Wrong ajax call type', 'wc_colissimo')]);
            $this->jsonResponse($result);
        }

        if (1 !== (int) check_ajax_referer($ajaxCall, self::NONCE_NAME, false)) {
            $result = $this->makeAndLogError(['message' => 'Authentication failed']);
            $this->jsonResponse($result);
        }

        if (!isset($this->tasks[$ajaxCall])) {
            $result = $this->makeAndLogError(['message' => sprintf(__('Unknown ajax call: %s', 'wc_colissimo'), $ajaxCall)]);
            $this->jsonResponse($result);
        }

        $isAdmin = current_user_can('lpc_manage_settings');
        if (!$isAdmin && $this->tasks[$ajaxCall]['onlyAdmin']) {
            $result = $this->makeAndLogError(['message' => 'Not allowed']);
            $this->jsonResponse($result);
        }

        $f      = $this->tasks[$ajaxCall]['callback'];
        $result = $f();
        $this->jsonResponse($result);
    }

    public function register(string $taskName, callable $f, bool $onlyAdmin = true): void {
        $this->tasks[$taskName] = [
            'callback'  => $f,
            'onlyAdmin' => $onlyAdmin,
        ];
    }

    public function makePayload($type, array $payload) {
        return array_merge(
            $payload,
            ['type' => $type]
        );
    }

    private function jsonResponse($result) {
        echo wp_json_encode($result);
        exit;
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

    public function getUrlForTask(string $taskName): string {
        return add_query_arg(
            self::NONCE_NAME,
            wp_create_nonce($taskName),
            admin_url(
                'admin-ajax.php?action=' . LPC_COMPONENT . '&task=' . $taskName
            )
        );
    }
}
