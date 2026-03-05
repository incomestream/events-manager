jQuery(document).ready(function ($) {
    $('#load-more-events').on('click', function () {
        var button = $(this);
        var offset = parseInt(button.data('offset')) || 0;
        $.ajax({
            url: em_ajax.ajaxurl,
            type: 'post',
            data: {
                action: 'em_load_more',
                nonce: em_ajax.nonce,
                offset: offset
            },
            success: function (response) {
                if (response.success) {
                    $('#additional-events').append(response.data.html);
                    // Обновляем offset
                    button.data('offset', response.data.new_offset);
                } else {
                    alert('Ошибка при загрузке данных.');
                }
            }
        });
    });
});