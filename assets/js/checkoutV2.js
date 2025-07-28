document.addEventListener('DOMContentLoaded', function () {
  const payButton = document.getElementById('btn-banorte-pagar');

  if (payButton) {
    payButton.addEventListener('click', function () {
      const datosBanorte = {
        idSesion: "SESSION123456",
        idUsuario: "99999999",
        idEstablecimiento: "0000000001",
        monto: "150.00",
        referencia: "ORD-789456",
        medioPago: "1",
        urlRespuesta: "https://tusitio.com/respuesta-banorte",
        urlAfiliacion: "https://tusitio.com/afiliacion-banorte",
        nombre: "Juan",
        apellidos: "Pérez López",
        correoElectronico: "juan.perez@example.com",
        numeroTelefono: "5551234567"
      };

      fetch('/wp-content/plugins/woo-banorte-master/includes/encrypt.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datosBanorte)
      })
      .then(res => res.json())
      .then(json => {
        if (json.key && json.data && json.iv) {
          // Aquí puedes redirigir al iframe o al orquestador de Banorte
          console.log("JSON cifrado recibido:", json);
          alert("JSON cifrado recibido. (reemplaza esto por redirección a Banorte)");
        } else {
          console.error("Error en cifrado:", json);
        }
      })
      .catch(err => console.error("Error de red:", err));
    });
  }
});
