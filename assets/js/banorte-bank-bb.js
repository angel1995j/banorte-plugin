jQuery(function($) {
    $(document).on('click', '#place_order', function(e) {
        if ($('input[name="payment_method"]:checked').val() !== 'banorte_bank_bb') return;

        e.preventDefault();
        
        var $form = $('form.checkout, form#order_review');
        var order_id = $form.find('#order_id').val() || typeof wc_checkout_params !== 'undefined' ? wc_checkout_params.order_id : 0;

        console.log('Enviando pago Banorte. Order ID:', order_id); // Debug

        $.ajax({
            url: banorte_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'banorte_process_payment',
                security: banorte_vars.nonce,  // Cambiado a 'security' que es el est¨¢ndar de WooCommerce
                order_id: order_id
            },
            beforeSend: function() {
                console.log('Iniciando solicitud AJAX'); // Debug
                $.blockUI({
                    message: 'Procesando pago con Banorte...',
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });
            },
            success: function(response) {
                console.log('Respuesta recibida:', response); // Debug
                if (response.success && response.data.redirect_url) {
                    window.location = response.data.redirect_url;
                } else {
                    alert(response.data || 'Error desconocido');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', status, error, xhr.responseText); // Debug detallado
                alert('Error al comunicarse con el servidor. Por favor intente nuevamente.');
            },
            complete: function() {
                $.unblockUI();
            }
        });
    });
});