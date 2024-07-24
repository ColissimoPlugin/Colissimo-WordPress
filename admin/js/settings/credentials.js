jQuery(function ($) {
    function init() {
        changeCredentialsType();
    }

    init();

    function changeCredentialsType() {
        const apiKeyFieldsContainers = $('#lpc_apikey, #lpc_contract_number').closest('tr');
        const accountFieldsContainers = $('#lpc_id_webservices, #lpc_pwd_webservices').closest('tr');
        $('#lpc_credentials_type').on('change', function () {
            if ('api_key' === $(this).val()) {
                accountFieldsContainers.hide();
                apiKeyFieldsContainers.show();
            } else {
                accountFieldsContainers.show();
                apiKeyFieldsContainers.hide();
            }
        }).trigger('change');
    }
});
