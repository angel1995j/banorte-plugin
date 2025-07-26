<?php
/**
 * WC_Payment_Banorte_Bank_BB - Gateway de pago Banorte para WooCommerce
 * Actualizado para cumplir con la guía v1.7 de Banorte
 */

class WC_Payment_Banorte_Bank_BB extends WC_Payment_Gateway
{
    /**
     * URL de producción de Banorte
     * @var string
     */
    public $production_url = 'https://via.pagosbanorte.com/payw2';
    
    /**
     * URL de pruebas de Banorte
     * @var string
     */
    public $test_url = 'https://via.pagosbanorte.com/payw2';
    
    /**
     * Constructor del gateway
     */
    public function __construct()
    {
        $this->id = 'banorte_bank';
        $this->icon = apply_filters('woo_banorte_icon', plugins_url('assets/images/banorte.png', dirname(__FILE__)));
        $this->method_title = __('Banorte', 'woo-banorte');
        $this->method_description = __('Accept credit and debit card payments (Visa, Mastercard, American Express) through Banorte payment gateway', 'woo-banorte');
        $this->has_fields = false;
        $this->supports = array('products', 'refunds');
        
        // Cargar configuración
        $this->init_form_fields();
        $this->init_settings();
        
        // Definir variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->merchant_name = $this->get_option('merchant_name');
        $this->merchant_city = $this->get_option('merchant_city');
        $this->user = $this->get_option('user');
        $this->password = $this->get_option('password');
        $this->terminal_id = $this->get_option('terminal_id');
        $this->environment = $this->get_option('environment', 'PRD');
        $this->debug = $this->get_option('debug', 'no');
        
        // Acciones
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_filter('woocommerce_available_payment_gateways', array($this, 'disable_gateway_if_invalid'));
        
        // Logger
        $this->logger = wc_get_logger();
    }

    /**
     * Campos del formulario de configuración
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woo-banorte'),
                'type' => 'checkbox',
                'label' => __('Enable Banorte Gateway', 'woo-banorte'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woo-banorte'),
                'type' => 'text',
                'description' => __('Payment method title that customers will see', 'woo-banorte'),
                'default' => __('Credit/Debit Card (Banorte)', 'woo-banorte'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woo-banorte'),
                'type' => 'textarea',
                'description' => __('Payment method description that customers will see', 'woo-banorte'),
                'default' => __('Pay securely with your credit or debit card through Banorte payment gateway.', 'woo-banorte'),
                'desc_tip' => true,
            ),
            'merchant_id' => array(
                'title' => __('Merchant ID', 'woo-banorte'),
                'type' => 'text',
                'description' => __('Your Banorte merchant ID provided by the bank', 'woo-banorte'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'required' => 'required'
                )
            ),
            'merchant_name' => array(
                'title' => __('Merchant Name', 'woo-banorte'),
                'type' => 'text',
                'description' => __('Your registered business name with Banorte', 'woo-banorte'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'required' => 'required'
                )
            ),
            'merchant_city' => array(
                'title' => __('Merchant City', 'woo-banorte'),
                'type' => 'text',
                'description' => __('City where your business is registered', 'woo-banorte'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'required' => 'required'
                )
            ),
            'user' => array(
                'title' => __('API User', 'woo-banorte'),
                'type' => 'text',
                'description' => __('Your Banorte API username', 'woo-banorte'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'required' => 'required'
                )
            ),
            'password' => array(
                'title' => __('API Password', 'woo-banorte'),
                'type' => 'password',
                'description' => __('Your Banorte API password', 'woo-banorte'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'required' => 'required'
                )
            ),
            'terminal_id' => array(
                'title' => __('Terminal ID', 'woo-banorte'),
                'type' => 'text',
                'description' => __('Your terminal ID provided by Banorte', 'woo-banorte'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'required' => 'required'
                )
            ),
            'environment' => array(
                'title' => __('Environment', 'woo-banorte'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => __('Select the processing environment', 'woo-banorte'),
                'default' => 'PRD',
                'options' => array(
                    'PRD' => __('Production', 'woo-banorte'),
                    'AUT' => __('Test - Always approve', 'woo-banorte'),
                    'DEC' => __('Test - Always decline', 'woo-banorte'),
                    'RND' => __('Test - Random response', 'woo-banorte')
                ),
                'desc_tip' => true,
            ),
            'debug' => array(
                'title' => __('Debug Log', 'woo-banorte'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'woo-banorte'),
                'default' => 'no',
                'description' => sprintf(__('Log Banorte events, inside %s', 'woo-banorte'), '<code>' . WC_Log_Handler_File::get_log_file_path('banorte') . '</code>'),
            ),
        );
    }

    /**
     * Procesar el pago
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        
        // Validar configuración
        if (!$this->validate_settings()) {
            $this->log('Error: Invalid gateway configuration');
            wc_add_notice(__('Payment error: Invalid gateway configuration', 'woo-banorte'), 'error');
            return false;
        }
        
        // Reducir stock
        wc_reduce_stock_levels($order_id);
        
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    /**
     * Mostrar formulario de pago en la página de confirmación
     */
    public function receipt_page($order_id)
    {
        $order = wc_get_order($order_id);
        echo $this->generate_payment_form($order);
    }

