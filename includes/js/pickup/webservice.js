var lpcGoogleMap, lpcMap, lpcMarkers = [], lpcMapOpenedInfoWindow, lpcChooseRelayText, $affectMethodDiv;
const coordinates = {
    latitude: 48.866667,
    longitude: 2.333333
};
const lowestCoordinates = {
    lowestLatitude: 999,
    lowestLongitude: 999,
    highestLatitude: -999,
    highestLongitude: -999
};

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

    function initButtonSwitchMobileLayout() {
        const mapContainer = document.getElementById('lpc_left');
        const button = document.getElementById('lpc_layer_relay_switch_mobile');
        const article = document.querySelector('.lpc-lib-modal-article');

        if (!button) {
            return;
        }

        const classList = 'dashicons-editor-ul';
        const classMap = 'dashicons-location-alt';

        button.addEventListener('click', function () {
            mapContainer.classList.toggle('lpc_mobile_display_none');
            button.querySelector('span').classList.toggle(classList);
            button.querySelector('span').classList.toggle(classMap);
            article.scrollTop = 0;

            lpcMapResize();
            if (lpcPickUpSelection.mapType === 'leaflet') {
                lpcMap.fitBounds([
                    [
                        lowestCoordinates.lowestLatitude,
                        lowestCoordinates.lowestLongitude
                    ],
                    [
                        lowestCoordinates.highestLatitude,
                        lowestCoordinates.highestLongitude
                    ]
                ]);
            }
        });
    }

    // Function called when the popup is opened to initialize the Gmap
    function lpcInitMap(origin) {
        $affectMethodDiv = $(origin).closest('.lpc_order_affect_available_methods');

        // Center the map on the client's position
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (position) {
                if (lpcPickUpSelection.mapType === 'gmaps') {
                    initialLocation = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
                    lpcGoogleMap.setCenter(initialLocation);
                } else if (lpcPickUpSelection.mapType === 'leaflet') {
                    coordinates.latitude = position.coords.latitude;
                    coordinates.longitude = position.coords.longitude;
                }
            });
        }

        if (lpcPickUpSelection.mapType === 'gmaps') {
            lpcGoogleMap = new google.maps.Map(document.getElementById('lpc_map'), {
                zoom: 10,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                center: {
                    lat: coordinates.latitude,
                    lng: coordinates.longitude
                },
                disableDefaultUI: true
            });
        } else if (lpcPickUpSelection.mapType === 'leaflet') {
            lpcMap = L.map('lpc_map').setView([
                coordinates.latitude,
                coordinates.longitude
            ], 14);
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

        $('#lpc_modal_relays_display_more').on('click', function () {
            lpcLoadRelays(true);
        });
        initButtonSwitchMobileLayout();
    }

    // Load relays for an address
    function lpcLoadRelays(loadMore = false) {
        const address = $('#lpc_modal_relays_search_address').val();
        const zipCode = $('#lpc_modal_relays_search_zipcode').val();
        const city = $('#lpc_modal_relays_search_city').val();
        let countryId = $('#lpc_modal_relays_country_id').val();

        const $errorDiv = $('#lpc_layer_error_message');
        const $listRelaysDiv = $('#lpc_layer_list_relays');

        const $loader = $('#lpc_layer_relays_loader');
        const orderId = $('#lpc_layer_order_id').val();

        if ('' === countryId || undefined === countryId) {
            countryId = $('#shipping_country').val();
        }

        if ('' === countryId || undefined === countryId) {
            countryId = 'FR';
        }

        const addressData = {
            address,
            zipCode,
            city,
            countryId,
            loadMore: loadMore ? 1 : 0,
            orderId: orderId
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
                    lpcChooseRelayText = response.chooseRelayText;
                    lpcAddRelaysOnMap(addressData);
                    lpcMapResize();
                    setDisplayHours();

                    const $loadMoreButton = $('#lpc_modal_relays_display_more');

                    if (response.loadMore && $loadMoreButton.length !== 0) {
                        $loadMoreButton.hide();
                    } else {
                        $loadMoreButton.show();
                    }

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

            markers.each(function (index, element) {
                const latitude = $(element).attr('data-lpc-relay-latitude');
                const longitude = $(element).attr('data-lpc-relay-longitude');

                lowestCoordinates.lowestLatitude = Math.min(latitude, lowestCoordinates.lowestLatitude);
                lowestCoordinates.lowestLongitude = Math.min(longitude, lowestCoordinates.lowestLongitude);
                lowestCoordinates.highestLatitude = Math.max(latitude, lowestCoordinates.highestLatitude);
                lowestCoordinates.highestLongitude = Math.max(longitude, lowestCoordinates.highestLongitude);

                let marker = L.marker([
                    latitude,
                    longitude
                ], {icon: markerIcon}).addTo(lpcMap);

                // Add the information window on each marker
                marker.bindPopup(lpcGetRelayInfo($(this)));
                lpcMarkers.push(marker);
                lpcLeafletAttachClickInfoWindow(marker, index);
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
                    lowestCoordinates.lowestLatitude,
                    lowestCoordinates.lowestLongitude
                ],
                [
                    lowestCoordinates.highestLatitude,
                    lowestCoordinates.highestLongitude
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
        contentString += '<button href="#" type="button" class="lpc_relay_choose lpc_relay_popup_choose" data-relayindex='
                         + indexRelay
                         + '>'
                         + lpcChooseRelayText
                         + '</button>';
        contentString += '</div>';

        return contentString;
    }

    // Add display relay detail click event
    function lpcGmapsAttachClickInfoWindow(marker, infoWindow, index) {
        marker.addEventListener('click', function () {
            lpcGmapsClickHandler(marker, infoWindow);
        });

        $('#lpc_layer_relay_' + index + ' .lpc_show_relay_details').click(function () {
            lpcGmapsClickHandler(marker, infoWindow);
        });
    }

    // Display details on markers
    function lpcGmapsClickHandler(marker, infoWindow) {
        // Display map if we are in list only display
        displayMapOnDisplayRelayDetails();

        // Display or hide relay info
        if (lpcMapOpenedInfoWindow) {
            lpcMapOpenedInfoWindow.close();
            lpcMapOpenedInfoWindow = null;
            return;
        }
        infoWindow.open(lpcGoogleMap, marker);
        lpcMapOpenedInfoWindow = infoWindow;
    }

    // Add display relay detail click event
    function lpcLeafletAttachClickInfoWindow(marker, index) {
        marker.on('click', function () {
            lpcLeafletClickHandler(marker);
        });
        $('#lpc_layer_relay_' + index + ' .lpc_show_relay_details').on('click', function () {
            lpcLeafletClickHandler(marker);
        });
    }

    // Display details on markers
    function lpcLeafletClickHandler(marker) {
        // Display map if we are in list only display
        displayMapOnDisplayRelayDetails();

        // Display or hide relay info
        if (lpcMapOpenedInfoWindow) {
            let tmpId = lpcMapOpenedInfoWindow._leaflet_id;
            lpcMapOpenedInfoWindow.closePopup();
            lpcMapOpenedInfoWindow = null;
            if (marker._leaflet_id === tmpId) {
                return;
            }
        }
        marker.openPopup();
        lpcMapOpenedInfoWindow = marker;
    }

    function lpcMapResize() {
        if ('gmaps' === lpcPickUpSelection.mapType) {
            google.maps.event.trigger(lpcGoogleMap, 'resize');
        } else if ('leaflet' === lpcPickUpSelection.mapType) {
            lpcMap.invalidateSize();
        }
    }

    // Display the map again
    function displayMapOnDisplayRelayDetails() {
        const button = document.getElementById('lpc_layer_relay_switch_mobile');
        const classList = 'dashicons-editor-ul';
        const classMap = 'dashicons-location-alt';
        const article = document.querySelector('.lpc-lib-modal-article');
        if (button && button.querySelector('span').classList.contains(classMap)) {
            // If list mode, display the map
            const mapContainer = document.getElementById('lpc_left');
            mapContainer.classList.toggle('lpc_mobile_display_none');
            button.querySelector('span').classList.toggle(classList);
            button.querySelector('span').classList.toggle(classMap);

            lpcMapResize();
            if (lpcPickUpSelection.mapType === 'leaflet') {
                lpcMap.fitBounds([
                    [
                        lowestCoordinates.lowestLatitude,
                        lowestCoordinates.lowestLongitude
                    ],
                    [
                        lowestCoordinates.highestLatitude,
                        lowestCoordinates.highestLongitude
                    ]
                ]);
            }
        }
        if (button) {
            article.scrollTop = 0;
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
        let lpcRelayDistanceTmp = relayClicked.find('.lpc_layer_relay_distance_value').text();

        let relayHours = {
            horairesOuvertureLundi: relayClicked.find('.lpc_layer_relay_hour_monday').text(),
            horairesOuvertureMardi: relayClicked.find('.lpc_layer_relay_hour_tuesday').text(),
            horairesOuvertureMercredi: relayClicked.find('.lpc_layer_relay_hour_wednesday').text(),
            horairesOuvertureJeudi: relayClicked.find('.lpc_layer_relay_hour_thursday').text(),
            horairesOuvertureVendredi: relayClicked.find('.lpc_layer_relay_hour_friday').text(),
            horairesOuvertureSamedi: relayClicked.find('.lpc_layer_relay_hour_saturday').text(),
            horairesOuvertureDimanche: relayClicked.find('.lpc_layer_relay_hour_sunday').text()
        };

        lpcChooseRelay(
            lpcRelayIdTmp,
            lpcRelayNameTmp,
            lpcRelayAddressTmp,
            lpcRelayZipcodeTmp,
            lpcRelayCityTmp,
            lpcRelayTypeTmp,
            lpcRelayCountryTmp,
            relayClicked,
            lpcRelayDistanceTmp,
            relayHours
        );
    }

    function lpcChooseRelay(lpcRelayId, lpcRelayName, lpcRelayAddress, lpcRelayZipcode, lpcRelayCity, lpcRelayTypeTmp, lpcRelayCountry, relayClicked, lpcRelayDistanceTmp, relayHours) {
        let $errorDiv = $('#lpc_layer_error_message');
        let relayData = {
            identifiant: lpcRelayId,
            nom: lpcRelayName,
            adresse1: lpcRelayAddress,
            codePostal: lpcRelayZipcode,
            localite: lpcRelayCity,
            libellePays: lpcRelayCountry,
            typeDePoint: lpcRelayTypeTmp,
            codePays: relayClicked.attr('data-lpc-relay-country_code'),
            distanceEnMetre: lpcRelayDistanceTmp,
            horairesOuvertureLundi: relayHours.horairesOuvertureLundi,
            horairesOuvertureMardi: relayHours.horairesOuvertureMardi,
            horairesOuvertureMercredi: relayHours.horairesOuvertureMercredi,
            horairesOuvertureJeudi: relayHours.horairesOuvertureJeudi,
            horairesOuvertureVendredi: relayHours.horairesOuvertureVendredi,
            horairesOuvertureSamedi: relayHours.horairesOuvertureSamedi,
            horairesOuvertureDimanche: relayHours.horairesOuvertureDimanche
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
                        if (window.lpcBlockChangeContent) {
                            window.lpcBlockChangeContent(response.html);
                        }
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

        $('.lpc-modal .modal-close').trigger('click');
    }

    function setDisplayHours() {
        $('.lpc_layer_relay_hours_header').on('click', function () {
            $(this).closest('.lpc_layer_relay_display_hours').find('.lpc_layer_relay_hours_details').toggle();
            $(this)
                .closest('.lpc_layer_relay_display_hours')
                .find('.lpc_layer_relay_hours_icon')
                .toggleClass('lpc_layer_relay_hours_icon_down lpc_layer_relay_hours_icon_up');
        });
    }

    window.lpcInitMapWebService = lpcInitMap;
});
