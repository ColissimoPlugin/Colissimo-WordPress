<?php

class LpcDbDefinition extends LpcComponent {
    /** @var LpcOutwardLabelDb */
    protected $outwardLabelDb;
    /** @var LpcInwardLabelDb */
    protected $inwardLabelDb;
    /** @var LpcBordereauDb */
    protected $bordereauDb;

    public function __construct(
        LpcOutwardLabelDb $outwardLabelDb = null,
        LpcInwardLabelDb $inwardLabelDb = null,
        LpcBordereauDb $bordereauDb = null
    ) {
        $this->outwardLabelDb = LpcRegister::get('outwardLabelDb', $outwardLabelDb);
        $this->inwardLabelDb  = LpcRegister::get('inwardLabelDb', $inwardLabelDb);
        $this->bordereauDb    = LpcRegister::get('bordereauDb', $bordereauDb);
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

    public function getDependencies(): array {
        return ['outwardLabelDb', 'inwardLabelDb'];
    }

    public function defineTableLabel() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        if (is_multisite()) {
            $currentBlog = get_current_blog_id();
            $sites       = get_sites();

            foreach ($sites as $site) {
                if (is_object($site)) {
                    $site = get_object_vars($site);
                }
                switch_to_blog($site['blog_id']);
                $this->createTables();
            }

            switch_to_blog($currentBlog);
        } else {
            $this->createTables();
        }
    }

    private function createTables() {
        $outwardSql = $this->outwardLabelDb->getTableDefinition();
        dbDelta($outwardSql);

        $inwardSql = $this->inwardLabelDb->getTableDefinition();
        dbDelta($inwardSql);

        $bordereauSql = $this->bordereauDb->getTableDefinition();
        dbDelta($bordereauSql);
    }
}
