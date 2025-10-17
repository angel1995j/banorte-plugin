<?php
// wsCifrado.php (mismo folder que checkout.php)
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$JAVA_URL  = 'http://127.0.0.1:8888/wsCifrado';
$CERT_PATH = __DIR__ . '/multicobros.cer'; // mismo folder

function ok($params){
  echo json_encode(['responseId'=>'100','code'=>'200','message'=>'OK','data'=>[$params]], JSON_UNESCAPED_SLASHES);
  exit;
}
function fail($msg, $http=500){
  http_response_code($http);
  echo json_encode(['responseId'=>'900','code'=>(string)$http,'message'=>$msg], JSON_UNESCAPED_SLASHES);
  exit;
}

// 1) Entrada
$in = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($in) || empty($in['base64']) || !is_string($in['base64'])) {
  fail('Solicitud inválida: {"base64":"<json en base64>"}', 400);
}

// 2) Certificado PEM (texto BEGIN CERTIFICATE...END CERTIFICATE)
if (!is_file($CERT_PATH)) fail('No se encontró certificado: '.$CERT_PATH, 500);
$certPem = file_get_contents($CERT_PATH);
if (!$certPem) fail('No se pudo leer el certificado público', 500);

// 3) Llamar al JAR (compat: pubKeyStrCert | rsaPublicKey)
$payload = [
  'base64'        => $in['base64'],
  'pubKeyStrCert' => $certPem,
  'rsaPublicKey'  => $certPem,
];

$ch = curl_init($JAVA_URL);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
  CURLOPT_TIMEOUT => 30,
]);
$res  = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$cerr = curl_error($ch);
curl_close($ch);
if ($res === false || $http !== 200) fail("Java no disponible ({$http}): {$cerr}", 502);

// 4) Interpretar respuesta del JAR y NORMALIZAR a "Sub1:::Sub2"
$j = json_decode($res, true);

// A) data[0] ya es cadena final
if (is_array($j) && ($j['code'] ?? null) === '200' && isset($j['data'][0]) && is_string($j['data'][0])) {
  $d0 = $j['data'][0];

  // A.1) "Sub1:::Sub2" directo
  if (strpos($d0, ':::') !== false && $d0[0] !== '{') {
    $params = preg_replace('/^null:::/','', $d0);
    ok($params);
  }

  // A.2) JSON string interno (como el que mostraste)
  if ($d0 && $d0[0] === '{') {
    $inner = json_decode($d0, true);

    // params -> "Sub1:::Sub2"
    if (is_array($inner) && isset($inner['params']) && is_string($inner['params']) && strpos($inner['params'],':::') !== false) {
      $params = preg_replace('/^null:::/','', $inner['params']);
      ok($params);
    }

    // data -> "Sub1:::Sub2"
    if (is_array($inner) && isset($inner['data']) && is_string($inner['data']) && strpos($inner['data'],':::') !== false) {
      $params = preg_replace('/^null:::/','', $inner['data']);
      ok($params);
    }
  }
}

// Si no se pudo normalizar, el build del JAR no es el correcto para VCE
fail('El JAR no regresó "Sub1:::Sub2" (ni en data[0] ni en params/data internos).', 502);
