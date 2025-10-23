<?php
// checkout.php

// 1) Cargar configuración
$config = require __DIR__ . '/config.php';

// 2) Elegir el script del lightbox según el ambiente
$endpointJs = ($config['mode'] === 'PRD')
  ? ($config['endpointJs_PRD'] ?? $config['endpointJs'] ?? '')
  : ($config['endpointJs_AUT'] ?? $config['endpointJs'] ?? '');

// 3) Armar datos de la orden
$order = [
  // Enviar ambas variantes por compatibilidad
  //'affiliateId'   => $config['affiliateId'],
  'merchantId'    => $config['affiliateId'],   // alias del mismo id

  // Usuario: ambas variantes
  //'user'          => $config['user'],
  'name'          => $config['user'],

  'password'      => $config['password'],
  'mode'          => strtoupper($config['mode']), // AUT o PRD
  'controlNumber' => 'REF' . time(),
  'terminalId'    => $config['terminalId'],

  // Usa ?amount= si viene; si no, 1.00 (para que lo que se muestra sea igual a lo que se envía)
  'amount'        => isset($_GET['amount']) ? (string)$_GET['amount'] : '1.00',

  'merchantName'  => $config['merchantName'],
  'merchantCity'  => $config['merchantCity'],
  'lang'          => $config['lang'],
];

// 4) Normalizar credenciales
//$order['user']     = trim((string)$order['user']);
$order['name']     = trim((string)$order['name']);
$order['password'] = trim((string)$order['password']);
$order['mode']     = strtoupper(trim((string)$order['mode']));


// === Campos opcionales de referencia (cliente / pedido) ===
$refs = [
  'customerRef1' => $_GET['order_id']      ?? '',
  'customerRef2' => $_GET['email']         ?? '',
  'customerRef3' => $_GET['phone']         ?? '',
  'customerRef4' => $_GET['customer_name'] ?? '',
  'customerRef5' => $_GET['note']          ?? ''
];

// Filtrar caracteres no permitidos y limitar longitud
foreach ($refs as $k => $v) {
  $v = preg_replace('/[^A-Za-z0-9_\-\s@\.]/', '', trim((string)$v)); // solo texto seguro
  if ($v === '') $v = 'NA'; // Banorte no acepta vacíos
  $order[$k] = substr($v, 0, 30); // longitudes seguras (máx según manual)
}



// 5) Copia para debug (no imprimir password real)
$orderForDebug              = $order;
$orderForDebug['password']  = str_repeat('*', max(3, min(12, strlen($order['password']))));
$amountForView              = $order['amount'];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Pagar con Banorte (Lightbox)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- jQuery primero (si ya la cargas en tu tema, puedes omitir esto) -->
  <?php if (!empty($config['jqueryJs'])): ?>
    <script src="<?= htmlspecialchars($config['jqueryJs'], ENT_QUOTES) ?>"></script>
  <?php endif; ?>

  <!-- Script del lightbox según ambiente (como en la versión que funcionaba) -->
  <?php if (!empty($endpointJs)): ?>
    <script src="<?= htmlspecialchars($endpointJs, ENT_QUOTES) ?>"></script>
  <?php else: ?>
    <script>console.error("No se definió endpointJs para el ambiente seleccionado.");</script>
  <?php endif; ?>

  <style>
    :root { color-scheme: light dark; }
    body {
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      padding: 24px; background: #000;
    }
    .wrap {
      max-width: 880px; margin: 0 auto; padding-top: 7%;
      background: #fff; padding-bottom: 7%; border-radius: 20px;
    }
    h1 { text-align:center; margin: 11px 0 10px; font-size:22px; font-weight:800 }
    p.intro { color:#6b7280; margin:4px 0 2px; font-size:14px; text-align:center }
    .amount {
      font-size:35px; font-weight:900; margin: 23px 12px 31px;
      text-align: center; color:#111827
    }
    .row { display: grid; gap: 16px; grid-template-columns: 1fr; justify-items:center; margin-top:10px }
    button {
      display: inline-flex; align-items: center; gap: 10px;
      padding: 19px 27px; border-radius: 12px; background: #2bb673; color: #001d10;
      border: none; cursor: pointer; font-weight: 800; font-size: 22px;
      box-shadow: 0 10px 30px rgba(0,0,0,.25);
    }
    button:hover { filter: brightness(1.05); }
    #spin {
      display:none; width:20px; height:20px; border-radius:50%;
      border:3px solid rgba(255,255,255,.25); border-top-color:#fff; animation:spin .9s linear infinite
    }
    @keyframes spin{to{transform:rotate(360deg)}}

    /* ===== Modal Fallback (si no hay plugin) ===== */
    #paymentFrameWrapper._fallback { display: none; }
    #paymentFrameWrapper._fallback.is-open { display: block; }
    #paymentFrameWrapper._fallback .backdrop {
      position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 9998;
    }
    #paymentFrameWrapper._fallback .dialog {
      position: fixed; inset: 5% 10%; background: #fff; border-radius: 10px; z-index: 9999;
      display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,.25);
    }
    #paymentFrameWrapper._fallback header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 10px 14px; border-bottom: 1px solid #eee; background: #fafafa;
    }
    #paymentFrameWrapper._fallback header .title { font-weight: 600; }
    #paymentFrameWrapper._fallback header .close {
      border: 0; background: transparent; font-size: 22px; cursor: pointer; line-height: 1;
    }
    #paymentFrame { width: 100%; height: calc(100vh - 20% - 50px); border: 0; background: #fff; }

    /* Ocultar completamente el panel de debug pero mantenerlo en DOM para no romper dbg() */
    .debug-wrap { display:none !important; }
    #dbg { display:none !important; }
  </style>

  <script>
