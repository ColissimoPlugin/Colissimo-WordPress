<?php

require_once LPC_ADMIN . 'lpc_admin_wc_order_action.php';

class LpcOrderGenerateOutwardLabelAction extends LpcAdminWCOrderAction {
	const ACTION_NAME = 'lpc_order_generate_outward_label';

	protected $labelGenerationOutward;
	protected $shippingMethods;

	public function __construct(
		LpcLabelGenerationOutward $labelGenerationOutward = null,
		LpcShippingMethods $shippingMethods = null
	) {
		$this->labelGenerationOutward = LpcRegister::get('labelGenerationOutward', $labelGenerationOutward);
		$this->shippingMethods        = LpcRegister::get('shippingMethods', $shippingMethods);
	}

	public function getDependencies() {
		return ['shippingMethods', 'labelGenerationOutward'];
	}

	public function getActionName() {
		return self::ACTION_NAME;
	}

	public function getActionLabel() {
		return __('Generate Colissimo Outward label', 'wc_colissimo');
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
			$this->labelGenerationOutward->generate($order);

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
