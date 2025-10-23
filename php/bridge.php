<?php
// /prueba/php/bridge.php
// Recibe el payload firmado desde Woo, guarda el contexto en sesión
// y redirige al checkout local (checkout.php) para iniciar VCE.

session_start();
session_regenerate_id(true);

$cfg       = require __DIR__ . '/bridge-config.php';
$secret    = $cfg['secret']      ?? '';
$notifyUrl = $cfg['notify_url']  ?? '';
$brand     = $cfg['brand_name']  ?? 'Pago Seguro';
$logo      = $cfg['brand_logo']  ?? '';

if (!$secret || !$notifyUrl) {
  http_response_code(500);
  echo 'bridge-config.php incompleto (secret/notify_url).';
  exit;
}

// --- Validar payload firmado ---
$payloadB64 = isset($_GET['payload']) ? (string)$_GET['payload'] : '';
$sig        = isset($_GET['sig'])     ? (string)$_GET['sig']     : '';

if ($payloadB64 === '' || $sig === '') {
  http_response_code(400);
  echo 'Faltan parámetros';
  exit;
}

$calc = hash_hmac('sha256', $payloadB64, $secret);
if (!hash_equals($calc, $sig)) {
  http_response_code(403);
  echo 'Firma inválida';
  exit;
}

$data = json_decode(base64_decode($payloadB64), true);
if (!is_array($data)) {
  http_response_code(400);
  echo 'Payload inválido';
  exit;
}

// --- Normalizador/limpieza ---
$norm = function($v){ return is_string($v) ? trim($v) : (string)$v; };

// Requeridos mínimos
$order_id  = isset($data['order_id'])  ? (int)$data['order_id']  : 0;
$order_key = isset($data['order_key']) ? $norm($data['order_key']) : '';

if ($order_id <= 0 || $order_key === '') {
  http_response_code(400);
  echo 'Payload incompleto (order_id/order_key).';
  exit;
}

// Opcionales
$controlNumber = isset($data['controlNumber']) ? $norm($data['controlNumber']) : '';
if ($controlNumber === '') {
  // Genera uno sencillo si no vino (útil para cruzar con respuesta)
  $controlNumber = 'REF' . $order_id . '-' . time();
}

$amount   = isset($data['amount'])   ? $norm($data['amount'])   : '';
$currency = isset($data['currency']) ? $norm($data['currency']) : ''; // Ideal: 'MXN'. Si usas 484, conviértelo más adelante.
$returnWC = isset($data['returnWC']) ? $norm($data['returnWC']) : '';
$notify   = isset($data['notifyUrl'])? $norm($data['notifyUrl']): '';

// Asegura notify por config si no vino en payload
if ($notify === '') { $notify = $notifyUrl; }

// Guardar en sesión (las usa callback-router.php)
$_SESSION['wc_notifyUrl']   = $notify;
$_SESSION['wc_returnWC']    = $returnWC;
$_SESSION['wc_order_id']    = $order_id;
$_SESSION['wc_order_key']   = $order_key;
$_SESSION['wc_controlNum']  = $controlNumber;
$_SESSION['wc_amount']      = $amount;
$_SESSION['wc_currency']    = $currency !== '' ? $currency : 'MXN'; // por defecto MXN
// (opcional) por si quieres volver al checkout en caso de error:
if (!isset($_SESSION['wc_checkout'])) {
  $_SESSION['wc_checkout'] = '/checkout';
}

// --- Redirigir a tu checkout local de VCE ---
$dest = './checkout.php';
$query = [];
if ($_SESSION['wc_amount'] !== '')      { $query['amount']  = $_SESSION['wc_amount']; }
if ($_SESSION['wc_controlNum'] !== '')  { $query['control'] = $_SESSION['wc_controlNum']; }
if (!empty($query)) { $dest .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986); }

?>
<!doctype html>
<html lang="es"><head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Redirigiendo a Banorte…</title>
  <meta http-equiv="refresh" content="1;url=<?php echo htmlspecialchars($dest, ENT_QUOTES); ?>">
  <link rel="preconnect" href="https://multicobros.banorte.com">
  <link rel="stylesheet" href="./assets/redirect.css">
  <style>small{color:#9fb0d3}</style>
</head><body>
  <div class="wrap">
    <div class="card">
      <?php if ($logo): ?>
        <img src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($brand, ENT_QUOTES); ?>" style="height:40px;margin-bottom:12px"/>
      <?php endif; ?>
      <div class="spinner"></div>
      <h1>Estamos enviándote a Banorte…</h1>
      <p>No cierres esta ventana. Estás en <strong><?php echo htmlspecialchars($brand, ENT_QUOTES); ?></strong> y tu pago se procesará de forma segura.</p>
      <p><a class="btn" href="<?php echo htmlspecialchars($dest, ENT_QUOTES); ?>">Ir a Banorte ahora</a></p>
      <p><small>Si tienes problemas con la redirección automática, usa el botón.</small></p>
    </div>
  </div>
  <script>setTimeout(function(){ location.href=<?php echo json_encode($dest); ?> }, 800);</script>
</body></html>
