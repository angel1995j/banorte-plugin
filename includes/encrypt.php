<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['error' => 'JSON no válido']);
    exit;
}

// 1. Convertir el .cer de Banorte a una clave pública para OpenSSL
$cert_path = __DIR__ . '/multicobros.cer';
$cert_content = file_get_contents($cert_path);

if (!$cert_content) {
    echo json_encode(['error' => 'No se pudo leer el archivo .cer']);
    exit;
}

$cert_parsed = openssl_x509_read($cert_content);
$pub_key = openssl_pkey_get_public($cert_parsed);

if (!$pub_key) {
    echo json_encode(['error' => 'No se pudo extraer la clave pública del .cer']);
    exit;
}

// 2. Generar clave AES
$aes_key = openssl_random_pseudo_bytes(32);
$iv = openssl_random_pseudo_bytes(16);

// 3. Cifrar la data con AES-256-CBC
$json = json_encode($data, JSON_UNESCAPED_UNICODE);
$encrypted_data = openssl_encrypt($json, 'AES-256-CBC', $aes_key, OPENSSL_RAW_DATA, $iv);

// 4. Cifrar la clave AES con RSA (clave pública de Banorte)
$encrypted_key = '';
openssl_public_encrypt($aes_key, $encrypted_key, $pub_key, OPENSSL_PKCS1_OAEP_PADDING);

// 5. Retornar todo en base64
echo json_encode([
    'key' => base64_encode($encrypted_key),
    'data' => base64_encode($encrypted_data),
    'iv' => base64_encode($iv)
]);
