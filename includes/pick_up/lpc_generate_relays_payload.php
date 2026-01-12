<?php
defined('ABSPATH') || die('Restricted Access');

class LpcGenerateRelaysPayload {
    protected $payload;

    public function __construct() {
        $this->payload = [
            'origin' => 'CMS',
        ];
    }

    public function withCredentials() {
        if ('api_key' === LpcHelper::get_option('lpc_credentials_type', 'api_key')) {
            $this->payload['apiKey'] = LpcHelper::get_option('lpc_apikey');
        } else {
            $this->payload['accountNumber'] = LpcHelper::get_option('lpc_id_webservices');
            $this->payload['password']      = LpcHelper::getPasswordWebService();
        }

        $parentAccountId = LpcHelper::get_option('lpc_parent_account');
        if (!empty($parentAccountId)) {
            $this->payload['codTiersPourPartenaire'] = $parentAccountId;
        }

        return $this;
    }

    public function withAddress(array $address) {
        $this->payload['address']     = $address['address'];
        $this->payload['zipCode']     = $address['zipCode'];
        $this->payload['city']        = $address['city'];
        $this->payload['countryCode'] = $address['countryCode'];

        return $this;
    }

    public function withShippingDate(DateTime $shippingDate = null) {
        if (null === $shippingDate) {
            $shippingDate           = new DateTime();
            $numberOfDayPreparation = intval(LpcHelper::get_option('lpc_preparation_time', '1'));
            $shippingDate->add(new DateInterval('P' . $numberOfDayPreparation . 'D'));
        }

        if (empty($shippingDate)) {
            unset($this->payload['shippingDate']);
        } else {
            $this->payload['shippingDate'] = $shippingDate->format('d/m/Y');
        }

        return $this;
    }

    public function withOptionInter() {
        if ('FR' === $this->payload['countryCode']) {
            $this->payload['optionInter'] = '0';
        } else {
            $this->payload['optionInter'] = '1';
        }

        return $this;
    }

    public function withRelayTypeFilter(?int $weight = null) {
        if (empty($weight)) {
            $cart = WC()->cart;
            if (!empty($cart)) {
                $weight = wc_get_weight(WC()->cart->get_cart_contents_weight(), 'kg');
            }
        }

        if (!empty($weight) && $weight > 20) {
            $this->payload['filterRelay'] = '0';

            return $this;
        }

        $relayTypes = LpcHelper::get_option('lpc_relay_types');
        if (empty($relayTypes)) {
            $relayTypes = '1';
        } elseif ('-1' === $relayTypes) {
            $relayTypes = '0';
        }

        $this->payload['filterRelay'] = $relayTypes;

        return $this;
    }

    public function checkConsistency() {
        $this->checkLogin();
        $this->checkAddress();
        $this->checkOptions();
    }

    protected function checkLogin() {
        if ('api_key' === LpcHelper::get_option('lpc_credentials_type', 'api_key')) {
            if (empty($this->payload['apiKey'])) {
                throw new Exception(__('Application key required to get relay points', 'wc_colissimo'));
            }
        } else {
            if (empty($this->payload['accountNumber']) || empty($this->payload['password'])) {
                throw new Exception(__('Login and password required to get relay points', 'wc_colissimo'));
            }
        }
    }

    protected function checkAddress() {
        if (empty($this->payload['zipCode'])) {
            throw new Exception(__('Zipcode required to get relay points', 'wc_colissimo'));
        }

        if (empty($this->payload['city'])) {
            throw new Exception(__('City required to get relay points', 'wc_colissimo'));
        }

        if (empty($this->payload['countryCode'])) {
            throw new Exception(__('Country code required to get relay points', 'wc_colissimo'));
        }
    }

    protected function checkOptions() {
        if (empty($this->payload['shippingDate'])) {
            throw new Exception(__('Shipping date required to get relay points', 'wc_colissimo'));
        }

        if (!empty($this->payload['optionInter']) && '1' == $this->payload['optionInter'] && 'FR' == $this->payload['countryCode']) {
            throw new Exception(__("The international option can't be enabled if the country destination is France", 'wc_colissimo'));
        }
    }

    public function assemble() {
        // array_merge to make a copy
        return array_merge($this->payload);
    }
}
