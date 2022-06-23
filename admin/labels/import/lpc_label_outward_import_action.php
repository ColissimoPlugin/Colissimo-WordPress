<?php

defined('ABSPATH') || die('Restricted Access');

class LpcLabelOutwardImportAction extends LpcComponent {
    const AJAX_TASK_NAME = 'label/import';

    /** @var LpcAjax */
    protected $ajaxDispatcher;

    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;

    /** @var LpcLabelGenerationOutward */
    protected $labelGenerationOutward;

    public function __construct(
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcAjax $ajaxDispatcher = null,
        LpcLabelGenerationOutward $labelGenerationOutward = null
    ) {
        $this->outwardLabelDb         = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->ajaxDispatcher         = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->labelGenerationOutward = LpcRegister::get('labelGenerationOutward', $labelGenerationOutward);
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'control']);
    }

    public function control() {
        if (!current_user_can('lpc_manage_labels')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to import new outward label',
                ]
            );
        }

        if (!isset($_FILES['tracking_number_import']['name'])) {
            die(json_encode(
                [
                    'type'    => 'error',
                    'message' => __('File not found', 'wc_colissimo'),
                ])
            );
        }

        $uploadOverrides = [
            'test_form' => false,
            'mimes'     => ['csv' => 'text/csv'],
        ];

        $file = $_FILES['tracking_number_import']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $file = wp_handle_upload($file, $uploadOverrides);

        if (isset($file['error'])) {
            die(json_encode(
                [
                    'type'    => 'error',
                    'message' => $file['error'],
                ])
            );
        }

        try {
            $fileContent = file_get_contents($file['file']);
        } catch (Exception $exception) {
            LpcLogger::error($exception->getMessage());
        }

        if (empty($fileContent)) {
            die(json_encode(
                [
                    'type'    => 'error',
                    'message' => __('The content of the file is empty', 'wc_colissimo'),
                ])
            );
        }

        $fileContent = str_replace(["\r\n", "\r"], "\n", $fileContent);
        $allLines    = explode("\n", $fileContent);

        $listSeparators = ["\t", ';'];
        $separator      = ',';
        foreach ($listSeparators as $sep) {
            if (strpos($allLines[0], $sep) !== false) {
                $separator = $sep;
                break;
            }
        }

        $columns = explode($separator, $allLines[0]);

        $requiredColumns = [
            'order_id'        => - 1,
            'tracking_number' => - 1,
        ];

        foreach ($requiredColumns as $requiredColumn => $pos) {
            if (!in_array($requiredColumn, $columns)) {
                continue;
            }

            $requiredColumns[$requiredColumn] = array_search($requiredColumn, $columns);
        }

        if (in_array(- 1, $requiredColumns)) {
            die(json_encode(
                [
                    'type'    => 'error',
                    'message' => __('Missing columns in the imported CSV', 'wc_colissimo'),
                ])
            );
        }

        $orderIdsIssues = [];

        foreach ($allLines as $key => $data) {
            if (0 === $key) {
                continue;
            }

            $data = explode($separator, $data);

            $orderId        = trim($data[$requiredColumns['order_id']]);
            $trackingNumber = trim($data[$requiredColumns['tracking_number']]);

            $insertion = $this->outwardLabelDb->insertFromThirdParty($orderId, $trackingNumber);

            if (empty($insertion)) {
                $orderIdsIssues[] = $orderId;
            }

            $order = wc_get_order($orderId);

            if (empty($order)) {
                $orderIdsIssues[] = $orderId;
                continue;
            }

            $this->labelGenerationOutward->applyStatusAfterLabelGeneration($order);

            $email_outward_label = LpcHelper::get_option(LpcOutwardLabelEmailManager::EMAIL_OUTWARD_TRACKING_OPTION, 'no');
            if (LpcOutwardLabelEmailManager::ON_OUTWARD_LABEL_GENERATION_OPTION === $email_outward_label) {
                /**
                 * Action called when a shipping label has been generated
                 *
                 * @since 1.6.4
                 */
                do_action(
                    'lpc_outward_label_generated_to_email',
                    ['order' => $order]
                );
            }
        }

        if (!empty($orderIdsIssues)) {
            die(json_encode(
                [
                    'type'    => 'error',
                    'message' => sprintf(__('Could not insert tracking number for order(s): %s', 'wc_colissimo'), implode(', ', $orderIdsIssues)),
                ])
            );
        }

        die(json_encode(['type' => 'success']));
    }

    private function getFilenameExtension($filename) {
        $endPos = strpos($filename, '?');
        if (false !== $endPos) {
            $filename = substr($filename, 0, $endPos);
        }

        $dot = strrpos($filename, '.');
        if (false === $dot) {
            return '';
        }

        return substr($filename, $dot + 1);
    }

    public function getUrlToImportTrackingNumbers() {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME);
    }
}
