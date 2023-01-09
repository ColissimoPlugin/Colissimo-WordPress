var lpcGoogleMap, lpcMap, lpcMarkers = [], lpcGmapsOpenedInfoWindow, lpcConfirmRelayDescText, lpcConfirmRelayText, lpcChooseRelayText, $affectMethodDiv;

jQuery(function ($) {
    $(document.body)
        .on('updated_shipping_method', function () {
            initLpcModal(); // this is needed when a new shipping method is chosen
        })
        .on('updated_wc_div', function () {
            initLpcModal(); // this is needed when checkout is updated (new item quantity...)
        })
        .on('updated_checkout', function () {
            initLpcModal(); // this is needed when checkout is loaded or updated (new item quantity...)
        });

    // Function called when the popup is opened to initialize the Gmap
    function lpcInitMap(origin) {
        $affectMethodDiv = $(origin).closest('.lpc_order_affect_available_methods');

        let initialLatitude = 48.866667;
        let initialLongitude = 2.333333;

        // Center the map on the client's position
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (position) {
                if (lpcPickUpSelection.mapType === 'gmaps') {
                    initialLocation = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
                    lpcGoogleMap.setCenter(initialLocation);
                } else if (lpcPickUpSelection.mapType === 'leaflet') {
                    initialLatitude = position.coords.latitude;
                    initialLongitude = position.coords.longitude;
                }
            });
        }

        if (lpcPickUpSelection.mapType === 'gmaps') {
            lpcGoogleMap = new google.maps.Map(document.getElementById('lpc_map'), {
                zoom: 10,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                center: {
                    lat: initialLatitude,
                    lng: initialLongitude
                },
                disableDefaultUI: true
            });
        } else if (lpcPickUpSelection.mapType === 'leaflet') {
            lpcMap = L.map('lpc_map').setView([
                initialLatitude,
                initialLongitude
            ], 14);
            // Default map for open street map: https://tile.openstreetmap.org/{z}/{x}/{y}.png
            L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>'
            }).addTo(lpcMap);
        }

        let $templateContent = $('#tmpl-lpc_pick_up_web_service').html();
        let $templateContentHtml = $($.parseHTML($templateContent));

        let $selectors = [];
        $selectors['address'] = '#lpc_modal_relays_search_address';
        $selectors['zipcode'] = '#lpc_modal_relays_search_zipcode';
        $selectors['city'] = '#lpc_modal_relays_search_city';
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
        if ($('#lpc_modal_relays_search_zipcode').val().length && $('#lpc_modal_relays_search_city').val().length) {
            lpcLoadRelays();
        }

        $('#lpc_layer_button_search').on('click', function () {
            lpcLoadRelays();
        });
    }

    // Load relays for an address
    function lpcLoadRelays() {
        let $address = $('#lpc_modal_relays_search_address').val();
        let $zipcode = $('#lpc_modal_relays_search_zipcode').val();
        let $city = $('#lpc_modal_relays_search_city').val();

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

        const addressData = {
            address: $address,
            zipCode: $zipcode,
            city: $city,
            countryId: countryId
        };

        $.ajax({
            url: lpcPickUpSelection.ajaxURL,
            type: 'POST',
            dataType: 'json',
            data: addressData,
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
                    lpcAddRelaysOnMap(addressData);
                    lpcMapResize();
                } else {
                    $errorDiv.html(response.message);
                    $errorDiv.show();
                }
            }
        });
    }

    // Display the markers on the map
    function lpcAddRelaysOnMap(addressData) {
        // Clean old markers from the map
        if ('gmaps' === lpcPickUpSelection.mapType) {
            lpcMarkers.forEach(function (element) {
                element.setMap(null);
            });
        } else if ('leaflet' === lpcPickUpSelection.mapType) {
            lpcMarkers.forEach(function (element) {
                element.removeFrom(lpcMap);
            });
        }
        lpcMarkers.length = 0;

        let markers = $('.lpc_layer_relay');

        // No new markers
        if (markers.length === 0) {
            return;
        }

        const address = `${addressData.countryId} ${addressData.city} ${addressData.zipCode} ${addressData.address}`;
        const colissimoPositionMarker = 'https://ws.colissimo.fr/widget-colissimo/images/ionic-md-locate.svg';

        // Get the new markers and place them on the map
        if ('gmaps' === lpcPickUpSelection.mapType) {
            let bounds = new google.maps.LatLngBounds();
            const gmapsIcon = {
                url: lpcPickUpSelection.mapMarker,
                size: new google.maps.Size(36, 58),
                origin: new google.maps.Point(0, 0),
                anchor: new google.maps.Point(9, 32),
                scaledSize: new google.maps.Size(18, 32)
            };
            markers.each(function (index, element) {
                let relayPosition = new google.maps.LatLng($(element).attr('data-lpc-relay-latitude'), $(element).attr('data-lpc-relay-longitude'));

                let markerLpc = new google.maps.Marker({
                    map: lpcGoogleMap,
                    position: relayPosition,
                    title: $(this).find('.lpc_layer_relay_name').text(),
                    icon: gmapsIcon
                });

                // Add the information window on each marker
                let infowindowLpc = new google.maps.InfoWindow({
                    content: lpcGetRelayInfo($(this)),
                    pixelOffset: new google.maps.Size(-9, -5)
                });
                lpcGmapsAttachClickInfoWindow(markerLpc, infowindowLpc, index);
                lpcAttachClickChooseRelay(element);

                lpcMarkers.push(markerLpc);
                bounds.extend(relayPosition);
            });

            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({'address': address}, function (results, status) {
                if (status !== google.maps.GeocoderStatus.OK) {
                    return;
                }

                lpcMarkers.push(new google.maps.Marker({
                    map: lpcGoogleMap,
                    position: new google.maps.LatLng(results[0].geometry.location.lat(), results[0].geometry.location.lng()),
                    icon: {
                        url: colissimoPositionMarker,
                        size: new google.maps.Size(25, 25),
                        origin: new google.maps.Point(0, 0),
                        anchor: new google.maps.Point(12, 12)
                    }
                }));
            });

            lpcGoogleMap.fitBounds(bounds);
        } else if ('leaflet' === lpcPickUpSelection.mapType) {
            const markerIcon = L.icon({
                iconUrl: lpcPickUpSelection.mapMarker,
                iconSize: [
                    18,
                    32
                ],
                iconAnchor: [
                    9,
                    32
                ],
                popupAnchor: [
                    0,
                    -34
                ]
            });

            let lowestLatitude = 999;
            let lowestLongitude = 999;
            let highestLatitude = -999;
            let highestLongitude = -999;

            markers.each(function (index, element) {
                const latitude = $(element).attr('data-lpc-relay-latitude');
                const longitude = $(element).attr('data-lpc-relay-longitude');

                lowestLatitude = Math.min(latitude, lowestLatitude);
                lowestLongitude = Math.min(longitude, lowestLongitude);
                highestLatitude = Math.max(latitude, highestLatitude);
                highestLongitude = Math.max(longitude, highestLongitude);

                let marker = L.marker([
                    latitude,
                    longitude
                ], {icon: markerIcon}).addTo(lpcMap);

                // Add the information window on each marker
                marker.bindPopup(lpcGetRelayInfo($(this)));
                lpcMarkers.push(marker);
                lpcAttachClickChooseRelay(element);
            });

            $.get('https://nominatim.openstreetmap.org/search?format=json&q=' + address, function (data) {
                if (data.length === 0) {
                    return;
                }

                let addressMarker = L.marker([
                    data[0].lat,
                    data[0].lon
                ], {
                    icon: L.icon({
                        iconUrl: colissimoPositionMarker,
                        iconSize: [
                            25,
                            25
                        ],
                        iconAnchor: [
                            12,
                            12
                        ]
                    })
                }).addTo(lpcMap);
                lpcMarkers.push(addressMarker);
            });

            lpcMap.fitBounds([
                [
                    lowestLatitude,
                    lowestLongitude
                ],
                [
                    highestLatitude,
                    highestLongitude
                ]
            ]);
        }
    }

    // Create marker popup content
    function lpcGetRelayInfo(relay) {
        let indexRelay = relay.find('.lpc_relay_choose').attr('data-relayindex');

        let contentString = '<div class="info_window_lpc">';
        contentString += '<span class="lpc_store_name">' + relay.find('.lpc_layer_relay_name').text() + '</span>';
        contentString += '<span class="lpc_store_address">' + relay.find('.lpc_layer_relay_address_street').text() + '<br>' + relay.find(
            '.lpc_layer_relay_address_zipcode').text() + ' ' + relay.find('.lpc_layer_relay_address_city').text() + '</span>';
        contentString += '<span class="lpc_store_schedule">' + relay.find('.lpc_layer_relay_schedule').html() + '</span>';
        contentString += '<a href="#" class="lpc_relay_choose lpc_relay_popup_choose" data-relayindex=' + indexRelay + '>' + lpcChooseRelayText + '</a>';
        contentString += '</div>';

        return contentString;
    }

    // Add display relay detail click event
    function lpcGmapsAttachClickInfoWindow(marker, infoWindow, index) {
        // TODO: in the Gmaps documentation but addListener is deprecated
        marker.addListener('click', function () {
            lpcGmapsClickHandler(marker, infoWindow);
        });

        $('#lpc_layer_relay_' + index).click(function () {
            lpcGmapsClickHandler(marker, infoWindow);
        });
    }

    // Display details on markers
    function lpcGmapsClickHandler(marker, infoWindow) {
        if (lpcGmapsOpenedInfoWindow) {
            lpcGmapsOpenedInfoWindow.close();
            lpcGmapsOpenedInfoWindow = null;
            return;
        }

        infoWindow.open(lpcGoogleMap, marker);
        lpcGmapsOpenedInfoWindow = infoWindow;
    }

    function lpcMapResize() {
        if ('gmaps' === lpcPickUpSelection.mapType) {
            google.maps.event.trigger(lpcGoogleMap, 'resize');
        } else if ('leaflet' === lpcPickUpSelection.mapType) {
            lpcMap.invalidateSize();
        }
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
        let $errorDiv = $('#lpc_layer_error_message');
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

        if ($affectMethodDiv.length === 0) {
            $.ajax({
                url: lpcPickUpSelection.pickUpSelectionUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    lpc_pickUpInfo: relayData
                },
                success: function (response) {
                    if (response.type === 'success') {
                        $errorDiv.hide();
                        $('#lpc_pick_up_info').replaceWith(response.html);
                        $('body').trigger('update_checkout');
                    } else {
                        $errorDiv.html(response.message);
                        $errorDiv.show();
                    }
                }
            });
        } else {
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
        }

        $('.lpc-modal .modal-close').click();
    }

    window.lpcInitMapWebService = lpcInitMap;
});
