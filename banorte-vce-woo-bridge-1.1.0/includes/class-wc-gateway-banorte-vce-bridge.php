<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WC_Gateway_Banorte_VCE_Bridge extends WC_Payment_Gateway {

	/** Props to avoid PHP 8.2 dynamic property deprecations */
	public $php_checkout_url;
	public $bridge_secret;
	public $debug;

	public function __construct() {
		$this->id                 = 'banorte_vce_bridge';
		$this->method_title       = 'Banorte VCE (Bridge)';
		$this->method_description = 'Redirige al checkout externo (tu /prueba/php) y luego notifica a Woo. UX mejorada para el cliente.';
		$this->has_fields         = false;
		$this->supports           = [ 'products' ];
		$this->icon               = defined('BANORTE_VCE_WOO_BRIDGE_URL') ? ( BANORTE_VCE_WOO_BRIDGE_URL . 'assets/banorte.svg' ) : '';

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled          = $this->get_option( 'enabled', 'yes' );
		$this->title            = $this->get_option( 'title', 'Tarjeta (Banorte)' );
		$this->description      = $this->get_option( 'description', 'Serás redirigido a la plataforma segura de Banorte para completar tu pago.' );
		$this->php_checkout_url = $this->get_option( 'php_checkout_url', '' );
		$this->bridge_secret    = $this->get_option( 'bridge_secret', '' );
		$this->debug            = $this->get_option( 'debug', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		// Iniciar flujo desde checkout (redirige al bridge externo)
		add_action( 'wp_ajax_banorte_vce_wc_start',        [ $this, 'ajax_start' ] );
		add_action( 'wp_ajax_nopriv_banorte_vce_wc_start', [ $this, 'ajax_start' ] );

		// Callback del bridge externo hacia Woo (usar como callback_url)
		add_action( 'init', function() {
			add_rewrite_rule( '^wc-api/banorte_vce_bridge/?', 'index.php?wc-api=banorte_vce_bridge', 'top' );
		} );
		add_action( 'woocommerce_api_banorte_vce_bridge', [ $this, 'callback_from_bridge' ] );
	}

	public function init_form_fields() {
		$this->form_fields = [
			'enabled' => [
				'title'   => 'Activar/Desactivar',
				'type'    => 'checkbox',
				'label'   => 'Activar Banorte VCE (Bridge)',
				'default' => 'yes',
			],
			'title' => [
				'title'       => 'Título',
				'type'        => 'text',
				'description' => 'Lo verá el cliente en el checkout',
				'default'     => 'Tarjeta (Banorte)',
				'desc_tip'    => true,
			],
			'description' => [
				'title'       => 'Descripción',
				'type'        => 'textarea',
				'default'     => 'Serás redirigido a la plataforma segura de Banorte para completar tu pago.',
			],
			'php_checkout_url' => [
				'title'       => 'URL del checkout externo (bridge.php)',
				'type'        => 'text',
				'description' => 'Ej: https://tu-dominio/prueba/php/bridge.php',
				'default'     => '',
				'desc_tip'    => true,
			],
			'bridge_secret' => [
				'title'       => 'Secreto compartido (HMAC)',
				'type'        => 'password',
				'description' => 'Cadena larga y aleatoria. Debe ser la misma en tu bridge.php/callback.php',
				'default'     => '',
			],
			'debug' => [
				'title'       => 'Registro (debug)',
				'type'        => 'checkbox',
				'label'       => 'Escribir eventos en WooCommerce > Estado > Logs (banorte-vce-bridge)',
				'default'     => 'no',
			],
		];
	}

	public function is_available() {
		if ( 'yes' !== $this->enabled ) return false;
		if ( empty( $this->php_checkout_url ) || empty( $this->bridge_secret ) ) return false;
		return true;
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return [ 'result' => 'failure' ];

		$redirect = add_query_arg( [
			'action'    => 'banorte_vce_wc_start',
			'order_id'  => $order_id,
			'order_key' => $order->get_order_key(),
		], admin_url( 'admin-ajax.php' ) );

		return [
			'result'   => 'success',
			'redirect' => $redirect,
		];
	}

	/** ============ START FLOW (AJAX) ============ */
	public function ajax_start() {
		$order_id  = isset($_GET['order_id'])  ? absint($_GET['order_id'])  : 0;
		$order_key = isset($_GET['order_key']) ? wc_clean($_GET['order_key']) : '';

		if ( ! $order_id || ! $order_key ) {
			wp_die( 'Missing order params' );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_order_key() !== $order_key ) {
			wp_die( 'Invalid order' );
		}

		// Datos mínimos para el bridge externo
		$amount   = (float) $order->get_total();
		$currency = $order->get_currency();
		$ts       = time();

		$payload = [
			'order_id'     => $order_id,
			'order_key'    => $order_key,
			'amount'       => number_format( $amount, 2, '.', '' ),
			'currency'     => $currency,
			'timestamp'    => $ts,
			'callback_url' => $this->get_callback_url(), // wc-api/banorte_vce_bridge
		];

		// HMAC para integridad
		$payload['hmac'] = $this->hmac_sign( $payload, $this->bridge_secret );

		// Redirección por GET (puedes cambiar a POST si tu bridge lo requiere)
		$url = $this->php_checkout_url;
		$url = add_query_arg( array_map( 'rawurlencode', $payload ), $url );

		if ( $this->is_debug() ) {
			$this->log( 'ajax_start redirect -> ' . $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/** ============ CALLBACK FROM BRIDGE (wc-api) ============ */
	public function callback_from_bridge() {
		// El bridge externo debe invocar esta URL (GET o POST) tras descifrar DATA y decidir resultadoPayw.

		$source = ! empty($_POST) ? $_POST : $_GET;
		$get    = function($k, $def='') use($source){ return isset($source[$k]) ? wc_clean( (string) $source[$k] ) : $def; };

		$order_id    = absint( $get('order_id') );
		$order_key   = $get('order_key');
		$amount      = $get('amount');
		$currency    = $get('currency');
		$resultado   = strtoupper( $get('resultadoPayw') ); // 'A','D','R','T','N','E'
		$referencia  = $get('referencia');
		$numControl  = $get('numeroControl');
		$texto       = $get('texto');
		$codigoAut   = $get('codigoAut');
		$idAf        = $get('idAfiliacion');
		$hmac        = $get('hmac');
		$ts          = $get('timestamp');

		if ( ! $order_id || ! $order_key || ! $hmac ) {
			status_header(400);
			echo 'Missing params';
			exit;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_order_key() !== $order_key ) {
			status_header(400);
			echo 'Invalid order';
			exit;
		}

		// Verifica HMAC
		$check_array = [
			'order_id'    => (string) $order_id,
			'order_key'   => (string) $order_key,
			'amount'      => (string) $amount,
			'currency'    => (string) $currency,
			'timestamp'   => (string) $ts,
			'resultadoPayw' => (string) $resultado,
			'referencia'  => (string) $referencia,
			'numeroControl' => (string) $numControl,
			'texto'       => (string) $texto,
			'codigoAut'   => (string) $codigoAut,
			'idAfiliacion'=> (string) $idAf,
		];
		$expected = $this->hmac_sign( $check_array, $this->bridge_secret );
		if ( ! hash_equals( $expected, $hmac ) ) {
			if ( $this->is_debug() ) {
				$this->log( 'HMAC mismatch. Expected ' . $expected . ' got ' . $hmac );
			}
			status_header(400);
			echo 'HMAC error';
			exit;
		}

		// Validación de monto (si llega)
		if ( $amount !== '' ) {
			$total = number_format( (float) $order->get_total(), 2, '.', '' );
			if ( $total !== $amount ) {
				$order->update_status( 'failed', 'Banorte VCE Bridge: monto inconsistente (' . $amount . ' vs ' . $total . ')' );
				$order->add_order_note( 'Banorte VCE Bridge: Monto no coincide.' );
				$order->save();
				$this->safe_redirect_after_callback( $order, false );
			}
		}

		// Guarda metadatos de referencia SIEMPRE
		if ( $idAf )       { $order->update_meta_data( '_banorte_idAfiliacion', $idAf ); }
		if ( $referencia ) { $order->update_meta_data( '_banorte_referencia', $referencia ); }
		if ( $numControl ) { $order->update_meta_data( '_banorte_numeroControl', $numControl ); }
		if ( $resultado )  { $order->update_meta_data( '_banorte_resultadoPayw', $resultado ); }
		if ( $codigoAut )  { $order->update_meta_data( '_banorte_codigoAut', $codigoAut ); }
		if ( $texto )      { $order->update_meta_data( '_banorte_texto', $texto ); }
		$order->save();

		// Decisión final: aprobar solo si A
		if ( $resultado === 'A' ) {
			$txn = $codigoAut ? $codigoAut : 'banorte_vce';
			$order->set_transaction_id( $txn );
			$order->payment_complete( $txn );
			$order->add_order_note( 'Pago aprobado por Banorte VCE (Bridge). Ref: ' . $referencia . ' | Control: ' . $numControl . ' | Aut: ' . $txn . ' | Texto: ' . $texto );
			$order->save();
			$this->safe_redirect_after_callback( $order, true );
		} else {
			$human = $this->human_message( $resultado, $texto );
			$order->update_status( 'failed', 'Banorte VCE Bridge: ' . $human );
			$order->add_order_note( 'Pago no aprobado. Resultado: ' . $resultado . ' | Mensaje: ' . $texto );
			$order->save();
			$this->safe_redirect_after_callback( $order, false );
		}
	}

	/** Redirección segura tras callback */
	private function safe_redirect_after_callback( WC_Order $order, $approved ) {
		if ( $approved ) {
			$url = $this->get_return_url( $order ); // order received
		} else {
			// Devolver al checkout con error
			wc_add_notice( __( 'Your payment could not be processed. Please try again.' ), 'error' );
			$url = wc_get_checkout_url();
		}
		if ( $this->is_debug() ) {
			$this->log( 'Callback redirect -> ' . $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/** URL del callback wc-api */
	private function get_callback_url() {
		return home_url( '/?wc-api=banorte_vce_bridge' );
	}

	/** HMAC canónica: ordena claves y firma JSON */
	private function hmac_sign( array $data, $secret ) {
		ksort( $data );
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return hash_hmac( 'sha256', $json, $secret );
	}

	private function is_debug() {
		return ( $this->debug === 'yes' || $this->debug === 1 || $this->debug === true );
	}

	private function log( $msg ) {
		if ( ! $this->is_debug() ) return;
		if ( class_exists( 'WC_Logger' ) ) {
			$logger = wc_get_logger();
			$logger->info( $msg, [ 'source' => 'banorte-vce-bridge' ] );
		}
	}

	/** Mensaje humano para resultadoPayw */
	private function human_message( $code, $texto = '' ) {
		$map = [
			'A' => 'Aprobada',
			'D' => 'Declinada',
			'R' => 'Rechazada',
			'T' => 'Sin respuesta 3DS',
			'N' => 'No procesada por verificación',
			'E' => 'Error en la transacción',
		];
		$base = isset( $map[ $code ] ) ? $map[ $code ] : 'Resultado desconocido';
		return $texto ? ( $base . ' - ' . $texto ) : $base;
	}
}
