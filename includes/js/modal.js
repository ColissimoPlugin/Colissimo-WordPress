function LpcModal(options) {
    if (!options.template) {
        console.error('Could not instantiate modal without template');
    }

    const modalId = `lpc-modal${options.template}`;

    let modal = document.getElementById(modalId);

    if (!modal) {
        const template = document.getElementById(`tmpl-${options.template}`);

        if (!template) {
            console.error('Error while getting the template of the modal');
        }

        document.body.insertAdjacentHTML('beforeend', `<div id="${modalId}">${template.innerHTML}</div>`);
        modal = document.getElementById(modalId);
    } else {
        modal.style.display = 'block';
    }

    const closeButtons = modal.querySelectorAll('.modal-close');

    for (const closeButton of closeButtons) {
        closeButton.addEventListener('click', function (e) {
            e.preventDefault();
            modal.style.display = 'none';
        });
    }
}


jQuery(function ($) {
    function initLpcModal() {
        $('[data-lpc-template]').off('click').on('click', function (e) {
            e.preventDefault();

            LpcModal({
                template: $(this).attr('data-lpc-template')
            });

            if ($(this).is('[data-lpc-callback]')) {
                window[$(this).attr('data-lpc-callback')]($(this));
            }
        });
    }

    initLpcModal();
    window.initLpcModal = initLpcModal;
});
