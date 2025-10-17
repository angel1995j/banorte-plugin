<?php
// /prueba/php/bridge-autopay.php
// Igual que bridge.php pero forzamos ?autopay=1&silent=1 para entrar directo al pago.
session_start();
$cfg = require __DIR__ . '/bridge-config.php';
$secret    = $cfg['secret']     ?? '';
$notifyUrl = $cfg['notify_url'] ?? '';

if (!$secret || !$notifyUrl) { http_response_code(500); echo 'bridge-config.php incompleto (secret/notify_url).'; exit; }

$payloadB64 = $_GET['payload'] ?? '';
$sig        = $_GET['sig']     ?? '';
if (!$payloadB64 || !$sig) { http_response_code(400); echo 'Faltan parámetros'; exit; }
$calc = hash_hmac('sha256', $payloadB64, $secret);
if (!hash_equals($calc, $sig)) { http_response_code(403); echo 'Firma inválida'; exit; }
$data = json_decode(base64_decode($payloadB64), true);
if (!is_array($data)) { http_response_code(400); echo 'Payload inválido'; exit; }

// Guardamos en sesión como el bridge normal
$_SESSION['wc_notifyUrl']  = $data['notifyUrl']  ?? $notifyUrl;
$_SESSION['wc_returnWC']   = $data['returnWC']   ?? '';
$_SESSION['wc_order_id']   = $data['order_id']   ?? '';
$_SESSION['wc_order_key']  = $data['order_key']  ?? '';
$_SESSION['wc_controlNum'] = $data['controlNumber'] ?? '';
$_SESSION['wc_amount']     = $data['amount']     ?? '';
$_SESSION['wc_currency']   = $data['currency']   ?? 484;

// Destino
$dest = './checkout.php';
$q = [];
if (!empty($_SESSION['wc_amount'])) { $q['amount'] = $_SESSION['wc_amount']; }
$q['autopay'] = '1';
$q['silent']  = '1';
$q['timeout'] = '6000'; // ms
if (!empty($q)) {
  $dest .= '?' . http_build_query($q);
}

// Entregar una redirección inmediata, sin intersticial
header('Location: ' . $dest, true, 302);
exit;
