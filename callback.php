<?php
// callback.php: Muestra el resultado del pago

$status  = $_GET['status']  ?? '';
$code    = $_GET['code']    ?? '';
$message = $_GET['message'] ?? '';
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Resultado de Pago Banorte</title>
  <style>
    body { font-family: sans-serif; padding: 2rem; }
    .success { color: green; }
    .error   { color: red; }
    .info    { color: #555; }
    .box { border: 1px solid #ccc; padding: 1rem; border-radius: 4px; }
  </style>
</head>
<body>
  <div class="box">
    <?php if ($status === 'A'): ?>
      <h1 class="success">✅ Pago aprobado</h1>
      <?php if ($code): ?>
        <p><strong>Código de autorización:</strong> <?= htmlspecialchars($code) ?></p>
      <?php endif; ?>
    <?php elseif ($status === 'E'): ?>
      <h1 class="error">❌ Pago rechazado o con error</h1>
      <p><strong>Status:</strong> <?= htmlspecialchars($status) ?></p>
      <?php if ($code !== ''): ?>
        <p><strong>Código de error:</strong> <?= htmlspecialchars($code) ?></p>
      <?php endif; ?>
      <?php if ($message !== ''): ?>
        <p><strong>Mensaje:</strong><br>
          <?= nl2br(htmlspecialchars($message)) ?></p>
      <?php else: ?>
        <p><em>No se recibió detalle del error.</em></p>
      <?php endif; ?>
    <?php elseif ($status === 'C'): ?>
      <h1 class="info">⚠️ Pago cancelado por el usuario</h1>
    <?php else: ?>
      <h1 class="info">ℹ️ Estado desconocido</h1>
      <p><strong>Status recibido:</strong> <?= htmlspecialchars($status) ?></p>
    <?php endif; ?>
  </div>
</body>
</html>
