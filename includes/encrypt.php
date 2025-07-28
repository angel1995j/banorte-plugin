<?php
require_once __DIR__ . '/../../../../wp-load.php';

if (!defined('ABSPATH')) {
    exit;
}

header('Content-Type: application/json');

// Habilitar logging para depuración
if (!function_exists('write_log')) {
    function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }
}

try {
    write_log('Iniciando proceso de encriptación Banorte');
    
    // Validar método de solicitud
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido. Se requiere POST.");
    }

    // Validar campos requeridos
    $required_fields = [
        'amount', 
        'order_id', 
        'merchant_id', 
        'terminal_id',
        'merchant_name',
        'merchant_city',
        'currency_code',
        'callback_url'
    ];
    
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception("Campos requeridos faltantes: " . implode(', ', $missing_fields));
    }
    
    write_log('Datos recibidos: ' . print_r($_POST, true));
    
    // Preparar datos para encriptación
    $data = [
        'Merchant' => sanitize_text_field($_POST['merchant_id']),
        'Terminal' => sanitize_text_field($_POST['terminal_id']),
        'Reference' => intval($_POST['order_id']),
        'Amount' => number_format(floatval($_POST['amount']), 2, '.', ''),
        'Currency' => intval($_POST['currency_code']),
        'MerchantName' => sanitize_text_field($_POST['merchant_name']),
        'MerchantCity' => sanitize_text_field($_POST['merchant_city']),
        'URLResponse' => esc_url_raw($_POST['callback_url']),
        'Version' => '1.7'
    ];
    
    $json_data = json_encode($data);
    write_log('Datos a encriptar: ' . $json_data);
    
    // Generar clave y vector de inicialización
    $key = openssl_random_pseudo_bytes(32);
    $iv = openssl_random_pseudo_bytes(16);
    
    if (!$key || !$iv) {
        throw new Exception("No se pudo generar la clave o el vector de inicialización");
    }
    
    // Encriptar los datos con AES-256-CBC
    $encrypted_data = openssl_encrypt($json_data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    
    if ($encrypted_data === false) {
        throw new Exception("Error en encriptación AES: " . openssl_error_string());
    }
    
    write_log('Datos encriptados con AES correctamente');
    
    // Cargar certificado Banorte
    $cert_path = __DIR__ . '/multicobros.cer';
    if (!file_exists($cert_path)) {
        throw new Exception("Archivo de certificado no encontrado en: " . $cert_path);
    }
    
    $cert_content = file_get_contents($cert_path);
    if (!$cert_content) {
        throw new Exception("No se pudo leer el contenido del certificado");
    }
    
    $public_key = openssl_pkey_get_public($cert_content);
    if (!$public_key) {
        throw new Exception("Clave pública inválida: " . openssl_error_string());
    }
    
    write_log('Certificado cargado correctamente');
    
    // Encriptar la clave AES con RSA
    $encrypted_key = '';
    if (!openssl_public_encrypt($key, $encrypted_key, $public_key, OPENSSL_PKCS1_PADDING)) {
        throw new Exception("Error en encriptación RSA: " . openssl_error_string());
    }
    
    write_log('Clave encriptada con RSA correctamente');
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'key' => base64_encode($encrypted_key),
        'iv' => base64_encode($iv),
        'data' => base64_encode($encrypted_data),
        'message' => 'Cadena de pago generada correctamente'
    ];
    
} catch (Exception $e) {
    write_log('Error en encrypt.php: ' . $e->getMessage());
    
    $response = [
        'success' => false,
        'message' => 'Error al generar la cadena de pago: ' . $e->getMessage()
    ];
}

write_log('Respuesta final: ' . print_r($response, true));
echo json_encode($response);
exit;