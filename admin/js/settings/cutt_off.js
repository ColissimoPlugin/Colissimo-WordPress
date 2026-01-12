jQuery(function ($) {
    function initCuttOff() {
        initializeWeeklySchedule();
        handleExceptions();
        initialValues();
    }

    initCuttOff();

    function initializeWeeklySchedule() {
        // Bind change event
        $(document).on('change', '.day-select', function () {
            updateJsonOutput();
        });
    }

    function handleExceptions() {
        // Add exception
        $('#addExceptionBtn').on('click', function (event) {
            event.preventDefault();

            const exceptionItem = $('#lpc_delivery_date_exception_template').html();
            $('#exceptionsList').append(exceptionItem);

            updateJsonOutput();
        });

        // Remove exception
        $(document).on('click', '.remove-btn', function () {
            $(this).closest('.exception-item').remove();
            updateJsonOutput();
        });

        // Update JSON on exception changes
        $(document).on('change', '.exception-date, .exception-hour', function () {
            updateJsonOutput();
        });
    }

    function initialValues() {
        const jsonValues = $('#cuttOffInitialValues').val();

        if (jsonValues) {
            const data = JSON.parse(jsonValues);

            // Set weekly schedule
            for (const [weekDay, time] of Object.entries(data.weekly_schedule)) {
                $(`.day-select[data-day="${weekDay}"]`).val(time);
            }

            // Set exceptions
            if (data.exceptions && Array.isArray(data.exceptions)) {
                data.exceptions.forEach(exception => {
                    // TODO: ignore past dates?

                    const $exceptionElement = $($('#lpc_delivery_date_exception_template').html());
                    $exceptionElement.find('.exception-date').val(exception.date);
                    $exceptionElement.find('.exception-hour').val(exception.hour);
                    $('#exceptionsList').append($exceptionElement);
                });
            }
        }

        updateJsonOutput();
    }

    function updateJsonOutput() {
        const schedule = {};
        const days = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday'
        ];

        // Collect weekly schedule
        days.forEach(day => {
            schedule[day] = $(`.day-select[data-day="${day}"]`).val();
        });

        // Collect exceptions
        const exceptionsList = [];
        $('.exception-item').each(function () {
            const date = $(this).find('.exception-date').val();
            const hour = $(this).find('.exception-hour').val();
            if (date && hour) {
                exceptionsList.push({
                    date: date,
                    hour: hour
                });
            }
        });

        const finalData = {
            weekly_schedule: schedule,
            exceptions: exceptionsList
        };

        // Update hidden input
        $('#scheduleData').val(JSON.stringify(finalData));
    }
});
