<?php

abstract class LpcAdminWCOrderAction extends LpcComponent {

	public function init() {
		add_action('woocommerce_order_actions', array($this, 'declareAction'));
		add_action('woocommerce_order_action_' . $this->getActionName(), array($this, 'processAction'));
	}

	abstract public function getActionName();
	abstract public function getActionLabel();

	public function declareAction(array $actions) {
		$actions[$this->getActionName()] = $this->getActionLabel();
		return $actions;
	}

	abstract public function processAction(WC_Order $order);
}
