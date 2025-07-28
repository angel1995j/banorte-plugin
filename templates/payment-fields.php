<?php
if (!defined('ABSPATH')) {
    exit;
}

$amount = $order->get_total();
$callback_url = home_url('/wc-api/wc_gateway_banorte_bank');
?>

<?php if (!defined('ABSPATH')) exit; ?>
<div id="banorte-payment-form">
    <input type="hidden" id="banorte-amount" value="<?php echo esc_attr($order->get_total()); ?>">
    <input type="hidden" id="banorte-order-id" value="<?php echo esc_attr($order->get_id()); ?>">
    <input type="hidden" id="banorte-merchant-id" value="<?php echo esc_attr($this->merchant_id); ?>">
    <input type="hidden" id="banorte-terminal-id" value="<?php echo esc_attr($this->terminal_id); ?>">
    <input type="hidden" id="banorte-merchant-name" value="<?php echo esc_attr($this->merchant_name); ?>">
    <input type="hidden" id="banorte-merchant-city" value="<?php echo esc_attr($this->merchant_city); ?>">
    <input type="hidden" id="banorte-currency-code" value="<?php echo esc_attr($this->currency_code); ?>">
    <input type="hidden" id="banorte-callback-url" value="<?php echo esc_url(WC()->api_request_url('WC_Gateway_Banorte_Bank_BB'))); ?>">
    
    <button type="button" id="banorte-submit" class="button alt">
        <?php esc_html_e('Pagar con Banorte', 'woo-banorte'); ?>
    </button>
</div>