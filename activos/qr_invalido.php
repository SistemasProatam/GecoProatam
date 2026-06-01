<?php
require_once __DIR__ . "/../config.php";
$razon = $_GET['razon'] ?? 'departamento';
$mensajes = [
    'token'        => 'El cÃ³digo QR no tiene un formato vÃ¡lido.',
    'no_encontrado'=> 'Este cÃ³digo QR no corresponde a ningÃºn activo registrado en el sistema.',
    'desconocido'  => 'OcurriÃ³ un error al procesar el cÃ³digo QR.',
    'departamento' => 'Tu departamento no tiene permisos para acceder a esta informaciÃ³n.'
];
$mensaje = $mensajes[$razon] ?? $mensajes['desconocido'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acceso Restringido</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/qr_invalido.css">
</head>
<body>
  <div class="card card-error">
    <div class="icon-container">
      <i class="bi bi-shield-x"></i>
    </div>
    <h2 class="error-title">Acceso restringido</h2>
    <p class="error-desc">No cuentas con los permisos necesarios para visualizar la informaciÃ³n confidencial de este activo.</p>
    
    <div class="reason-box">
      <strong>Detalle:</strong> <?= htmlspecialchars($mensaje) ?>
    </div>
    
    <a href="<?= BASE_URL ?>/index.php" class="btn-action">
      <i class="bi bi-house-door"></i> Ir al inicio de GECO
    </a>
  </div>
</body>
</html>



