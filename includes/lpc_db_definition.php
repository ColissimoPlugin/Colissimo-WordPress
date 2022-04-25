<?php

class LpcDbDefinition extends LpcComponent {
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;

    public function __construct(
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcInwardLabelDb $inwardLabelDb = null
    ) {
        $this->outwardLabelDb = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->inwardLabelDb  = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
    }

    public function init() {
        // only at plugin installation
        register_activation_hook(
            LPC_FOLDER . 'index.php',
            function () {
                $this->defineTableLabel();
            }
        );
    }

    public function getDependencies() {
        return ['outwardLabelDb', 'inwardLabelDb'];
    }

    public function defineTableLabel() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $outwardSql = $this->outwardLabelDb->getTableDefinition();
        dbDelta($outwardSql);

        $inwardSql = $this->inwardLabelDb->getTableDefinition();
        dbDelta($inwardSql);
    }
}