/** ================== Constantes Banorte ================== **/
const BANORTE_HOST = 'https://multicobros.banorte.com';
const BANORTE_V2   = BANORTE_HOST + '/orquestador/V2';

/** ================== Forzar ambiente del SDK ================== **/
// Dejamos 'pro' como en tu versión que sí funcionaba.
document.addEventListener('DOMContentLoaded', function(){
  if (window.Payment && typeof Payment.setEnv === 'function') {
    try { Payment.setEnv('pro'); console.log('[VCE] Payment.setEnv: pro'); } catch(e){}
  }
});

/** ================== Modal fallback + SHIM de .modal() ================== **/
(function ensureModal(){
  function openFallback()  { var w = document.getElementById('paymentFrameWrapper'); if (w) { w.classList.add('is-open'); } }
  function closeFallback() { var w = document.getElementById('paymentFrameWrapper'); if (w) { w.classList.remove('is-open'); } }

  var wrapperEl = null;
  document.addEventListener('DOMContentLoaded', function(){
    wrapperEl = document.getElementById('paymentFrameWrapper');
    if (wrapperEl && !wrapperEl.classList.contains('_fallback')) {
      wrapperEl.classList.add('_fallback');
    }
  });

  if (!window.jQuery || !jQuery.fn || !jQuery.fn.modal) {
    window.jQuery = window.jQuery || function(sel){ return [document.querySelector(sel)]; };
    jQuery.fn = jQuery.fn || {};
    jQuery.fn.modal = function(arg){
      var el = (this && this[0]) ? this[0] : (wrapperEl || document.getElementById('paymentFrameWrapper'));
      if (!el) return this;
      if (typeof arg === 'string') {
        if (arg === 'show') openFallback();
        if (arg === 'hide') closeFallback();
      } else {
        if (arg && arg.onShow) { try { arg.onShow(); } catch(_){} }
        openFallback();
      }
      return this;
    };
  }

  document.addEventListener('DOMContentLoaded', function(){
    var el = document.getElementById('paymentFrameWrapper');
    if (el && typeof el.modal !== 'function') {
      el.modal = function(arg){
        if (typeof arg === 'string') {
          if (arg === 'show') openFallback();
          if (arg === 'hide') closeFallback();
        } else {
          openFallback();
        }
      };
    }
  });

  window.$modal = {
    show: function(){ openFallback(); },
    hide: function(){ closeFallback(); }
  };
})();

/** ================== Interceptores para evitar pestañas nuevas ================== **/

