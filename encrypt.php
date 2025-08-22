<?php
// encrypt.php: Cifrado AES-128-CTR + RSA/OAEP

function generateRandomString($length, $chars) {
    $str = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[random_int(0, $max)];
    }
    return $str;
}

function deriveKey($passphrase, $saltHex) {
    $salt = hex2bin($saltHex);
    // PBKDF2 SHA1, 1000 iteraciones → key 16 bytes
    return substr(hash_pbkdf2('sha1', $passphrase, $salt, 1000, 32, true), 0, 16);
}

function encryptForVCE(array $orderData, string $certPath): string {
    // 1) JSON sin escapar
    $json = json_encode($orderData, JSON_UNESCAPED_SLASHES);

    // 2) Generar passphrase, IV y salt
    $passphrase = generateRandomString(16, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!*?%-_');
    $ivRaw      = generateRandomString(16, '0123456789abcdefghijklmnopqrstuvwxyz');
    $saltRaw    = generateRandomString(16, '0123456789abcdefghijklmnopqrstuvwxyz');
    $ivHex      = bin2hex($ivRaw);
    $saltHex    = bin2hex($saltRaw);

    // 3) Derivar llave AES-128-CTR
    $key = deriveKey($passphrase, $saltHex);

    // 4) Cifrar con AES-128-CTR
    $cipher = openssl_encrypt($json, 'aes-128-ctr', $key, OPENSSL_RAW_DATA, $ivRaw);
    $cipherB64 = base64_encode($cipher);

    // 5) Subcadena1 = ivHex::saltHex::passphrase
    $sub1 = "{$ivHex}::{$saltHex}::{$passphrase}";

    // 6) Cifrar subcadena1 con RSA/OAEP SHA-256
    $pubKey = file_get_contents($certPath);
    $res    = openssl_pkey_get_public($pubKey);
    openssl_public_encrypt($sub1, $encSub1, $res, OPENSSL_PKCS1_OAEP_PADDING);
    $sub1B64 = base64_encode($encSub1);

    // 7) Resultado final: sub1B64:::cipherB64
    return "{$sub1B64}:::{$cipherB64}";
}
