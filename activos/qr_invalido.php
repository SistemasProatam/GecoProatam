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
  <title>Acceso Restringido</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --font-family: 'Outfit', sans-serif;
      --bg-color: #f8fafc;
      --card-bg: #ffffff;
      --text-main: #0f172a;
      --text-muted: #64748b;
      --danger-color: #ef4444;
      --danger-bg: rgba(239, 68, 68, 0.08);
      --primary-color: #1e3a8a;
      --primary-hover: #1e40af;
      --shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    
    body {
      background-color: var(--bg-color);
      font-family: var(--font-family);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
    }
    
    .card-error {
      max-width: 440px;
      width: 100%;
      background: var(--card-bg);
      border-radius: 20px;
      border: 1px solid rgba(226, 232, 240, 0.8);
      box-shadow: var(--shadow);
      padding: 3rem 2.5rem !important;
      text-align: center;
      transition: transform 0.3s ease;
    }
    
    .card-error:hover {
      transform: translateY(-2px);
    }
    
    .icon-container {
      width: 90px;
      height: 90px;
      margin: 0 auto 1.5rem;
      background: var(--danger-bg);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      animation: pulse 2s infinite;
    }
    
    .icon-container i {
      font-size: 2.5rem;
      color: var(--danger-color);
    }
    
    .error-title {
      font-size: 1.6rem;
      font-weight: 700;
      color: var(--text-main);
      margin-bottom: 0.75rem;
      letter-spacing: -0.02em;
    }
    
    .error-desc {
      font-size: 0.95rem;
      color: var(--text-muted);
      line-height: 1.6;
      margin-bottom: 1.75rem;
    }
    
    .reason-box {
      background: #f1f5f9;
      border-radius: 12px;
      padding: 1rem;
      font-size: 0.875rem;
      color: #475569;
      margin-bottom: 2rem;
      border-left: 4px solid var(--danger-color);
      text-align: left;
    }
    
    .btn-action {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      width: 100%;
      background-color: var(--primary-color);
      color: #ffffff;
      font-weight: 600;
      font-size: 0.95rem;
      padding: 0.85rem 1.5rem;
      border-radius: 12px;
      border: none;
      text-decoration: none;
      transition: all 0.2s ease;
      box-shadow: 0 4px 12px rgba(30, 58, 138, 0.15);
    }
    
    .btn-action:hover {
      background-color: var(--primary-hover);
      color: #ffffff;
      box-shadow: 0 6px 16px rgba(30, 58, 138, 0.25);
    }
    
    @keyframes pulse {
      0% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
      }
      70% {
        box-shadow: 0 0 0 15px rgba(239, 68, 68, 0);
      }
      100% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
      }
    }
  </style>
</head>
<body>
  <div class="card card-error">
    <div class="icon-container">
      <i class="bi bi-shield-x"></i>
    </div>
    <h2 class="error-title">Acceso restringido</h2>
    <p class="error-desc">No cuentas con los permisos necesarios para visualizar la información confidencial de este activo.</p>
    
    <div class="reason-box">
      <strong>Detalle:</strong> <?= htmlspecialchars($mensaje) ?>
    </div>
    
    <a href="<?= BASE_URL ?>/index.php" class="btn-action">
      <i class="bi bi-house-door"></i> Ir al inicio de GECO
    </a>
  </div>
</body>
</html>


