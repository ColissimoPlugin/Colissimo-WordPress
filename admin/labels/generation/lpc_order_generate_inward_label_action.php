<?php

require_once LPC_ADMIN . 'lpc_admin_wc_order_action.php';


class LpcOrderGenerateInwardLabelAction extends LpcAdminWCOrderAction {
	const ACTION_NAME = 'lpc_order_generate_inward_label';

	protected $labelGenerationInward;
	protected $shippingMethods;

	public function __construct(
		LpcLabelGenerationInward $lpcLabelGenerationInward = null,
		LpcShippingMethods $shippingMethods = null
	) {
		$this->labelGenerationInward = LpcRegister::get('labelGenerationInward', $lpcLabelGenerationInward);
		$this->shippingMethods       = LpcRegister::get('shippingMethods', $shippingMethods);
	}

	public function getDependencies() {
		return ['labelGenerationInward', 'shippingMethods'];
	}

	public function getActionName() {
		return self::ACTION_NAME;
	}

	public function getActionLabel() {
		return __('Generate Colissimo Inward label', 'wc_colissimo');
	}

	public function declareAction(array $actions) {
		global $theorder;

		if (!empty($this->shippingMethods->getAllColissimoShippingMethodsOfOrder($theorder))) {
			return parent::declareAction($actions);
		}

		return $actions;
	}

	public function processAction(WC_Order $order) {
		try {
			$this->labelGenerationInward->generate($order);

			return $order;
		} catch (Exception $e) {
			add_action(
				'admin_notices',
				function () use ($e) {
					LpcHelper::displayNoticeException($e);
				}
			);
		}
	}
}
