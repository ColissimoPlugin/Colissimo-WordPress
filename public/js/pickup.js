jQuery(function ($) {
    function applyRelayPointInfoToShippingAddress(point) {
        $('#ship-to-different-address-checkbox')
            .attr('checked', true)
            .trigger('change');
        $('#shipping_company').val(point.nom);
        $('#shipping_address_1').val(point.adresse1);
        $('#shipping_address_2').val(point.adresse2);
        $('#shipping_postcode').val(point.codePostal);
        $('#shipping_city').val(point.localite);
        $('#shipping_first_name').val($('#billing_first_name').val());
        $('#shipping_last_name').val($('#billing_last_name').val());

        $('#shipping_country')
            .val(point.codePays)
            .trigger('change');
    }

    window.lpc_pickup_applyRelayPointInfoToShippingAddress = applyRelayPointInfoToShippingAddress;
});
