var $affectMethodDiv;

jQuery(function ($) {
    function init() {
        window.lpc_callback = function (point) {
            $('.lpc-modal .modal-close').click();

            $affectMethodDiv.find('input[name="lpc_order_affect_relay_informations"]').val(JSON.stringify(point));
            $affectMethodDiv.find('.lpc_order_affect_relay_information_displayed')
                            .html(point['nom']
                                  + ' ('
                                  + point['identifiant']
                                  + ')'
                                  + '<br>'
                                  + point['adresse1']
                                  + '<br>'
                                  + point['codePostal']
                                  + ' '
                                  + point['localite']);
        };

        $('#lpc_pick_up_widget_show_map').click(function (e) {
            e.preventDefault();

            $affectMethodDiv = $(this).closest('.lpc_order_affect_available_methods');

            $(this).WCBackboneModal({
                template: 'lpc_pick_up_widget_container'
            });

            var colissimoParams = {
                callBackFrame: 'lpc_callback'
            };

            $.extend(colissimoParams, window.lpc_widget_info);

            $('#lpc_widget_container').frameColissimoOpen(colissimoParams);
        });
    }

    init();
});
