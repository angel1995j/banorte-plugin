<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Payment_Banorte_Bank_BB extends WC_Payment_Gateway {
    // Declaraci¨®n expl¨ªcita de todas las propiedades
    public $merchant_id;
    public $terminal_id;
    public $merchant_name;
    public $merchant_city;
    public $currency_code;
    public $environment;
    public $encryption_url;
    
    public function __construct() {
        $this->id = 'banorte_bank';
        $this->icon = plugins_url('assets/images/banorte.png', dirname(__FILE__) . '../banorte-gateway.php');
        $this->method_title = __('Banorte', 'woo-banorte');
        $this->method_description = __('Payments with credit cards through Banorte.', 'woo-banorte');
        $this->has_fields = true;
        $this->supports = array('products');
        
        // Cargar traducciones correctamente
        add_action('init', array($this, 'load_textdomain'));
        
        $this->init_form_fields();
        $this->init_settings();
        
        // Asignar propiedades despu¨¦s de init_settings()
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->terminal_id = $this->get_option('terminal_id');
        $this->merchant_name = $this->get_option('merchant_name', get_bloginfo('name'));
        $this->merchant_city = $this->get_option('merchant_city', '');
        $this->currency_code = $this->get_option('currency_code', '484');
        $this->environment = $this->get_option('environment', 'AUT');
        $this->encryption_url = plugins_url('includes/encrypt.php', dirname(__FILE__) . '../banorte-gateway.php');
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('woo-banorte', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    // ... (resto de m¨¦todos permanecen igual pero usando las propiedades declaradas)
    
    public function payment_scripts() {
        if (!is_checkout() || !$this->is_available()) {
            return;
        }
        
        // Registrar script con la ruta correcta
        wp_register_script(
            'banorte_js',
            plugins_url('assets/js/banorte-bank-bb.js', dirname(__FILE__)),
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('banorte_js', 'banorte_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'encryption_url' => $this->encryption_url
        ));
        
        wp_enqueue_script('banorte_js');
    }
    
    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
        
        // Ruta correcta para el template
        $template_path = plugin_dir_path(__FILE__) . '../templates/payment-fields.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p>Error: No se pudo cargar el formulario de pago.</p>';
            error_log('Banorte Plugin: Template not found at ' . $template_path);
        }
    }
}