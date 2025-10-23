
/*!
 * Banorte VCE – Autopay (headless trigger)
 * Objetivo: Iniciar el pago automáticamente al cargar la página, con UI mínima.
 * No toca tu lógica: sólo intenta disparar tu flujo existente.
 */
(function(){
  const qs = new URLSearchParams(location.search);
  const SILENT = qs.get('silent') !== '0'; // por defecto oculto
  const TIMEOUT = parseInt(qs.get('timeout')||'8000',10); // ms para fallback
  const RETRY_MS = 200;

  // Ocultar todo visualmente (modo "silent")
  if (SILENT) {
    const style = document.createElement('style');
    style.innerHTML = 'html,body{background:#0b1220!important;color:#e5ecff!important} body>*{display:none!important}';
    document.head.appendChild(style);
    document.documentElement.classList.add('autopay-silent');
  }

  function log(){ try{ console.debug('[AUTOPAY]', ...arguments); }catch(e){} }

  function findLegacyButton(){
    const q = s => document.querySelector(s);
    const qa = s => Array.from(document.querySelectorAll(s));
    return q('#btnRun') || q('#pay') || qa('button, input[type="submit"]').find(b=>{
      const t = (b.textContent || b.value || '').toLowerCase();
      return /pagar|pay|iniciar|continuar/.test(t);
    });
  }

  let started = false;
  async function start(){
    if (started) return;
    started = true;
    log('intentando start…');

    try {
      if (typeof window.startPaymentFlow === 'function') {
        log('startPaymentFlow()');
        await window.startPaymentFlow();
        return;
      }

      const btn = findLegacyButton();
      if (btn) {
        log('click legacy button', btn);
        btn.click();
        return;
      }

      if (window.Payment && (window.Payment.startPayment || window.Payment.authenticateV2)) {
        log('Payment global presente; esperando a que tu flujo lo invoque…');
        return;
      }

      throw new Error('No encontré el disparador de pago');
    } catch(e){
      log('error intentando start:', e);
      throw e;
    }
  }

  // Intenta tan pronto como sea posible
  const ready = (fn)=> {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  };

  ready(()=>{
    // Primer intento inmediato
    start().catch(()=>{});

    // Reintentos rápidos por si tu código crea el botón después
    const startedAt = Date.now();
    const iv = setInterval(()=>{
      if (Date.now() - startedAt > Math.max(1200, RETRY_MS*8)) { clearInterval(iv); return; }
      if (started) return;
      start().catch(()=>{});
    }, RETRY_MS);

    // Fallback visual si no se pudo en X tiempo
    setTimeout(()=>{
      if (!started) {
        clearInterval(iv);
        // Mostrar una UI mínima para que el usuario toque el botón
        document.body.innerHTML = `
          <div style="min-height:100vh;display:grid;place-items:center;background:#0b1220;color:#e5ecff;font-family:system-ui,-apple-system,Segoe UI,Roboto">
            <div style="max-width:520px;background:#121a2b;border:1px solid rgba(255,255,255,.1);padding:24px;border-radius:16px;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.35)">
              <div style="width:40px;height:40px;border:4px solid rgba(255,255,255,.2);border-top-color:#fff;border-radius:50%;margin:2px auto 12px;animation:spin .9s linear infinite"></div>
              <h1 style="font-size:18px;margin:10px 0 8px">Estamos listos para procesar tu pago</h1>
              <p style="color:#9fb0d3">Si no se abrió automáticamente, toca el botón para continuar.</p>
              <button id="autopayBtn" style="margin-top:10px;padding:12px 16px;border-radius:10px;background:#2bb673;color:#001d10;font-weight:700;border:none;cursor:pointer">Continuar y pagar</button>
              <style>@keyframes spin{to{transform:rotate(360deg)}}</style>
            </div>
          </div>`;
        const b = document.getElementById('autopayBtn');
        b.addEventListener('click', ()=> start().catch(err=> alert('No se pudo iniciar el pago: '+(err&&err.message||err))));
      }
    }, TIMEOUT);
  });
})();
