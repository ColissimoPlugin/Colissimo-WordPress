<?php

require_once LPC_FOLDER . 'lib' . DS . 'MTOMSoapClient.php';

abstract class LpcSoapApi extends LpcComponent {

    private $soapClient;

    abstract public function getApiUrl();

    protected function getSoapClient(array $params = []) {
        if (null === $this->soapClient) {
            $this->soapClient = new KeepItSimple\Http\Soap\MTOMSoapClient($this->getApiUrl(), $params);
        }

        return $this->soapClient;
    }

}
