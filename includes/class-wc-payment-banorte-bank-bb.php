<?php
class WC_Payment_Banorte_Bank_BB extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'banorte_bank';
        $this->icon = banorte_bank_bb()->plugin_url . 'assets/images/banorte.png';
        $this->method_title = __('Banorte', 'woo-banorte');
        $this->method_description = __('Payments with credit cards, VISA and Mastercard debit through Banorte.', 'woo-banorte');
        $this->description  = $this->get_option('description');
        $this->order_button_text = __('Continue to payment', 'woo-banorte');
        $this->has_fields = false;
        $this->supports = array('products');
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title');
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'order_received_message'), 10, 2);
        add_action('woocommerce_available_payment_gateways', array(&$this, 'disable_payment_if_options_empty'), 20);
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woo-banorte'),
                'type' => 'checkbox',
                'label' => __('Enable Banorte', 'woo-banorte'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woo-banorte'),
                'type' => 'text',
                'description' => __('Title that the user sees during checkout.', 'woo-banorte'),
                'default' => __('Banorte Woocommerce', 'woo-banorte'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woo-banorte'),
                'type' => 'textarea',
                'description' => __('Description shown during checkout.', 'woo-banorte'),
                'default' => __('Payments with credit cards, VISA and Mastercard debit through Banorte.', 'woo-banorte'),
                'desc_tip' => true,
            ),
            'merchant_id' => array(
                'title' => __('MERCHANT_ID', 'woo-banorte'),
                'type' => 'text',
                'description' => __('Affiliation number assigned by Banorte.', 'woo-banorte'),
                'desc_tip' => true,
                'default' => '',
            ),
            'user' => array(
                'title' => __('USER', 'woo-banorte'),
                'type' => 'text',
                'description' => __('User assigned by Banorte.', 'woo-banorte'),
                'desc_tip' => true,
                'default' => '',
            ),
            'password' => array(
                'title' => __('PASSWORD', 'woo-banorte'),
                'type' => 'password',
                'description' => __('Password assigned by Banorte.', 'woo-banorte'),
                'desc_tip' => true,
                'default' => '',
            ),
            'terminal_id' => array(
                'title' => __('TERMINAL_ID', 'woo-banorte'),
                'type' => 'text',
                'description' => __('Terminal ID registered with Banorte.', 'woo-banorte'),
                'desc_tip' => true,
                'default' => '',
            ),
            'environment' => array(
                'title' => __('Environment', 'woo-banorte'),
                'type' => 'select',
                'description' => __('Choose transaction environment.', 'woo-banorte'),
                'desc_tip' => true,
                'default' => 'AUT',
                'options' => array(
                    'PRD' => __('Production', 'woo-banorte'),
                    'AUT' => __('Authorize (Test)', 'woo-banorte'),
                    'DEC' => __('Decline (Test)', 'woo-banorte'),
                    'RND' => __('Random (Test)', 'woo-banorte')
                )
            ),
        );
    }

    public function admin_options()
    {
        echo '<h3>' . esc_html($this->title) . '</h3>';
        echo '<p>' . esc_html($this->method_description) . '</p>';
        echo '<table class="form-table">';
        $this->check_options();
        $this->generate_settings_html();
        echo '</table>';
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $order->reduce_order_stock();
        WC()->cart->empty_cart();
        return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true));
    }

    public function receipt_page($order_id)
    {
        $order = wc_get_order($order_id);
        echo $this->generate_banorte_bank_form($order);
    }

    public function generate_banorte_bank_form($order)
    {
        $amount = number_format($order->get_total(), 2, '.', '');
        $order_id = $order->get_id();
        $merchant_id = $this->get_option('merchant_id');
        $terminal_id = $this->get_option('terminal_id');
        $env = $this->get_option('environment');
        ?>
        <div id="card-banorte-vce"></div>
        <script src="https://multicobros.banorte.com/orquestador/resources/js/jquery-3.3.1.js"></script>
        <script src="https://multicobros.banorte.com/orquestador/lightbox/checkoutV2.js"></script>
        <script>
            Payment.setEnv("<?php echo esc_js($env); ?>");

            const json = {
                merchantId: "<?php echo esc_js($merchant_id); ?>",
                terminalId: "<?php echo esc_js($terminal_id); ?>",
                amount: "<?php echo esc_js($amount); ?>",
                orderId: "<?php echo esc_js($order_id); ?>",
                redirectUrl: "<?php echo esc_url(home_url('/')); ?>",
                cancelUrl: "<?php echo esc_url(home_url('/')); ?>",
                paymentType: "sale",
                language: "es"
            };

            fetch("<?php echo plugins_url('encrypt.php', __FILE__); ?>", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(json)
            })
            .then(res => res.json())
            .then(data => {
                if (data.Params) {
                    Payment.startPayment({ Params: data.Params });
                } else {
                    alert("Error al generar la cadena de pago.");
                }
            });
        </script>
        <?php
    }

    public function order_received_message($text, $order)
    {
        if (!empty($_GET['msg'])) {
            return $text . ' ' . sanitize_text_field($_GET['msg']);
        }
        return $text;
    }

    public function restore_order_stock($order_id)
    {
        $order = wc_get_order($order_id);
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id()) {
                $_product = $item->get_product();
                if ($_product && $_product->managing_stock()) {
                    $qty = $item->get_quantity();
                    $_product->increase_stock($qty);
                }
            }
        }
    }

    public function disable_payment_if_options_empty($availableGateways)
    {
        if (!$this->get_is_valid_options() && isset($availableGateways[$this->id])) {
            unset($availableGateways[$this->id]);
        }
        return $availableGateways;
    }

    public function check_options()
    {
        if (!$this->get_is_valid_options()) {
            do_action('notices_action_tag_banorte_bank_bb', __('Banorte Woocommerce: All fields are required', 'woo-banorte'));
        }
    }

    public function get_is_valid_options()
    {
        return $this->get_option('merchant_id') && $this->get_option('user') && $this->get_option('password') && $this->get_option('terminal_id');
    }
    
    public function payment_fields() {
    echo '<p>Pagar con tarjeta a través de Banorte</p>';
    echo '<button type="button" id="btn-banorte-pagar" class="button alt">Pagar con Banorte</button>';
   }



}
