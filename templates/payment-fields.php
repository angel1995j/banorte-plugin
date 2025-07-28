<div id="banorte-payment-form">
    <div class="banorte-description">
        <?php echo wpautop(wptexturize($description)); ?>
    </div>
    
    <?php if ($testmode): ?>
        <div class="banorte-test-mode-notice">
            <p>MODO PRUEBAS HABILITADO</p>
        </div>
    <?php endif; ?>
    
    <input type="hidden" id="banorte_order_id" value="<?php echo $order_id; ?>">
</div>