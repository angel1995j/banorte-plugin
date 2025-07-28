<?php
/**
 * Plantilla de pago personalizada para Banorte
 * Ruta: /wp-content/plugins/woo-banorte-master/templates/order-pay.php
 */
defined('ABSPATH') || exit;

$order_id = absint(get_query_var('order-pay'));
$order = wc_get_order($order_id);

if (!$order) {
    echo "<h2>No se encontró la orden.</h2>";
    return;
}
?>

<h2>Pago seguro con Banorte</h2>

<p>Orden #<?= esc_html($order->get_id()); ?> - Total: $<?= esc_html($order->get_total()); ?></p>

<form id="banorte-payment-form">
    <input type="hidden" name="order_id" value="<?= esc_attr($order_id); ?>">
    <button type="submit" id="pagarBanorte" style="background:#ba0c2f;color:#fff;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;">
        Pagar con Banorte
    </button>
</form>

<div id="banorte-status" style="margin-top:20px; font-family:sans-serif;"></div>

<script>
document.getElementById("banorte-payment-form").addEventListener("submit", async function(e) {
    e.preventDefault();

    const statusDiv = document.getElementById("banorte-status");
    statusDiv.innerHTML = "Cifrando datos y contactando a Banorte...";

    try {
        const response = await fetch("<?= plugins_url('includes/encrypt.php', __FILE__) ?>", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                id: "<?= esc_attr($order->get_id()); ?>",
                total: "<?= esc_attr($order->get_total()); ?>",
                descripcion: "Pago orden #<?= esc_attr($order->get_id()); ?>",
                referencia: "<?= esc_attr($order->get_order_key()); ?>",
                email: "<?= esc_attr($order->get_billing_email()); ?>"
                // puedes agregar más campos si Banorte los requiere
            })
        });

        const result = await response.json();

        if (result.key && result.data && result.iv) {
            // Crear formulario para Banorte
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "https://vpos.inbursa.com/banorte/ventana/ventana.html"; // o URL real de ambiente producción/pruebas
            form.target = "_self";

            // Inputs
            const inputKey = document.createElement("input");
            inputKey.type = "hidden";
            inputKey.name = "KEY";
            inputKey.value = result.key;

            const inputData = document.createElement("input");
            inputData.type = "hidden";
            inputData.name = "DATA";
            inputData.value = result.data;

            const inputIV = document.createElement("input");
            inputIV.type = "hidden";
            inputIV.name = "IV";
            inputIV.value = result.iv;

            form.appendChild(inputKey);
            form.appendChild(inputData);
            form.appendChild(inputIV);

            document.body.appendChild(form);
            form.submit();
        } else {
            statusDiv.innerHTML = "Error: " + (result.error || "No se pudo generar el cifrado.");
        }
    } catch (err) {
        statusDiv.innerHTML = "Fallo de comunicación con el servidor: " + err.message;
    }
});
</script>
