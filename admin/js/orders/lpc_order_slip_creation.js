jQuery(function ($) {
    $('.lpc_slip_creation_table_header').off('click').on('click', function () {
        $(this).closest('.lpc_slip_creation_table_container').find('.lpc_slip_creation_table_listing').toggle();

        const $icon = $(this).find('.dashicons');
        $icon.toggleClass('dashicons-arrow-left-alt2 dashicons-arrow-down-alt2');
    });

    $('#colissimo_action_bordereau_selected').off('click').on('click', function () {
        $('input[name="action"]').val('bulk-slip_creation_ids');
        $('form[method="post"]').submit();
    });

    function changePaginationParamsName(type) {
        $(`#lpc_slip_creation_table_listing_${type} .pagination-links a.button[href*="&paged"]`).each(function () {
            const href = $(this).attr('href');
            // If it's the first page, we remove the paged param
            // phpcs:disable
            if ($(this).hasClass('first-page')) {
                const regex = new RegExp(`&paged_${type}=[0-9]*(&)?`);
                $(this).attr('href', href.replace(regex, ''));
            } else {
                $(this).attr('href', href.replace('&paged=', `&paged_${type}=`));
            }
            // phpcs:enable
        });
    }

    changePaginationParamsName('today');
    changePaginationParamsName('other');
});
