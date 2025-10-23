
/* === Banorte VCE – Checkout UX enhancer (non-invasive) === */
(function(){
  const Q = sel => document.querySelector(sel);
  const QA = sel => Array.from(document.querySelectorAll(sel));
  const params = new URLSearchParams(location.search);
  const amount = params.get('amount') || params.get('importe') || '';
  const currency = 'MXN';

  function formatMoney(v){
    if(!v) return '';
    const n = Number(v);
    if(isNaN(n)) return v;
    return n.toLocaleString('es-MX',{style:'currency',currency:'MXN'});
  }

  // Mark body for scoping
  document.documentElement.classList.add('vce-ux-root');
  document.body.classList.add('vce-ux');

  // Build shell UI
  const shell = document.createElement('div');
  shell.className = 'vce-container';
  shell.innerHTML = `
    <div class="vce-card">
      <div class="vce-header">
        <div class="vce-brand">
          <img src="https://upload.wikimedia.org/wikipedia/commons/7/7f/Banorte_logo.svg" alt="Banorte"/>
          <h1>Pago seguro con Banorte</h1>
        </div>
        <div class="vce-amount" id="vceAmount"> ${amount ? formatMoney(amount) : ''} </div>
      </div>
      <div class="vce-body">
        <div class="vce-main">
          <div class="vce-steps">
            <div class="vce-chip active">1 · Revisión</div>
            <div class="vce-chip">2 · Autenticación</div>
            <div class="vce-chip">3 · Confirmación</div>
          </div>

          <p class="vce-hint">Al continuar se abrirá el recuadro seguro de Banorte para capturar tu método de pago.</p>

          <div class="vce-actions">
            <button class="vce-btn -primary" id="vcePayBtn">
              <span class="vce-spinner" id="vceSpin" style="display:none"></span>
              <span>Continuar y pagar</span>
            </button>
            <a class="vce-btn -muted" id="vceCancelBtn" href="/cart/">Cancelar</a>
            <a class="vce-btn" id="vceHelpBtn" href="mailto:soporte@maratonmonterrey.mx">Necesito ayuda</a>
          </div>

          <div class="vce-hint">Transacción procesada por Banorte. Tus datos se envían cifrados.</div>
          <div class="vce-divider"></div>
          <div>
            <span class="vce-toggle" id="vceToggleLog">Ver detalles técnicos</span>
            <pre class="vce-log" id="vceLog"></pre>
          </div>
        </div>

        <aside class="vce-aside">
          <ul class="vce-list">
            <li><span class="vce-k">Importe</span><span class="vce-v" id="vceAmountAside">${amount ? formatMoney(amount) : ''}</span></li>
            <li><span class="vce-k">Moneda</span><span class="vce-v">${currency}</span></li>
            <li><span class="vce-k">Sitio</span><span class="vce-v">${location.hostname}</span></li>
          </ul>
          <div class="vce-divider"></div>
          <div class="vce-badges">
            <img class="vce-badge" src="https://upload.wikimedia.org/wikipedia/commons/7/7f/Banorte_logo.svg" alt="Banorte"/>
            <img class="vce-badge" src="https://upload.wikimedia.org/wikipedia/commons/2/2a/PCI_DSS_logo.svg" alt="PCI"/>
            <img class="vce-badge" src="https://upload.wikimedia.org/wikipedia/commons/5/5a/Lock_font_awesome.svg" alt="Secure"/>
          </div>
        </aside>
      </div>
    </div>
  `;
  document.body.prepend(shell);

  // Logger hookup (non-invasive)
  const logEl = Q('#vceLog');
  function log(){ try {
    const t = Array.from(arguments).map(x => typeof x==='object'? JSON.stringify(x): String(x)).join(' ');
    const line = '['+ new Date().toLocaleTimeString('es-MX') +'] ' + t + '\n';
    logEl.textContent += line;
  } catch(e){} }

  // Toggle log
  Q('#vceToggleLog').addEventListener('click', () => {
    logEl.classList.toggle('show');
  });

  // Payment trigger: try your existing function/button without changing logic
  const btn = Q('#vcePayBtn');
  const spin = Q('#vceSpin');
  function setBusy(b){ spin.style.display = b? 'inline-block':'none'; btn.disabled = !!b; }

  async function tryStart(){
    setBusy(true); log('Iniciando pago UX…');
    try {
      // 1) If your page exposes startPaymentFlow()
      if (typeof window.startPaymentFlow === 'function') { log('→ startPaymentFlow()'); await window.startPaymentFlow(); return; }

      // 2) If your legacy button exists (common ids)
      const legacyBtn = Q('#btnRun') || Q('#pay') || Q('button[name="pay"]') || QA('button, input[type="submit"]').find(b=>/pagar|pay|iniciar/i.test(b.textContent||b.value||''));
      if (legacyBtn) { log('→ click legacy button:', legacyBtn.outerHTML.slice(0,80)); legacyBtn.click(); return; }

      // 3) If Payment is globally available (last resort; we try not to touch your data flow)
      if (window.Payment && (Payment.startPayment || Payment.authenticateV2)) {
        log('→ Payment global detectado, espera a que tu flujo lo invoque…');
        return;
      }

      log('⚠ No encontré un disparador de pago. Usa tu botón original si lo ves en pantalla.');
      alert('No pude detectar el botón de pago original automáticamente. Por favor, usa el botón de tu página.');
    } catch(e){
      console.error(e); log('ERROR:', e.message||e);
      alert('Error al iniciar pago: ' + (e.message||e));
    } finally {
      setBusy(false);
    }
  }

  btn.addEventListener('click', tryStart);

  // Auto-focus on button
  setTimeout(()=>btn.focus(), 300);

  // If page already auto-invokes the payment, we simply stay as a visual shell.
})();
