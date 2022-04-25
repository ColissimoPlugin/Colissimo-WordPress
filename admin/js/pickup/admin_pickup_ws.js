var lpcGoogleMap, lpcMarkers = [], lpcOpenedInfoWindow, lpcConfirmRelayDescText, lpcConfirmRelayText, lpcChooseRelayText;

jQuery(function ($) {
    // Function called when the popup is opened to initialize the Gmap
    function lpcInitMap(origin) {

        $affectMethodDiv = $(origin).closest('.lpc_order_affect_available_methods');

        let mapOptions = {
            zoom: 10,
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            center: {
                lat: 48.866667,
                lng: 2.333333
            },
            disableDefaultUI: true
        };
        lpcGoogleMap = new google.maps.Map(document.getElementById('lpc_map'), mapOptions);

        // TODO: if we have the client's address from WC, do we want to center on this location instead?
        // Center the map on the client's position
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (position) {
                initialLocation = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
                lpcGoogleMap.setCenter(initialLocation);
            });
        }

        let $templateContent = $('#tmpl-lpc_pick_up_web_service').html();
        let $templateContentHtml = $($.parseHTML($templateContent));

        let $selectors = [];
        $selectors['address'] = '#lpc_modal_relays_search_address .lpc_modal_relays_search_input';
        $selectors['zipcode'] = '#lpc_modal_relays_search_zipcode .lpc_modal_relays_search_input';
        $selectors['city'] = '#lpc_modal_relays_search_city .lpc_modal_relays_search_input';
        $selectors['country'] = '#lpc_modal_relays_country_id';

        let $templateAddress = $templateContentHtml.find($selectors['address']).val();
        let $templateZipcode = $templateContentHtml.find($selectors['zipcode']).val();
        let $templateCity = $templateContentHtml.find($selectors['city']).val();
        let $templateCountry = $templateContentHtml.find($selectors['country']).val();

        $($selectors['address']).val($templateAddress);
        $($selectors['zipcode']).val($templateZipcode);
        $($selectors['city']).val($templateCity);
        $($selectors['country']).val($templateCountry);

        // Load the relays when opening the map if the client already entered an address
        if ($('#lpc_modal_relays_search_zipcode input').val().length && $('#lpc_modal_relays_search_city input').val().length) {
            lpcLoadRelays();
        }

        $('#lpc_layer_button_search').on('click', function () {
            lpcLoadRelays();
        });
    }

    // Load relays for an address
    function lpcLoadRelays() {
        let $address = $('#lpc_modal_relays_search_address input').val();
        let $zipcode = $('#lpc_modal_relays_search_zipcode input').val();
        let $city = $('#lpc_modal_relays_search_city input').val();

        let $errorDiv = $('#lpc_layer_error_message');
        let $listRelaysDiv = $('#lpc_layer_list_relays');

        let $loader = $('#lpc_layer_relays_loader');

        let countryId = $('#lpc_modal_relays_country_id').val();

        if ('' === countryId || undefined === countryId) {
            countryId = $('#shipping_country').val();
        }

        if ('' === countryId || undefined === countryId) {
            countryId = 'FR';
        }

        $.ajax({
            url: lpcPickUpWS.ajaxURL,
            type: 'POST',
            dataType: 'json',
            data: {
                address: $address,
                zipCode: $zipcode,
                city: $city,
                countryId: countryId
            },
            beforeSend: function () {
                $errorDiv.hide();
                $listRelaysDiv.hide();
                $loader.show();
            },
            success: function (response) {
                $loader.hide();
                if (response.type === 'success') {
                    $listRelaysDiv.html(response.html);
                    $listRelaysDiv.show();
                    lpcConfirmRelayDescText = response.confirmRelayDescText;
                    lpcConfirmRelayText = response.confirmRelayText;
                    lpcChooseRelayText = response.chooseRelayText;
                    lpcAddRelaysOnMap();
                    lpcMapResize();
                } else {
                    $errorDiv.html(response.message);
                    $errorDiv.show();
                }
            }
        });
    }

    // Display the markers on the map
    function lpcAddRelaysOnMap() {
        // Clean old markers from the map
        lpcMarkers.forEach(function (element) {
            element.setMap(null);
        });
        lpcMarkers.length = 0;

        let markers = $('.lpc_layer_relay');

        // No new markers
        if (markers.length === 0) {
            return;
        }

        // Get the new markers and place them on the map
        let bounds = new google.maps.LatLngBounds();
        markers.each(function (index, element) {
            let relayPosition = new google.maps.LatLng($(element).attr('data-lpc-relay-latitude'), $(element).attr('data-lpc-relay-longitude'));

            let markerLpc = new google.maps.Marker({
                map: lpcGoogleMap,
                position: relayPosition,
                title: $(this)
                    .find('.lpc_layer_relay_name')
                    .text(),
                icon: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png'
            });

            // Add the information window on each marker
            let infowindowLpc = lpcInfoWindowGenerator($(this));
            lpcAttachClickInfoWindow(markerLpc, infowindowLpc, index);
            lpcAttachClickChooseRelay(element);

            lpcMarkers.push(markerLpc);
            bounds.extend(relayPosition);
        });

        lpcGoogleMap.fitBounds(bounds);
    }

    // Create marker popup content
    function lpcInfoWindowGenerator(relay) {
        let indexRelay = relay.find('.lpc_relay_choose').attr('data-relayindex');

        let contentString = '<div class="info_window_lpc">';
        contentString += '<span class="lpc_store_name">' + relay.find('.lpc_layer_relay_name').text() + '</span>';
        contentString += '<span class="lpc_store_address">' + relay.find('.lpc_layer_relay_address_street').text() + '<br>' + relay.find(
            '.lpc_layer_relay_address_zipcode').text() + ' ' + relay.find('.lpc_layer_relay_address_city').text() + '</span>';
        contentString += '<span class="lpc_store_schedule">' + relay.find('.lpc_layer_relay_schedule').html() + '</span>';
        contentString += '<a href="#" class="lpc_relay_choose lpc_relay_popup_choose" data-relayindex=' + indexRelay + '>' + lpcChooseRelayText + '</a>';
        contentString += '</div>';

        return new google.maps.InfoWindow({
            content: contentString
        });
    }

    // Add display relay detail click event
    function lpcAttachClickInfoWindow(marker, infoWindow, index) {
        // TODO: in the Gmaps documentation but addListener is deprecated
        marker.addListener('click', function () {
            lpcClickHandler(marker, infoWindow);
        });

        $('#lpc_layer_relay_' + index).click(function () {
            lpcClickHandler(marker, infoWindow);
        });
    }

    // Display details on markers
    function lpcClickHandler(marker, infoWindow) {
        if (lpcOpenedInfoWindow) {
            lpcOpenedInfoWindow.close();
        }

        infoWindow.open(lpcGoogleMap, marker);
        lpcOpenedInfoWindow = infoWindow;
    }

    function lpcMapResize() {
        google.maps.event.trigger(lpcGoogleMap, 'resize');
    }

    function lpcAttachClickChooseRelay(element) {
        let divChooseRelay = jQuery(element).find('.lpc_relay_choose');
        let relayIndex = divChooseRelay.attr('data-relayindex');

        jQuery(document).off('click', '.lpc_relay_choose[data-relayindex=' + relayIndex + ']');

        jQuery(document).on('click', '.lpc_relay_choose[data-relayindex=' + relayIndex + ']', function (e) {
            e.preventDefault();
            lpcAttachOnclickConfirmationRelay(relayIndex);
        });
    }

    function lpcAttachOnclickConfirmationRelay(relayIndex) {
        let relayClicked = $('#lpc_layer_relay_' + relayIndex);

        if (relayClicked === null) {
            return;
        }

        let lpcRelayIdTmp = relayClicked.find('.lpc_layer_relay_id').text();
        let lpcRelayNameTmp = relayClicked.find('.lpc_layer_relay_name').text();
        let lpcRelayAddressTmp = relayClicked.find('.lpc_layer_relay_address_street').text();
        let lpcRelayCityTmp = relayClicked.find('.lpc_layer_relay_address_city').text();
        let lpcRelayZipcodeTmp = relayClicked.find('.lpc_layer_relay_address_zipcode').text();
        let lpcRelayCountryTmp = relayClicked.find('.lpc_layer_relay_address_country').text();
        let lpcRelayTypeTmp = relayClicked.find('.lpc_layer_relay_type').text();

        if (confirm(lpcConfirmRelayText
                    + '\n\n'
                    + lpcConfirmRelayDescText
                    + '\n'
                    + lpcRelayNameTmp
                    + '\n'
                    + lpcRelayAddressTmp
                    + '\n'
                    + lpcRelayZipcodeTmp
                    + ' '
                    + lpcRelayCityTmp)) {
            lpcChooseRelay(lpcRelayIdTmp,
                lpcRelayNameTmp,
                lpcRelayAddressTmp,
                lpcRelayZipcodeTmp,
                lpcRelayCityTmp,
                lpcRelayTypeTmp,
                lpcRelayCountryTmp,
                relayClicked
            );
        }
    }

    function lpcChooseRelay(lpcRelayId, lpcRelayName, lpcRelayAddress, lpcRelayZipcode, lpcRelayCity, lpcRelayTypeTmp, lpcRelayCountry, relayClicked) {
        let relayData = {
            identifiant: lpcRelayId,
            nom: lpcRelayName,
            adresse1: lpcRelayAddress,
            codePostal: lpcRelayZipcode,
            localite: lpcRelayCity,
            libellePays: lpcRelayCountry,
            typeDePoint: lpcRelayTypeTmp,
            codePays: relayClicked.attr('data-lpc-relay-country_code')
        };

        $affectMethodDiv.find('input[name="lpc_order_affect_relay_informations"]').val(JSON.stringify(relayData));
        $affectMethodDiv.find('.lpc_order_affect_relay_information_displayed')
                        .html(relayData['nom']
                              + ' ('
                              + relayData['identifiant']
                              + ')'
                              + '<br>'
                              + relayData['adresse1']
                              + '<br>'
                              + relayData['codePostal']
                              + ' '
                              + relayData['localite']);

        $('.lpc-modal .modal-close').click();
    }

    window.lpcInitMapWebService = lpcInitMap;
});