// 1) Interceptar window.open hacia /orquestador/V2 y forzarlo al iframe/modal
(function hijackWindowOpen(){
  const originalOpen = window.open;
  window.open = function(url, name, specs){
    try {
      const u = String(url || '');
      if (/\/orquestador\/V2(\b|\/|\?|#|$)/.test(u)) {
        const frame = document.getElementById('paymentFrame');
        const targetName = name || frame.name || 'paymentFrame';
        frame.name = targetName;

        window.$modal.show();
        console.log('[VCE] window.open capturado:', { url: u, target: targetName, specs });

        try { return frame.contentWindow; } catch(_) {}
      }
    } catch(_) {}

    return originalOpen.apply(window, arguments);
  };
})();

// 2) Forzar cualquier submit (event o submit() directo) a /orquestador/V2 -> iframe
(function forceFormToIframe(){
  const ABS_V2 = BANORTE_V2;
  const frame  = () => document.getElementById('paymentFrame');

  function isV2(action){ return /\/orquestador\/V2(\b|\/|\?|#|$)/.test(String(action||'')); }

  function routeFormToIframe(form, reason){
    const f = frame();
    if (!f) return;

    f.name = f.name || 'paymentFrame';
    form.method = 'POST';
    form.action = ABS_V2;     // convertir cualquier ruta relativa a absoluta
    form.target = f.name;

    window.$modal.show();
    console.log('[VCE] form->V2 capturado ('+reason+'):', {
      action: form.action, method: form.method, target: form.target
    });
  }

  document.addEventListener('submit', function(ev){
    const form = ev.target;
    if (form && form.tagName === 'FORM' && isV2(form.action)) {
      routeFormToIframe(form, 'event');
    }
  }, true);

  const _submit = HTMLFormElement.prototype.submit;
  HTMLFormElement.prototype.submit = function(){
    try { if (isV2(this.action)) routeFormToIframe(this, 'proto'); } catch(_) {}
    return _submit.apply(this, arguments);
  };
})();

/** ================== Sondas de diagnóstico (debug oculto) ================== **/
(function(){
  if (!window.jQuery) return;
  const _ajax = jQuery.ajax;
  jQuery.ajax = function(opts){
    try {
      if (opts && typeof opts.url === 'string' && opts.url.indexOf('authenticateV2') !== -1) {
        console.log('[VCE] authenticateV2 via $.ajax:', {
          url: opts.url,
          data: opts.data,
          async: opts.async,
          headers: opts.headers,
          crossDomain: opts.crossDomain
        });
      }
    } catch(_){}
    return _ajax.apply(this, arguments);
  };

  jQuery(document).ajaxError(function(_e, jqxhr, settings){
    const info = {
      url: (settings && settings.url) || null,
      status: (jqxhr && jqxhr.status) || null,
      response: (jqxhr && jqxhr.responseText) || null
    };
    console.warn('AJAX error =>', info);
  });
})();

// Interceptar XHR para ver respuesta con token
(function(){
  const _open = XMLHttpRequest.prototype.open;
  const _send = XMLHttpRequest.prototype.send;

  XMLHttpRequest.prototype.open = function(method, url){
    this._isAuth = /\/authenticateV2(\b|\/|\?|#|$)/.test(String(url||''));
    this._url    = url;
    return _open.apply(this, arguments);
  };

  XMLHttpRequest.prototype.send = function(body){
    if (this._isAuth) {
      this.addEventListener('load', () => {
        try {
          console.log('[VCE] authenticateV2 response:', {
            status: this.status,
            text: this.responseText
          });
        } catch(_){}
      });
    }
    return _send.apply(this, arguments);
  };
})();
  </script>
</head>
<body>
<div class="wrap">
  <h1>Pago seguro con Banorte</h1>
  <p class="intro">Ser&aacute;s redirigido a la plataforma segura de Banorte; ellos procesar&aacute;n tu pago.</p>

  <div class="amount">
    Total: <?= htmlspecialchars(number_format((float)$amountForView, 2, '.', ',')) ?> MXN
  </div>

  <div class="row">
    <div>
      <button id="payBtn">
        <span id="spin"></span>
        IR A PAGAR
      </button>
    </div>

    <!-- Debug oculto: permanece en DOM para no romper dbg(), pero no se ve -->
    <div class="debug-wrap">
      <h3>Debug</h3>
      <pre id="dbg"></pre>
    </div>
  </div>
</div>

<!-- ===== Modal Wrapper + Iframe ===== -->
<div id="paymentFrameWrapper" aria-hidden="true">
  <div class="backdrop"></div>
  <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="paymentDialogTitle">
    <header>
      <div id="paymentDialogTitle" class="title">Procesando pago Banorte…</div>
      <button id="closeModalBtn" class="close" type="button" title="Cerrar">&times;</button>
    </header>
    <iframe id="paymentFrame" name="paymentFrame" allow="payment *; fullscreen"></iframe>
  </div>
</div>

<script>
(function(){
  // ------- Utilidades -------
  const dbgEl = document.getElementById('dbg');
  const dbg = (label, obj) => {
    // Aunque está oculto, mantenemos el logger por si necesitas revisar el DOM.
    if (!dbgEl) return;
    const time = new Date().toISOString().replace('T',' ').replace('Z','');
    if (obj === undefined) {
      dbgEl.textContent += `[${time}] ${label}\n`;
    } else {
      try {
        dbgEl.textContent += `[${time}] ${label}\n` + JSON.stringify(obj, null, 2) + "\n";
      } catch(_) {
        dbgEl.textContent += `[${time}] ${label}\n` + String(obj) + "\n";
      }
    }
  };
  const b64EncodeUnicode = (str) => btoa(unescape(encodeURIComponent(str)));

  // ------- Datos de la orden -------
  const order         = <?= json_encode($order, JSON_UNESCAPED_SLASHES) ?>;
  const orderForDebug = <?= json_encode($orderForDebug, JSON_UNESCAPED_SLASHES) ?>;

  // Log (queda oculto en UI)
  dbg('OrderData:', orderForDebug);

  // ------- Acción Pagar (MISMO FLUJO QUE FUNCIONABA) -------
  document.getElementById('payBtn').addEventListener('click', async () => {
    const spin = document.getElementById('spin');
    const btn  = document.getElementById('payBtn');
    btn.disabled = true; spin.style.display = 'inline-block';

    try {
      // 1) base64(JSON(order))
      const base64 = b64EncodeUnicode(JSON.stringify(order));
      const wsUrl  = 'wsCifrado.php'; // mismo directorio

      // 2) Cifrado vía PHP -> microservicio Java
      dbg('POST wsCifrado payload:', { base64_len: base64.length });
      const res = await fetch(wsUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ base64 })
      });

      const json = await res.json().catch(() => ({}));
      dbg('Respuesta wsCifrado:', json);

      if (!res.ok || !json || !json.data || !json.data[0]) {
        alert('Error cifrando parámetros. Revisa logs (wsCifrado).');
        return;
      }

      // 3) sub1:::sub2 para el lightbox
      const paramsString = json.data[0];
      dbg('ParamsString:', paramsString);

      // 4) Verificar SDK y lanzar (como lo tenías)
      if (typeof Payment === 'undefined' || !Payment.startPayment) {
        dbg('Error:', 'checkout/checkoutV2 no expuso Payment.startPayment');
        alert('No se cargó el SDK de Banorte. Verifica endpoint y caché.');
        return;
      }

      console.log('[VCE] Llamando Payment.startPayment...');
      if (window.$modal) $modal.show(); // abre modal/iframe antes (por si el SDK usa window.open)

      Payment.startPayment({
        Params: paramsString,
        onSuccess: function(resp) {
          dbg('VCE onSuccess:', resp);
          const nc = (resp && (resp.numeroControl || resp.controlNumber)) || order.controlNumber || '';
          window.location = 'callback.php?status=A&control=' + encodeURIComponent(nc);
        },
        onError: function(resp) {
          console.error('Error VCE (raw):', resp);
          dbg('Error VCE (raw):', resp);
          try { alert('VCE onError:\n' + JSON.stringify(resp, null, 2)); } catch(_){}
          const code = (resp && (resp.id || resp.code)) || '';
          const msg  = (resp && (resp.message || resp.descripcion || resp.error)) || 'Error desconocido';
          window.location = 'callback.php?status=E&code=' + encodeURIComponent(code) + '&message=' + encodeURIComponent(msg);
        },
        onCancel: function() {
          dbg('VCE onCancel');
          window.location = 'callback.php?status=C';
        },
        onClosed: function() {
          dbg('VCE onClosed');
          if (window.$modal) $modal.hide();
        }
      });

    } catch (e) {
      console.error(e);
      dbg('Excepción inesperada:', { message: e.message, stack: e.stack });
      alert('Falla inesperada: ' + e.message);
    } finally {
      spin.style.display = 'none';
      btn.disabled = false;
    }
  });
})();
</script>
</body>
</html> 