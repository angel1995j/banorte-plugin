<?php
class WC_Payment_Banorte_Bank_BB extends WC_Payment_Gateway {

    private $cert_path;
    private $key_path;

    public function __construct() {
        $this->id = 'banorte_bank_bb';
        $this->method_title = 'Banorte VCE 1.7';
        $this->method_description = 'Acepta pagos con tarjetas de crédito/débito via Banorte';
        $this->has_fields = true;
        
        $this->init_form_fields();
        $this->init_settings();
        
        // Configuración básica
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->terminal_id = $this->get_option('terminal_id');
        $this->merchant_city = $this->get_option('merchant_city');
        
        // Rutas de certificados
        $this->cert_path = plugin_dir_path(__FILE__) . 'certificates/multicobros.cer';
        $this->key_path = plugin_dir_path(__FILE__) . 'certificates/llave_privada.pem';
        
        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('woocommerce_api_wc_gateway_' . $this->id, [$this, 'handle_callback']);
         add_action('wp_ajax_banorte_process_payment', [$this, 'ajax_process_payment']);
    add_action('wp_ajax_nopriv_banorte_process_payment', [$this, 'ajax_process_payment']);
    }
    
    
    public function ajax_process_payment() {
    try {
        // 1. Verificar nonce
        if (!check_ajax_referer('banorte_payment_nonce', 'security', false)) {
            throw new Exception('Solicitud no válida (nonce)');
        }

        // 2. Obtener order_id
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        
        if ($order_id === 0) {
            throw new Exception('ID de orden no válido');
        }

        // 3. Procesar pago
        $result = $this->process_payment($order_id);
        
        if (isset($result['result']) && $result['result'] === 'success') {
            wp_send_json_success([
                'redirect_url' => $result['redirect']
            ]);
        } else {
            throw new Exception('Error al procesar el pago');
        }

    } catch (Exception $e) {
        error_log('Error Banorte AJAX: ' . $e->getMessage());
        wp_send_json_error($e->getMessage(), 400);
    }
}

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Habilitar/Deshabilitar',
                'type' => 'checkbox',
                'label' => 'Habilitar Banorte VCE',
                'default' => 'yes'
            ],
            'title' => [
                'title' => 'Título',
                'type' => 'text',
                'description' => 'Nombre del método de pago',
                'default' => 'Banorte',
                'desc_tip' => true
            ],
            'merchant_id' => [
                'title' => 'Merchant ID',
                'type' => 'text',
                'required' => true
            ],
            'terminal_id' => [
                'title' => 'Terminal ID',
                'type' => 'text',
                'required' => true
            ],
            'merchant_city' => [
                'title' => 'Ciudad del Comercio',
                'type' => 'text',
                'required' => true
            ]
        ];
    }

    public function payment_scripts() {
        if (!is_checkout()) return;

        wp_enqueue_script(
            'banorte_payment_js',
            plugins_url('/assets/js/banorte-bank-bb.js', dirname(__FILE__)),
            ['jquery', 'wc-checkout'],
            '1.7.0',
            true
        );

        wp_localize_script('banorte_payment_js', 'banorte_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('banorte_payment_nonce'),
            'error_general' => 'Error al procesar el pago. Intente nuevamente.'
        ]);
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        // Validaciones críticas
        if (!$this->validate_certificates()) {
            return $this->fail_payment($order, 'Certificados no válidos');
        }

        try {
            $encrypted_data = $this->prepare_payment_data($order);
            $response = $this->send_to_banorte($encrypted_data);

            if ($response['success']) {
                $order->update_status('pending', 'Pendiente de pago Banorte');
                return [
                    'result' => 'success',
                    'redirect' => $response['redirect_url']
                ];
            } else {
                throw new Exception($response['error']);
            }
        } catch (Exception $e) {
            return $this->fail_payment($order, $e->getMessage());
        }
    }

    private function validate_certificates() {
        if (!file_exists($this->cert_path) || !file_exists($this->key_path)) {
            $this->log_error('Certificados no encontrados');
            return false;
        }
        return true;
    }

    private function prepare_payment_data($order) {
        return [
            'MerchantId' => $this->merchant_id,
            'TerminalId' => $this->terminal_id,
            'Amount' => number_format($order->get_total(), 2, '.', ''),
            'OrderId' => $order->get_id(),
            'Currency' => '484', // MXN
            'MerchantName' => get_bloginfo('name'),
            'MerchantCity' => $this->merchant_city,
            'CallbackUrl' => WC()->api_request_url('WC_Gateway_Banorte_Bank_BB')
        ];
    }

    private function log_error($message) {
        error_log('[Banorte Gateway] ' . $message);
    }
}