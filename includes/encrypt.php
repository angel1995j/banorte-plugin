<?php
require_once('../../../wp-load.php');

// Configuración de seguridad
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Logging detallado
$log = function($message, $level = 'INFO') {
    $log_entry = sprintf(
        "[%s] %s: %s\n",
        date('Y-m-d H:i:s'),
        $level,
        is_string($message) ? $message : json_encode($message)
    );
    file_put_contents(__DIR__.'/banorte_payments.log', $log_entry, FILE_APPEND);
};

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Formato JSON inválido');
    }
    
    // Validar campos obligatorios
    $required_fields = [
        'MerchantId', 'TerminalId', 'Amount', 
        'OrderId', 'Currency', 'CallbackUrl'
    ];
    
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Campo requerido faltante: $field");
        }
    }
    
    // Proceso de encriptación (implementar según Banorte VCE 1.7)
    $encrypted_data = [
        'Version' => '1.7',
        'MerchantId' => sanitize_text_field($input['MerchantId']),
        'TerminalId' => sanitize_text_field($input['TerminalId']),
        'Amount' => number_format(floatval($input['Amount']), 2, '.', ''),
        'OrderId' => intval($input['OrderId']),
        'Currency' => '484',
        'Signature' => $this->generate_signature($input) // Implementar
    ];
    
    $log('Datos encriptados generados: ' . print_r($encrypted_data, true));
    
    echo json_encode([
        'success' => true,
        'data' => $encrypted_data
    ]);
    
} catch (Exception $e) {
    $log($e->getMessage(), 'ERROR');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}