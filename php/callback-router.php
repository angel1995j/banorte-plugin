<?php
// /prueba/php/callback-router.php
// Enruta el regreso de VCE, decide estado SIN heurísticas por texto
// y notifica de forma firmada (HMAC) al endpoint de WooCommerce.

session_start();
$cfg = require __DIR__ . '/bridge-config.php';
$secret     = $cfg['secret']     ?? '';
$notifyUrl  = $cfg['notify_url'] ?? '';
$preferWoo  = !empty($cfg['prefer_wc_thankyou']);
$brand      = $cfg['brand_name'] ?? 'Pago Seguro';
$logo       = $cfg['brand_logo'] ?? '';

if (!$secret || !$notifyUrl) { http_response_code(500); echo 'bridge-config.php incompleto (secret/notify_url).'; exit; }

// Contexto guardado al iniciar el flujo
$order_id    = $_SESSION['wc_order_id']   ?? '';
$order_key   = $_SESSION['wc_order_key']  ?? '';
$controlNum  = $_SESSION['wc_controlNum'] ?? '';
$amount      = $_SESSION['wc_amount']     ?? '';
$returnWC    = $_SESSION['wc_returnWC']   ?? '';
$wcCurrency  = $_SESSION['wc_currency']   ?? '';
$checkoutURL = $_SESSION['wc_checkout']   ?? '';

// Helper mínimo
$norm = function($v){ return is_string($v) ? trim($v) : (string)$v; };

// === Lectura de campos devueltos por VCE (si llegan en claro) ===
// ¡OJO! No inferimos aprobación por texto. Solo códigos duros/resultadoPayw.

// Códigos de estatus genéricos (si el proveedor los expone en claro)
$banorte_status_code = '';
foreach (['status','cd_response','code','responseCode'] as $k) {
    if (isset($_REQUEST[$k]) && $_REQUEST[$k] !== '') { $banorte_status_code = $norm($_REQUEST[$k]); break; }
}

// Posibles aliases para código de autorización y transacción
$auth_code = '';
foreach (['codigoAut','auth','authCode','authorization','codeAuth','codAut','claveAutorizacion','id_autorizacion','autCode'] as $k) {
    if (isset($_REQUEST[$k]) && $_REQUEST[$k] !== '') { $auth_code = $norm($_REQUEST[$k]); break; }
}
$txn_id = '';
foreach (['txn_id','txnid','transactionId','id_trans','idTransaccion','folioOper','folioOperacion'] as $k) {
    if (isset($_REQUEST[$k]) && $_REQUEST[$k] !== '') { $txn_id = $norm($_REQUEST[$k]); break; }
}

$message = '';
foreach (['msg','message','desc','texto'] as $k) {
    if (isset($_REQUEST[$k]) && $_REQUEST[$k] !== '') { $message = $norm($_REQUEST[$k]); break; }
}

// Campos “buenos” si tu descifrado ya los expone en la URL/POST (si no, quedan vacíos)
$resultadoPayw = isset($_REQUEST['resultadoPayw']) ? strtoupper($norm($_REQUEST['resultadoPayw'])) : '';
$referencia    = isset($_REQUEST['referencia'])    ? $norm($_REQUEST['referencia'])    : '';
$numeroControl = isset($_REQUEST['numeroControl']) ? $norm($_REQUEST['numeroControl']) : '';
$idAfiliacion  = isset($_REQUEST['idAfiliacion'])  ? $norm($_REQUEST['idAfiliacion'])  : '';
$codigoAut     = $auth_code; // alias principal para autorización
$importe       = isset($_REQUEST['importe']) ? $norm($_REQUEST['importe']) : $amount;
$texto         = isset($_REQUEST['texto'])   ? $norm($_REQUEST['texto'])   : $message;

// === Mapeo de estado SIN heurísticas por texto ===
// Regla: aprobado SOLO si el código duro lo indica.
// - Preferimos resultadoPayw=A si viene (descifrado real).
// - Respaldo: cd_response/code '00' o 'A' explícito.
// - Si no hay código de autorización ni txn_id, se fuerza failed.

$status = 'failed';

if ($resultadoPayw !== '') {
    if ($resultadoPayw === 'A') {
        $status = 'approved';
    } elseif (in_array($resultadoPayw, ['P','T','N','E','H'], true)) {
        $status = 'pending';
    } else {
        $status = 'failed';
    }
} else {
    $codeU = strtoupper($banorte_status_code);
    if ($codeU === '00' || $codeU === 'A') {
        $status = 'approved';
    } elseif (in_array($codeU, ['P','PD','PEND','H','HOLD','T','N'], true)) {
        $status = 'pending';
    } else {
        $status = 'failed';
    }
}

// Guardarraíl: si “approved” pero sin autorización/txn, entonces failed.
if ($status === 'approved' && $codigoAut === '' && $txn_id === '') {
    $status = 'failed';
}

// Notificar a Woo si hay datos mínimos
if ($order_id && $order_key) {
    $notify = [
        'order_id'       => (int)$order_id,
        'order_key'      => $order_key,
        'status'         => $status,            // legacy/readable
        'resultadoPayw'  => $resultadoPayw,     // clave para Woo
        'txn_id'         => $txn_id,
        'auth_code'      => $codigoAut,
        'message'        => $texto ?: $message,
        'controlNumber'  => $controlNum,
        'amount'         => $amount,            // total esperado por Woo (del inicio)
        'importe'        => $importe,           // monto reportado por VCE (si venía)
        'currency'       => $wcCurrency,
        'referencia'     => $referencia,
        'numeroControl'  => $numeroControl,
        'idAfiliacion'   => $idAfiliacion,
        'codigoAut'      => $codigoAut,
    ];

    $b64 = base64_encode(json_encode($notify, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $sig = hash_hmac('sha256', $b64, $secret);

    $ch = curl_init($notifyUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['payload' => $b64, 'sig' => $sig],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

// UX: preferir la “Thank You” de Woo si la tenemos
if ($preferWoo && !empty($returnWC)) {
    $dest  = $returnWC;
    $title = ($status === 'approved') ? 'Confirmando tu pago…' : (($status === 'pending') ? 'Pago en revisión…' : 'Regresando al checkout…');
    ?>
    <!doctype html>
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
        <?php if ($status === 'approved'): ?>
          <h1>¡Pago procesado!</h1>
          <p>Estamos llevando tu pedido a la página de confirmación.</p>
        <?php elseif ($status === 'pending'): ?>
          <h1>Pago en revisión</h1>
          <p>Estamos confirmando el resultado con el banco. En breve verás el estado actualizado.</p>
        <?php else: ?>
          <h1>No pudimos confirmar tu pago</h1>
          <p>Te regresaremos al checkout para que puedas intentar nuevamente.</p>
        <?php endif; ?>
        <p><a class="btn" href="<?php echo htmlspecialchars($dest, ENT_QUOTES); ?>">Continuar</a></p>
      </div></div>
      <script>setTimeout(function(){ location.href=<?php echo json_encode($dest); ?> }, 800);</script>
    </body></html>
    <?php
    exit;
}

// Si no usamos la “Thank You” de Woo, conserva tu callback de presentación propio
require __DIR__ . '/callback.php';
