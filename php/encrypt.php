<?php
// encrypt.php: Cifrado AES-128-CTR + RSA/OAEP (compat VCE)
// Devuelve payload listo para enviar a Banorte y los parámetros para descifrado.

declare(strict_types=1);

/**
 * Cadena aleatoria segura de un alfabeto dado (para passphrase legible).
 */
function secureRandomString(int $length, string $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!*?%-_'): string {
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

/**
 * Deriva llave AES-128 a partir de passphrase + saltHex usando PBKDF2-SHA1 (1000 iteraciones).
 * @return string 16 bytes
 */
function deriveKey(string $passphrase, string $saltHex): string {
    $salt = hex2bin($saltHex);
    if ($salt === false) {
        throw new RuntimeException('Salt inválido (no es hex).');
    }
    // 16 bytes para AES-128
    return substr(hash_pbkdf2('sha1', $passphrase, $salt, 1000, 32, true), 0, 16);
}

/**
 * Cifra datos de orden para VCE.
 *
 * @param array  $orderData  Datos que Banorte espera (json sin escapar).
 * @param string $certPath   Ruta a certificado/llave pública de Banorte (PEM/X509).
 *
 * @return array{payload:string, viHex:string, saltHex:string, passphrase:string}
 *         payload  => sub1B64:::cipherB64
 *         viHex    => IV en hex (para guardar y luego descifrar la respuesta)
 *         saltHex  => salt en hex (idem)
 *         passphrase => passphrase usada en PBKDF2 (idem)
 */
function encryptForVCE(array $orderData, string $certPath): array {
    // 0) Validaciones mínimas
    if (!is_file($certPath) || !is_readable($certPath)) {
        throw new RuntimeException('No se puede leer el certificado público: ' . $certPath);
    }

    // 1) JSON sin escapar (UTF-8)
    $json = json_encode($orderData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('No se pudo codificar JSON: ' . json_last_error_msg());
    }

    // 2) Generar passphrase legible y binarios verdaderamente aleatorios para IV y salt
    $passphrase = secureRandomString(24);      // legible; puedes subir/bajar longitud
    $ivRaw      = random_bytes(16);            // AES-CTR IV: 16 bytes
    $saltRaw    = random_bytes(16);            // 16 bytes
    $viHex      = bin2hex($ivRaw);
    $saltHex    = bin2hex($saltRaw);

    // 3) Derivar llave AES-128-CTR
    $key = deriveKey($passphrase, $saltHex);

    // 4) Cifrar con AES-128-CTR (salida binaria)
    $cipherRaw = openssl_encrypt($json, 'aes-128-ctr', $key, OPENSSL_RAW_DATA, $ivRaw);
    if ($cipherRaw === false) {
        throw new RuntimeException('Error en openssl_encrypt AES-128-CTR.');
    }
    $cipherB64 = base64_encode($cipherRaw);

    // 5) Subcadena1 = viHex::saltHex::passphrase
    $sub1 = $viHex . '::' . $saltHex . '::' . $passphrase;

    // 6) Cifrar subcadena1 con RSA/OAEP (OpenSSL → OAEP con SHA-1 por defecto; compatible VCE)
    $pubKeyPem = file_get_contents($certPath);
    $pubKey    = openssl_pkey_get_public($pubKeyPem);
    if ($pubKey === false) {
        throw new RuntimeException('Certificado público inválido para openssl.');
    }

    $encSub1 = '';
    $ok = openssl_public_encrypt($sub1, $encSub1, $pubKey, OPENSSL_PKCS1_OAEP_PADDING);
    if ($ok !== true) {
        throw new RuntimeException('Fallo en openssl_public_encrypt (OAEP).');
    }
    $sub1B64 = base64_encode($encSub1);

    // 7) Resultado final: sub1B64:::cipherB64
    $payload = $sub1B64 . ':::' . $cipherB64;

    return [
        'payload'    => $payload,
        'viHex'      => $viHex,
        'saltHex'    => $saltHex,
        'passphrase' => $passphrase,
    ];
}
