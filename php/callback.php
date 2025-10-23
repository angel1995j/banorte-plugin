<?php
// /prueba/php/callback.php
// Esta vista NO decide estado ni notifica a Woo. Solo UX/redirect.
// Todo el procesamiento y notificación firmada se hace en callback-router.php

session_start();
$cfg = require __DIR__ . '/bridge-config.php';

$preferWoo = !empty($cfg['prefer_wc_thankyou']); // si quieres usar thankyou de Woo
$brand     = $cfg['brand_name'] ?? 'Pago Seguro';
$logo      = $cfg['brand_logo'] ?? '';

$order_id   = $_SESSION['wc_order_id']  ?? '';
$order_key  = $_SESSION['wc_order_key'] ?? '';
$returnWC   = $_SESSION['wc_returnWC']  ?? '';
$checkoutURL= $_SESSION['wc_checkout']  ?? ''; // si lo guardas; opcional

// ⚠️ Blindaje: NO confiar en querystring tipo ?status=A
// Ignoramos totalmente cualquier $_GET/$_POST de estado aquí.

// Si no hay contexto de sesión mínimo, regresamos al checkout (o home)
if (!$order_id || !$order_key) {
  $fallback = $checkoutURL ?: '/';
  header('Location: ' . $fallback, true, 302);
  exit;
}

// Preferir la thankyou de Woo si está disponible
if ($preferWoo && !empty($returnWC)) {
  $dest  = $returnWC;
  $title = 'Confirmando tu pago…'; // neutro, no afirma éxito
  ?><!doctype html>
  <html lang="es"><head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title><?php echo htmlspecialchars($title, ENT_QUOTES); ?></title>
    <meta http-equiv="refresh" content="1;url=<?php echo htmlspecialchars($dest, ENT_QUOTES); ?>">
    <link rel="stylesheet" href="./assets/redirect.css">
  </head><body>
    <div class="wrap"><div class="card">
      <?php if ($logo): ?>
        <img src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($brand, ENT_QUOTES); ?>" style="height:40px;margin-bottom:12px"/>
      <?php endif; ?>
      <div class="spinner"></div>
      <h1>Volviendo a la tienda…</h1>
      <p>Estamos confirmando el resultado con el banco y actualizando tu pedido.</p>
      <p><a class="btn" href="<?php echo htmlspecialchars($dest, ENT_QUOTES); ?>">Continuar</a></p>
    </div></div>
    <script>setTimeout(function(){ location.href=<?php echo json_encode($dest); ?> }, 800);</script>
  </body></html><?php
  exit;
}

// Si no hay returnWC, manda al checkout como respaldo
$fallback = $checkoutURL ?: '/';
header('Location: ' . $fallback, true, 302);
exit;
