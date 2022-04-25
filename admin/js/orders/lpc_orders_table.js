jQuery(function ($) {
    $('#lpc__orders_listing__page__more_options--toggle--text').off('click').on('click', function () {
        $('#lpc__orders_listing__page__more_options--options').toggle();
    });

    $('#lpc__orders_listing__page__more_options--options input:checkbox').change(function () {
        let inputName = $(this).attr('name');
        let value = $(this).val();
        let $checkboxes = $('#lpc__orders_listing__page__more_options--options input:checkbox[name="' + inputName + '"]');

        if (value.length === 0) {
            $checkboxes.filter(function () {
                return this.value.length > 0;
            }).prop('checked', false);
        } else if (value.length) {
            $checkboxes.filter(function () {
                return this.value.length === 0;
            }).prop('checked', false);
        }

        let numberOfChecked = $checkboxes.filter(function () {
            return this.checked;
        }).length;

        if (numberOfChecked === 0) {
            $checkboxes.filter(function () {
                return this.value.length === 0;
            }).prop('checked', true);
        }
    });

    $('#lpc__orders_listing__page__more_options--options__bottom-actions__reset').on('click', function () {
        let $allCheckboxes = $('#lpc__orders_listing__page__more_options--options input:checkbox');
        $allCheckboxes.filter(function () {
            return this.value.length > 0;
        }).prop('checked', false);

        $allCheckboxes.filter(function () {
            return this.value.length === 0;
        }).prop('checked', true);

        $('#filter-action').click();
    });

    const $inputFile = $('#colissimo-tracking_number_import');

    $inputFile.on('change', function () {
        const formData = new FormData();
        const files = $(this)[0].files;

        formData.append($inputFile.attr('name'), files[0]);

        $.ajax({
            url: $inputFile.attr('colissimo-data-url'),
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                response = JSON.parse(response);
                if (response.type === 'error') {
                    alert(response.message);
                }
                location.reload();
            }
        });
    });

    $('#colissimo-tracking_number_import-button').off('click').on('click', function () {
        $inputFile.click();
    });
});
