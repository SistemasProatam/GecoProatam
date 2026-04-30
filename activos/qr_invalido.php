<?php
require_once __DIR__ . "/../config.php";
$razon = $_GET['razon'] ?? 'departamento';
$mensajes = [
    'token'        => 'El código QR no tiene un formato válido.',
    'no_encontrado'=> 'Este código QR no corresponde a ningún activo registrado en el sistema.',
    'desconocido'  => 'Ocurrió un error al procesar el código QR.',
    'departamento' => 'Tu departamento no tiene permisos para acceder a esta información.'
];
$mensaje = $mensajes[$razon] ?? $mensajes['desconocido'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Usuario No Autorizado</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <style>
    body { background: #f0f4f8; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .card-error { max-width: 420px; width: 100%; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,.12); }
    .icon-wrap { font-size: 4rem; color: #dc3545; }
  </style>
</head>
<body>
  <div class="card card-error p-5 text-center bg-white">
    <div class="icon-wrap mb-3"><i class="bi bi-qr-code"></i></div>
    <h2 class="fw-bold mb-2">Acceso restringido</h2>
    <p>No tienes permisos para visualizar la información de este activo.</p>
    <p class="text-muted mb-4"><?= htmlspecialchars($mensaje) ?></p>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary" style="background:#113456; border-color:#113456;">
      <i class="bi bi-house-door"></i> Ir al inicio
    </a>
  </div>
</body>
</html>


