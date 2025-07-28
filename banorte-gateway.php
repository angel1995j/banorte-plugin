<?php
/**
 * Plugin Name: WooCommerce Gateway Banorte VCE
 * Description: Pasarela de pago Banorte Ventana de Comercio Electr¨®nico 1.7
 * Version: 1.7.0
 * Author: Tu Nombre
 */

defined('ABSPATH') || exit;

// Carga las dependencias principales
add_action('plugins_loaded', 'init_banorte_gateway', 0);

function init_banorte_gateway() {
    if (!class_exists('WC_Payment_Gateway')) return;

    require_once __DIR__ . '/includes/class-wc-payment-banorte-bank-bb.php';
    require_once __DIR__ . '/includes/class-banorte-bank-bb-plugin.php';

    // Registrar gateway
    add_filter('woocommerce_payment_gateways', 'add_banorte_gateway');
    function add_banorte_gateway($methods) {
        $methods[] = 'WC_Payment_Banorte_Bank_BB';
        return $methods;
    }
}

add_action('init', function() {
    if (defined('WP_DEBUG') && WP_DEBUG && isset($_POST['action']) && $_POST['action'] === 'banorte_process_payment') {
        error_log('Datos recibidos en AJAX: ' . print_r($_POST, true));
        error_log('Nonce recibido: ' . $_POST['security']);
        error_log('Nonce esperado: ' . wp_create_nonce('banorte_payment_nonce'));
    }
});

// Hooks de activaci¨®n
register_activation_hook(__FILE__, ['Banorte_Bank_BB_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Banorte_Bank_BB_Plugin', 'deactivate']);