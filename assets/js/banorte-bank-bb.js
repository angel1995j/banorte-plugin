jQuery(document).ready(function($) {
    $('#banorte-submit').on('click', function() {
        const data = {
            amount: $('#banorte-amount').val(),
            order_id: $('#banorte-order-id').val(),
            merchant_id: $('#banorte-merchant-id').val(),
            terminal_id: $('#banorte-terminal-id').val(),
            merchant_name: $('#banorte-merchant-name').val(),
            merchant_city: $('#banorte-merchant-city').val(),
            currency_code: $('#banorte-currency-code').val(),
            callback_url: $('#banorte-callback-url').val()
        };

        $.post(banorte_params.encryption_url, data, function(response) {
            if (response.success) {
                // Crear formulario oculto y enviar a Banorte
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'https://via.banorte.com/secure3d/Solucion3DSecure.htm';
                form.style.display = 'none';
                
                Object.entries({
                    Merchant: data.merchant_id,
                    Reference: data.order_id,
                    Amount: data.amount,
                    Key: response.key,
                    Vector: response.iv,
                    Data: response.data
                }).forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
            } else {
                alert('Error: ' + (response.message || 'Error desconocido'));
            }
        }).fail(function() {
            alert('Error al conectar con el servidor');
        });
    });
});