    /**
     * Generar formulario de pago
     */
    protected function generate_payment_form($order)
    {
        // Obtener datos del cliente
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name = $order->get_billing_last_name();
        $billing_email = $order->get_billing_email();
        $billing_phone = $order->get_billing_phone();
        
        // Preparar datos para Banorte según guía v1.7
        $transaction_data = array(
            'merchantId' => $this->merchant_id,
            'name' => $this->user,
            'password' => $this->password,
            'mode' => $this->environment,
            'controlNumber' => $order->get_id(),
            'terminalId' => $this->terminal_id,
            'amount' => number_format($order->get_total(), 2, '.', ''),
            'merchantName' => $this->merchant_name,
            'merchantCity' => $this->merchant_city,
            'lang' => 'ES',
            'billToFirstName' => $billing_first_name,
            'billToLastName' => $billing_last_name,
            'billToEmail' => $billing_email,
            'billToPhoneNumber' => $billing_phone,
            'billToIpAddress' => $this->get_client_ip(),
            // Campos adicionales requeridos por Cybersource
            'billToStreet' => $order->get_billing_address_1(),
            'billToStreetNumber' => $this->extract_street_number($order->get_billing_address_1()),
            'billToStreet2Col' => $order->get_billing_city(),
            'billToStreet2Del' => $order->get_billing_state(),
            'billToCity' => $order->get_billing_city(),
            'billToState' => $order->get_billing_state(),
            'billToCountry' => $order->get_billing_country(),
            'billToPostalCode' => $order->get_billing_postcode()
        );
        
        // Cifrar datos según Anexo B
        $encrypted_data = $this->encrypt_data($transaction_data);
        
        if (!$encrypted_data) {
            $this->log('Error: Failed to encrypt transaction data');
            return __('Payment processing error. Please try again.', 'woo-banorte');
        }
        
        // Incluir JavaScript de Banorte
        wp_enqueue_script('banorte-checkout', 'https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js', array(), null, true);
        
        // Mostrar formulario
        ob_start();
        ?>
        <div id="banorte-payment-form">
            <p class="banorte-description"><?php echo esc_html($this->description); ?></p>
            <div id="banorte-errors" class="woocommerce-error" style="display:none;"></div>
            
            <script>
                jQuery(document).ready(function($) {
                    // Configurar entorno
                    Payment.setEnv("<?php echo $this->environment === 'PRD' ? 'pro' : 'test'; ?>");
                    
                    // Iniciar pago cuando el formulario se envíe
                    $('#banorte-submit').on('click', function(e) {
                        e.preventDefault();
                        
                        // Mostrar cargando
                        $('#banorte-payment-form').block({
                            message: null,
                            overlayCSS: {
                                background: '#fff',
                                opacity: 0.6
                            }
                        });
                        
                        // Iniciar pago
                        Payment.startPayment({
                            Params: "<?php echo $encrypted_data; ?>",
                            onClosed: function(response) {
                                $('#banorte-payment-form').unblock();
                            },
                            onError: function(response) {
                                $('#banorte-errors').text('Error: ' + response.message).show();
                                $('#banorte-payment-form').unblock();
                            },
                            onSuccess: function(response) {
                                window.location.href = "<?php echo esc_url($order->get_checkout_order_received_url()); ?>";
                            },
                            onCancel: function(response) {
                                $('#banorte-errors').text('Payment was cancelled').show();
                                $('#banorte-payment-form').unblock();
                            }
                        });
                    });
                });
            </script>
            
            <button id="banorte-submit" class="button alt">
                <?php echo __('Pay with Banorte', 'woo-banorte'); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Cifrar datos según Anexo B
     */
    protected function encrypt_data($data)
    {
        try {
            require_once dirname(__FILE__) . '/../includes/class-banorte-encryption.php';
            $encryptor = new Banorte_Encryption();
            
            // Convertir a JSON
            $json_data = json_encode($data);
            
            // Cifrar según guía v1.7
            return $encryptor->encrypt($json_data);
            
        } catch (Exception $e) {
            $this->log('Encryption error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Página de agradecimiento
     */
    public function thankyou_page($order_id)
    {
        $order = wc_get_order($order_id);
        
        if ($order->get_payment_method() === $this->id) {
            echo '<p>' . __('Thank you for your payment. We will process your order shortly.', 'woo-banorte') . '</p>';
        }
    }

    /**
     * Validar configuración
     */
    protected function validate_settings()
    {
        return !empty($this->merchant_id) && 
               !empty($this->user) && 
               !empty($this->password) && 
               !empty($this->terminal_id) &&
               !empty($this->merchant_name) &&
               !empty($this->merchant_city);
    }

    /**
     * Deshabilitar gateway si configuración es inválida
     */
    public function disable_gateway_if_invalid($available_gateways)
    {
        if (isset($available_gateways[$this->id]) && !$this->validate_settings()) {
            unset($available_gateways[$this->id]);
        }
        return $available_gateways;
    }

    /**
     * Obtener IP del cliente
     */
    protected function get_client_ip()
    {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = '127.0.0.1';
            
        return $ipaddress;
    }

    /**
     * Extraer número de dirección
     */
    protected function extract_street_number($address)
    {
        preg_match('/\d+/', $address, $matches);
        return isset($matches[0]) ? $matches[0] : '0';
    }

    /**
     * Registrar mensajes en el log
     */
    protected function log($message)
    {
        if ($this->debug === 'yes') {
            $this->logger->debug($message, array('source' => 'banorte'));
        }
    }
}