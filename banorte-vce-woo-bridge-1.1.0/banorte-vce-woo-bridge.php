<?php
/**
 * Plugin Name: Banorte VCE Woo Bridge
 * Description: Pasarela WooCommerce que redirige al checkout externo ( /prueba/php) y recibe la notificación para cerrar el pedido. Incluye mejoras UX.
 * Version: 1.2.1
 * Author: La carcacha
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.2
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BANORTE_VCE_WOO_BRIDGE_VER', '1.2.1' );
define( 'BANORTE_VCE_WOO_BRIDGE_FILE', __FILE__ );
define( 'BANORTE_VCE_WOO_BRIDGE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BANORTE_VCE_WOO_BRIDGE_URL', plugin_dir_url( __FILE__ ) );

// Declarar compatibilidad HPOS (High-Performance Order Storage)
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});

// Dependencia básica: WooCommerce
add_action( 'plugins_loaded', function(){
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function(){
			echo '<div class="notice notice-error"><p><strong>Banorte VCE Woo Bridge</strong> requiere <em>WooCommerce</em> activo.</p></div>';
		} );
		return;
	}

	// Registrar gateway
	add_filter( 'woocommerce_payment_gateways', function( $gws ){
		require_once BANORTE_VCE_WOO_BRIDGE_DIR . 'includes/class-wc-gateway-banorte-vce-bridge.php';
		$gws[] = 'WC_Gateway_Banorte_VCE_Bridge';
		return $gws;
	} );
}, 11 );

// ==== AJAX: start (redirigir al bridge externo) ====
add_action('wp_ajax_nopriv_banorte_vce_wc_start', 'banorte_vce_wc_start');
add_action('wp_ajax_banorte_vce_wc_start',        'banorte_vce_wc_start');

function banorte_vce_wc_start() {
	if ( ! class_exists('WC_Order') ) { status_header(500); echo 'WooCommerce requerido'; exit; }

	$settings = get_option('woocommerce_banorte_vce_bridge_settings', []);
	$phpUrl   = isset($settings['php_checkout_url']) ? trim($settings['php_checkout_url']) : '';
	$secret   = isset($settings['bridge_secret'])    ? (string)$settings['bridge_secret']    : '';

	if ( empty($phpUrl) || empty($secret) ) {
		status_header(500); echo 'Bridge mal configurado (php_checkout_url / bridge_secret)'; exit;
	}

	$order_id  = absint($_REQUEST['order_id'] ?? 0);
	$order_key = sanitize_text_field($_REQUEST['order_key'] ?? '');
	if ( ! $order_id || ! $order_key ) { status_header(400); echo 'Faltan parámetros'; exit; }

	$order = wc_get_order($order_id);
	if ( ! $order || $order->get_order_key() !== $order_key ) { status_header(403); echo 'Orden no válida'; exit; }

	$amount    = number_format((float)$order->get_total(), 2, '.', '');
	$currency  = $order->get_currency(); // Mantén el ISO de Woo; si tu bridge requiere 484, conviértelo allá.
	$control   = $order_id . '-' . time();
	$returnWC  = $order->get_checkout_order_received_url();
	$notifyUrl = admin_url('admin-ajax.php?action=banorte_vce_wc_notify');

	$payload = [
		'order_id'      => $order_id,
		'order_key'     => $order_key,
		'amount'        => $amount,
		'currency'      => $currency,
		'controlNumber' => $control,
		'returnWC'      => $returnWC,
		'notifyUrl'     => $notifyUrl,
	];

	$b64 = base64_encode( wp_json_encode( $payload ) );
	$sig = hash_hmac('sha256', $b64, $secret);

	$dest = add_query_arg( [
		'bridge'  => '1',
		'payload' => rawurlencode($b64),
		'sig'     => $sig,
	], $phpUrl );

	// Intersticial amigable: pantalla de transición con spinner mientras redirige
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="1;url=' . esc_attr( $dest ) . '">';
	echo '<meta name="viewport" content="width=device-width,initial-scale=1"><title>Redirigiendo a Banorte…</title>';
	echo '<link rel="preconnect" href="https://multicobros.banorte.com">';
	$css = '';
	$css_file = BANORTE_VCE_WOO_BRIDGE_DIR . 'assets/redirect.css';
	if ( file_exists( $css_file ) && is_readable( $css_file ) ) {
		$css = @file_get_contents( $css_file );
	}
	echo '<style>'. $css .'</style>';
	echo '</head><body><div class="wrap"><div class="card"><div class="spinner"></div><h1>Estamos enviándote a Banorte…</h1><p>No cierres esta ventana. Si no avanzamos en unos segundos, usa el botón:</p><p><a class="btn" href="'.esc_url( $dest ).'">Ir a Banorte ahora</a></p></div></div><script>setTimeout(function(){location.href='.json_encode($dest).'},800);</script></body></html>';
	exit;
}

// ==== AJAX: notify (regreso desde bridge externo) ====
add_action('wp_ajax_nopriv_banorte_vce_wc_notify','banorte_vce_wc_notify');
add_action('wp_ajax_banorte_vce_wc_notify',       'banorte_vce_wc_notify');

function banorte_vce_wc_notify() {
	if ( ! class_exists('WC_Order') ) { status_header(500); echo 'WooCommerce requerido'; exit; }

	$settings = get_option('woocommerce_banorte_vce_bridge_settings', []);
	$secret   = isset($settings['bridge_secret']) ? (string)$settings['bridge_secret'] : '';
	$debug    = ! empty($settings['debug']);

	if ( empty($secret) ) { status_header(500); echo 'Bridge mal configurado (bridge_secret)'; exit; }

	$b64 = $_POST['payload'] ?? '';
	$sig = $_POST['sig']     ?? '';
	if ( empty($b64) || empty($sig) ) { status_header(400); echo 'Faltan parámetros'; exit; }

	$calc = hash_hmac('sha256', $b64, $secret);
	if ( ! hash_equals($calc, $sig) ) { status_header(403); echo 'Firma inválida'; exit; }

	$data = json_decode(base64_decode($b64), true);
	if ( ! is_array($data) ) { status_header(400); echo 'Payload inválido'; exit; }

	$order_id   = absint($data['order_id'] ?? 0);
	$order_key  = sanitize_text_field($data['order_key'] ?? '');
	$order      = wc_get_order($order_id);

	if ( ! $order || $order->get_order_key() !== $order_key ) {
		status_header(404); echo 'Orden no encontrada'; exit;
	}

	// --------- Datos que esperamos del bridge tras descifrar DATA ----------
	// PRIORIDAD: resultadoPayw (A/D/R/T/N/E). 'status' queda como respaldo.
	$resultado  = strtoupper( sanitize_text_field( $data['resultadoPayw'] ?? '' ) );
	$status_in  = (string)($data['status'] ?? '');      // respaldo legacy
	$status     = strtolower(trim($status_in));
	$amount_in  = isset($data['amount']) ? (string)$data['amount'] : '';
	$importe_in = isset($data['importe']) ? (string)$data['importe'] : ''; // [HARDEN] soporta nombre 'importe'
	$currency   = isset($data['currency']) ? (string)$data['currency'] : '';
	$txn_id     = sanitize_text_field($data['txn_id'] ?? '');
	$auth_code  = sanitize_text_field($data['codigoAut'] ?? $data['auth_code'] ?? '');
	$referencia = sanitize_text_field($data['referencia'] ?? '');
	$numControl = sanitize_text_field($data['numeroControl'] ?? '');
	$idAf       = sanitize_text_field($data['idAfiliacion'] ?? '');
	$texto      = sanitize_text_field($data['texto'] ?? $data['message'] ?? '');

	// --------- Guarda referencias SIEMPRE ----------
	if ( $idAf )       { $order->update_meta_data('_banorte_idAfiliacion', $idAf); }
	if ( $referencia ) { $order->update_meta_data('_banorte_referencia',   $referencia); }
	if ( $numControl ) { $order->update_meta_data('_banorte_numeroControl', $numControl); }
	if ( $resultado )  { $order->update_meta_data('_banorte_resultadoPayw', $resultado); }
	if ( $auth_code )  { $order->update_meta_data('_banorte_codigoAut',     $auth_code); }
	if ( $texto )      { $order->update_meta_data('_banorte_texto',         $texto); }
	if ( $txn_id )     { $order->update_meta_data('_banorte_txn_id',        $txn_id); }
	if ( ! empty($data['controlNumber']) ) {
		$order->update_meta_data('_banorte_control', sanitize_text_field($data['controlNumber']));
	}
	$order->save();

	$logger = ( $debug && class_exists('WC_Logger') ) ? wc_get_logger() : null;

	// --------- VALIDACIÓN DE MONTO (si llega) ----------
	if ( $amount_in !== '' ) {
		$total_wc = number_format( (float) $order->get_total(), 2, '.', '' );
		if ( $total_wc !== (string) $amount_in ) {
			$order->update_status('failed', 'Banorte VCE Bridge: monto inconsistente (' . $amount_in . ' vs ' . $total_wc . ')' );
			$order->add_order_note('Banorte VCE Bridge: Monto no coincide.');
			if ( $logger ) { $logger->warning( 'Monto inconsistente para orden '.$order_id, ['source'=>'banorte-vce-bridge'] ); }
			wp_send_json_success( ['updated'=>'failed', 'reason'=>'amount_mismatch'] );
		}
	}

	// [HARDEN] Validar 'importe' si llega (algunas integraciones lo usan en lugar de 'amount')
	if ( $importe_in !== '' ) {
		$total_wc = number_format( (float) $order->get_total(), 2, '.', '' );
		if ( $total_wc !== (string) $importe_in ) {
			$order->update_status('failed', 'Banorte VCE Bridge: importe inconsistente (' . $importe_in . ' vs ' . $total_wc . ')' );
			$order->add_order_note('Banorte VCE Bridge: Importe no coincide.');
			if ( $logger ) { $logger->warning( 'Importe inconsistente para orden '.$order_id, ['source'=>'banorte-vce-bridge'] ); }
			wp_send_json_success( ['updated'=>'failed', 'reason'=>'importe_mismatch'] );
		}
	}

	// --------- DECISIÓN FINAL ----------
	// 1) Si existe resultadoPayw, manda él (exigimos A + autorización presente)
	if ( $resultado !== '' ) {

		// [HARDEN] Exigir código de autorización o txn_id y (opcionalmente) que importe/amount ya haya pasado arriba
		$has_auth = ( $auth_code !== '' ) || ( $txn_id !== '' );

		if ( $resultado === 'A' && $has_auth ) {
			$txn = $auth_code ?: $txn_id;
			if ( $txn ) {
				$order->set_transaction_id( $txn );
			}
			$order->payment_complete( $txn ?: 'banorte_' . time() );
			$order->add_order_note( 'Pago aprobado por Banorte (Bridge). Ref: ' . $referencia . ' | Control: ' . $numControl . ' | Aut: ' . ( $txn ?: $auth_code ) . ' | Texto: ' . $texto );
			if ( $logger ) { $logger->info( 'Aprobada por resultadoPayw=A para orden ' . $order_id, ['source'=>'banorte-vce-bridge'] ); }
			wp_send_json_success( ['updated'=>'processing/completed'] );
		} else {
			// Cualquier cosa distinta a A o sin auth => fallido
			$human = banorte_vce_bridge_human_message( $resultado, $texto );
			$msg   = 'Banorte VCE Bridge: ' . $human;
			if ( $resultado === 'A' && ! $has_auth ) {
				$msg .= ' (sin código de autorización/txn_id)';
			}
			$order->update_status( 'failed', $msg );
			$order->add_order_note( 'Pago no aprobado. Resultado: ' . $resultado . ' | Mensaje: ' . $texto );
			if ( $logger ) { $logger->warning( 'No aprobada (resultadoPayw='.$resultado.') para orden ' . $order_id, ['source'=>'banorte-vce-bridge'] ); }
			wp_send_json_success( ['updated'=>'failed', 'reason'=> ($resultado === 'A' && ! $has_auth) ? 'missing_auth' : ('resultadoPayw_'.$resultado) ] );
		}
	}

	// 2) Respaldo legacy: usar "status" si el bridge aún no envía resultadoPayw
	$approved_aliases = ['approved','success','ok','00','a','aprobado'];
	if ( $status !== '' ) {
		if ( in_array( $status, $approved_aliases, true ) ) {
			// [HARDEN-LEGACY] también exigir auth en legacy si está disponible
			$txn = $auth_code ?: $txn_id;
			if ( $txn ) {
				$order->set_transaction_id( $txn );
			}
			$order->payment_complete( $txn ?: 'banorte_' . time() );
			$order->add_order_note( 'Banorte aprobado (legacy status='. $status .'). Auth: ' . ( $auth_code ?: $txn_id ?: 'N/A' ) . ' ' . $texto );
			if ( $logger ) { $logger->info( 'Aprobada por status legacy ('.$status.') para orden ' . $order_id, ['source'=>'banorte-vce-bridge'] ); }
			wp_send_json_success( ['updated'=>'processing/completed', 'mode'=>'legacy'] );
		} elseif ( in_array( $status, ['pending','hold','on-hold'], true ) ) {
			$order->update_status('on-hold', 'Banorte en espera. ' . $texto);
			if ( $logger ) { $logger->info( 'On-hold (legacy) para orden ' . $order_id, ['source'=>'banorte-vce-bridge'] ); }
			wp_send_json_success( ['updated'=>'on-hold', 'mode'=>'legacy' ] );
		} else {
			$order->update_status('failed', 'Banorte rechazado/cancelado. ' . $texto . ' (status=' . $status . ')' );
			if ( $logger ) { $logger->warning( 'Fallida (legacy status='.$status.') para orden ' . $order_id, ['source'=>'banorte-vce-bridge'] ); }
			wp_send_json_success( ['updated'=>'failed', 'mode'=>'legacy' ] );
		}
	}

	// Si no llegó ni resultadoPayw ni status, no podemos aprobar
	$order->update_status('failed', 'Banorte VCE Bridge: notificación incompleta (sin resultadoPayw/status).');
	if ( isset($logger) && $logger ) { $logger->error( 'Notificación incompleta para orden ' . $order_id, ['source'=>'banorte-vce-bridge'] ); }
	wp_send_json_success( ['updated'=>'failed', 'reason'=>'missing_result'] );
}

/**
 * Mensaje humano para códigos de resultadoPayw
 */
function banorte_vce_bridge_human_message( $code, $texto = '' ) {
	$map = [
		'A' => 'Aprobada',
		'D' => 'Declinada',
		'R' => 'Rechazada',
		'T' => 'Sin respuesta 3DS',
		'N' => 'No procesada por verificación',
		'E' => 'Error en la transacción',
	];
	$base = isset($map[$code]) ? $map[$code] : 'Resultado desconocido';
	return $texto ? ( $base . ' - ' . $texto ) : $base;
}
