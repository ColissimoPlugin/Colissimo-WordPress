<?php

defined('ABSPATH') || die('Restricted Access');
require_once LPC_FOLDER . DS . 'lib' . DS . 'MergePdf.class.php';

class LpcThermalLabelPrintAction extends LpcComponent {

    const THERMAL_LABEL_INFOS_VAR_NAME = 'lpc_thermal_labels_infos';
    const TRACKING_NUMBER_VAR_NAME = 'lpc_tracking_number';
    const AJAX_TASK_NAME = 'label/url_print_thermal';

    /** @var LpcAjax */
    protected $ajaxDispatcher;
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;

    public function __construct(
        LpcAjax $ajaxDispatcher = null,
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcInwardLabelDb $inwardLabelDb = null
    ) {
        $this->ajaxDispatcher = LpcRegister::get('ajaxDispatcher', $ajaxDispatcher);
        $this->outwardLabelDb = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->inwardLabelDb  = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
    }

    public function getDependencies() {
        return ['ajaxDispatcher', 'outwardLabelDb', 'inwardLabelDb'];
    }

    public function init() {
        $this->listenToAjaxAction();
    }

    protected function listenToAjaxAction() {
        $this->ajaxDispatcher->register(self::AJAX_TASK_NAME, [$this, 'getUrlsForThermalPrint']);
    }

    public function getThermalPrintActionUrl() {
        return $this->ajaxDispatcher->getUrlForTask(self::AJAX_TASK_NAME);
    }

    public function getUrlsForThermalPrint() {
        if (!current_user_can('lpc_print_labels')) {
            header('HTTP/1.0 401 Unauthorized');

            return $this->ajaxDispatcher->makeAndLogError(
                [
                    'message' => 'unauthorized access to thermal outward label print',
                ]
            );
        }

        $urls = [];

        $thermalLabelsInfos = LpcHelper::getVar(self::THERMAL_LABEL_INFOS_VAR_NAME, [], 'array');

        foreach ($thermalLabelsInfos as $oneThermalInfo) {
            $trackingNumber = $oneThermalInfo[self::TRACKING_NUMBER_VAR_NAME];

            if (empty($trackingNumber)) {
                LpcLogger::error(
                    __METHOD__ . ' tracking number missing'
                );

                return json_encode($urls);
            }

            $isOutward = true;
            $label     = $this->getLabel($trackingNumber, $isOutward);

            if (
                LpcLabelGenerationPayload::LABEL_FORMAT_DPL !== $label['format']
                && LpcLabelGenerationPayload::LABEL_FORMAT_ZPL !== $label['format']
            ) {
                continue;
            }

            $url = $this->generateUrl($label);

            if ($url['success']) {
                $urls[] = [
                    'trackingNumber' => $trackingNumber,
                    'url'            => $url['info'],
                ];
            } elseif (!empty($url['info'])) {
                LpcLogger::error(
                    __METHOD__ . ' ' . $url['info'],
                    [
                        'tracking_number' => $trackingNumber,
                    ]
                );
            }

            if ($isOutward) {
                $this->outwardLabelDb->updatePrintedLabel($trackingNumber);
            } else {
                $this->inwardLabelDb->updatePrintedLabel($trackingNumber);
            }
        }

        return json_encode($urls);
    }

    protected function generateUrl($label = []) {
        $response = [
            'success' => false,
            'info'    => '',
        ];

        if (empty($label['label'])) {
            $response['info'] = 'no outward label for order';

            return $response;
        }

        if (
            !empty($label['format'])
            && LpcLabelGenerationPayload::LABEL_FORMAT_DPL !== $label['format']
            && LpcLabelGenerationPayload::LABEL_FORMAT_ZPL !== $label['format']
        ) {
            $response['info'] = 'wrong label format';

            return $response;
        }

        $port        = LpcHelper::get_option('lpc_zpldpl_labels_port', 'USB');
        $ipAddress   = LpcHelper::get_option('lpc_zpldpl_labels_ip');
        $protocol    = LpcHelper::get_option('lpc_zpldpl_labels_protocol', 'DATAMAX');
        $urlPort     = LpcHelper::get_option('lpc_zpldpl_labels_urlport', '8000');
        $urlProtocol = strtolower(LpcHelper::get_option('lpc_zpldpl_labels_type', 'HTTP'));

        if ('USB' === $port && empty($protocol)) {
            $response['info'] = 'if USB is selected, a protocol has to be selected';

            return $response;
        } elseif ('ETHERNET' === $port && empty($ipAddress)) {
            $response['info'] = 'if Ethernet is selected, an IP address has to be set';

            return $response;
        }

        $labelContent = base64_encode($label['label']);

        if ('USB' === $port) {
            $ipAddress = '';
        }

        if ('ETHERNET' === $port) {
            $protocol = '';
        }

        $response['success'] = true;
        $response['info']    = $urlProtocol . '://localhost:' . $urlPort . '/imprimerEtiquetteThermique?port=' . $port . '&protocole=' . $protocol . '&adresseIp=' . $ipAddress . '&etiquette=' . $labelContent;

        return $response;
    }

    protected function getLabel($trackingNumber, &$isOutward) {
        $label = $this->outwardLabelDb->getLabelFor($trackingNumber);

        if (!empty($label['label'])) {
            $isOutward = true;

            return $label;
        }

        $label = $this->inwardLabelDb->getLabelFor($trackingNumber);

        if (!empty($label['label'])) {
            $isOutward = false;

            return $label;
        }

        return false;
    }
}
