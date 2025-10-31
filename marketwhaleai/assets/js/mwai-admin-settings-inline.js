jQuery(document).ready(function($){
    $('#mwai-test-connection-btn').on('click', function(e){
        e.preventDefault();
        const $button = $(this);
        const $result = $('#mwai-test-connection-result');
        $result.text('Testing connection...');
        $button.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'mwai_test_connection',
            _wpnonce: MWAI_Admin_Settings_Ajax.nonce
        }, function(response){
            if (response.success) {
                $result.css('color', 'green').text('Connection successful! ' + response.data.message);
            } else {
                $result.css('color', 'red').text('Connection failed: ' + response.data.message);
            }
        }).fail(function(){
            $result.css('color', 'red').text('Network error during connection test.');
        }).always(function(){
            $button.prop('disabled', false);
        });
    });
});
