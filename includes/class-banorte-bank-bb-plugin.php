<?php
/**
 * Plugin Banorte para WooCommerce - Clase principal
 * Actualizado para cumplir con la guía v1.7 de Banorte
 */

class Banorte_Bank_BB_Plugin
{
    /**
     * Filepath of main plugin file.
     * @var string
     */
    public $file;
    
    /**
     * Plugin version.
     * @var string
     */
    public $version;
    
    /**
     * Absolute plugin path.
     * @var string
     */
    public $plugin_path;
    
    /**
     * Absolute plugin URL.
     * @var string
     */
    public $plugin_url;
    
    /**
     * Absolute path to plugin includes dir.
     * @var string
     */
    public $includes_path;
    
    /**
     * Flag to indicate the plugin has been boostrapped.
     * @var bool
     */
    private $_bootstrapped = false;
    
    /**
     * @var WC_Logger
     */
    public $logger;
    
    /**
     * @var string Ruta del certificado .cer
     */
    private $certificate_path;

    public function __construct($file, $version)
    {
        $this->file = $file;
        $this->version = $version;
        
        // Paths
        $this->plugin_path = trailingslashit(plugin_dir_path($this->file));
        $this->plugin_url = trailingslashit(plugin_dir_url($this->file));
        $this->includes_path = $this->plugin_path . trailingslashit('includes');
        $this->certificate_path = $this->plugin_path . 'cert/banorte.cer'; // Ajustar ruta
        
        $this->logger = new WC_Logger();
    }

    public function banorte_run()
    {
        try {
            if ($this->_bootstrapped) {
                throw new Exception(__('Banorte Woocommerce can only be initialized once', 'woo-banorte'));
            }
            
            // Verificar requisitos
            if (!extension_loaded('openssl')) {
                throw new Exception(__('OpenSSL extension is required for Banorte payments', 'woo-banorte'));
            }
            
            $this->_run();
            $this->_bootstrapped = true;
            
        } catch (Exception $e) {
            if (is_admin() && !defined('DOING_AJAX')) {
                add_action('admin_notices', function() use ($e) {
                    echo '<div class="error"><p>' . esc_html($e->getMessage()) . '</p></div>';
                });
            }
            $this->logger->add('banorte', 'Init error: ' . $e->getMessage());
        }
    }

    protected function _run()
    {
        require_once($this->includes_path . 'class-wc-payment-banorte-bank-bb.php');
        
        // Filtros y acciones
        add_filter('plugin_action_links_' . plugin_basename($this->file), array($this, 'plugin_action_links'));
        add_filter('woocommerce_payment_gateways', array($this, 'woocommerce_banorte_bank_add_gateway'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp', array($this, 'return_params_banorte_bank_bb'));
    }

    /**
     * Añade enlaces de acción al plugin
     */
    public function plugin_action_links($links)
    {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=banorte_bank') . '">' . 
                esc_html__('Settings', 'woo-banorte') . '</a>',
            '<a href="https://multicobros.banorte.com" target="_blank">' . 
                esc_html__('Documentation', 'woo-banorte') . '</a>'
        );
        return array_merge($plugin_links, $links);
    }

    /**
     * Añade el gateway a WooCommerce
     */
    public function woocommerce_banorte_bank_add_gateway($methods)
    {
        $methods[] = 'WC_Payment_Banorte_Bank_BB';
        return $methods;
    }

    /**
     * Carga scripts y estilos
     */
    public function enqueue_scripts()
    {
        if (is_checkout_pay_page()) {
            wp_enqueue_script(
                'banorte-checkout', 
                $this->plugin_url . 'assets/js/checkout.js', 
                array('jquery'), 
                $this->version, 
                true
            );
            
            wp_localize_script('banorte-checkout', 'banorte_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'environment' => $this->get_option('environment'),
                'merchant_id' => $this->get_option('merchant_id'),
                'merchant_name' => $this->get_option('merchant_name'),
                'merchant_city' => $this->get_option('merchant_city')
            ));
        }
    }

    /**
     * Procesa la respuesta de Banorte (actualizado para v1.7)
     */
    public function return_params_banorte_bank_bb()
    {
        if (!isset($_REQUEST['DATA']) || !isset($_REQUEST['controlNumber'])) {
            return;
        }

        $order_id = (int)$_REQUEST['controlNumber'];
        $order = wc_get_order($order_id);
        
        if (!$order) {
            $this->logger->add('banorte', 'Order not found: ' . $order_id);
            return;
        }

        try {
            // Descifrar la respuesta
            $response = $this->decrypt_response($_REQUEST['DATA']);
            $this->logger->add('banorte', 'Decrypted response: ' . print_r($response, true));

            // Procesar según el resultado
            switch ($response['resultadoPayw'] ?? 'E') {
                case 'A': // Aprobado
                    $order->payment_complete($response['referencia'] ?? '');
                    $order->add_order_note(sprintf(
                        __('Banorte payment approved. Auth code: %s', 'woo-banorte'),
                        $response['codigoAut'] ?? ''
                    ));
                    break;
                    
                case 'D': // Declinado
                case 'R': // Rechazado  
                case 'T': // Timeout
                case 'E': // Error
                    $order->update_status('failed', 
                        __('Payment rejected by Banorte: ', 'woo-banorte') . 
                        ($response['texto'] ?? 'Reason unknown')
                    );
                    break;
            }

            // Redireccionar
            wp_redirect($order->get_checkout_order_received_url());
            exit;

        } catch (Exception $e) {
            $this->logger->add('banorte', 'Response processing error: ' . $e->getMessage());
            wp_die(__('Payment processing error. Please contact support.', 'woo-banorte'));
        }
    }

    /**
     * Descifra la respuesta de Banorte (Anexo B)
     */
    private function decrypt_response($encrypted_data)
    {
        if (!file_exists($this->certificate_path)) {
            throw new Exception('Banorte certificate not found');
        }

        // 1. Separar Subcadena1 y Subcadena2
        $parts = explode(':::', $encrypted_data);
        if (count($parts) !== 2) {
            throw new Exception('Invalid encrypted data format');
        }

        // 2. Descifrar Subcadena1 (RSA) para obtener claves AES
        $rsa = new \phpseclib\Crypt\RSA();
        $rsa->loadKey(file_get_contents($this->certificate_path));
        $rsa->setEncryptionMode(\phpseclib\Crypt\RSA::ENCRYPTION_OAEP);
        $decrypted = $rsa->decrypt(base64_decode($parts[0]));
        
        if ($decrypted === false) {
            throw new Exception('RSA decryption failed');
        }

        // 3. Extraer componentes AES
        $components = explode('::', $decrypted);
        if (count($components) !== 3) {
            throw new Exception('Invalid AES components');
        }

        // 4. Descifrar Subcadena2 (AES)
        $cipher = new \phpseclib\Crypt\AES(\phpseclib\Crypt\AES::MODE_CTR);
        $cipher->setKeyLength(128);
        $cipher->setKey($components[2]); // passphrase
        $cipher->setIV(hex2bin($components[0])); // viHex
        
        $json_data = $cipher->decrypt(base64_decode($parts[1]));
        
        if (json_decode($json_data) === null) {
            throw new Exception('Invalid JSON response');
        }

        return json_decode($json_data, true);
    }

    /**
     * Helper para obtener opciones
     */
    private function get_option($key)
    {
        $options = get_option('woocommerce_banorte_bank_settings');
        return $options[$key] ?? null;
    }
}