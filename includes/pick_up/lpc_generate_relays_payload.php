<?php

class LpcGenerateRelaysPayload {
    protected $payload;

    public function __construct() {
        $this->payload = [];
    }

    public function withLogin($login = null) {
        if (null === $login) {
            $login = LpcHelper::get_option('lpc_id_webservices', '');
        }

        if (empty($login)) {
            unset($this->payload['accountNumber']);
        } else {
            $this->payload['accountNumber'] = $login;
        }

        return $this;
    }

    public function withPassword($password = null) {
        if (null === $password) {
            $password = LpcHelper::get_option('lpc_pwd_webservices', '');
        }

        if (empty($password)) {
            unset($this->payload['password']);
        } else {
            $this->payload['password'] = $password;
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

    public function withWeight($weight) {
        if (empty($weight)) {
            unset($this->payload['weight']);
        } else {
            $this->payload['weight'] = $weight;
        }

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

    public function withOptionInter($optionInter = null) {
        if (null === $optionInter) {
            $optionInter = LpcHelper::get_option('lpc_show_international', 'yes') == 'yes' ? '1' : '0';
        }

        if (empty($optionInter) || 'FR' == $this->payload['countryCode']) {
            $this->payload['optionInter'] = '0';
        } else {
            $this->payload['optionInter'] = $optionInter;
        }

        return $this;
    }

    public function checkConsistency() {
        $this->checkLogin();
        $this->checkAddress();
        $this->checkOptions();
    }

    protected function checkLogin() {
        if (empty($this->payload['accountNumber']) || empty($this->payload['password'])) {
            throw new Exception(__('Login and password required to get relay points', 'wc_colissimo'));
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
