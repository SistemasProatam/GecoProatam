<?php
require_once __DIR__ . '/../config.php';

require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");
require_once __DIR__ . "/qr_generator.php";

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: list_activos.php");
    exit;
}

$stmt = $conn->prepare(
    "SELECT a.id, a.nombre, a.codigo, a.qr_token,
            at.nombre AS tipo_nombre, at.prefijo
     FROM activos a
     LEFT JOIN activo_tipos at ON at.id = a.tipo_id
     WHERE a.id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$activo = $stmt->get_result()->fetch_assoc();

if (!$activo || !$activo['qr_token']) {
    header("Location: details_activo.php?id={$id}&error=sin_qr");
    exit;
}

// Intentar leer qr_ruta_imagen
$qrRutaActual = null;
try {
    $stmt_ruta = $conn->prepare("SELECT qr_ruta_imagen FROM activos WHERE id=?");
    $stmt_ruta->bind_param("i", $id);
    $stmt_ruta->execute();
    $row_ruta = $stmt_ruta->get_result()->fetch_assoc();
    $qrRutaActual = $row_ruta['qr_ruta_imagen'] ?? null;
} catch (Throwable $e) { }

// Regenerar el PNG si no está en disco
if (!$qrRutaActual || !file_exists($_SERVER['DOCUMENT_ROOT'] . $qrRutaActual)) {
    $nuevaRuta = QRGenerator::generarYGuardar($activo['qr_token']);
    if ($nuevaRuta) {
        try {
            $stmt_upd = $conn->prepare("UPDATE activos SET qr_ruta_imagen=? WHERE id=?");
            $stmt_upd->bind_param("si", $nuevaRuta, $id);
            $stmt_upd->execute();
        } catch (Throwable $e) {
            error_log("QR update skipped: " . $e->getMessage());
        }
        $qrRutaActual = $nuevaRuta;
    }
}

$qrUrl = QRGenerator::getQRUrl($activo['qr_token'], $qrRutaActual, 400);
$nombreArchivo = 'QR_' . preg_replace('/[^A-Z0-9_-]/i', '_', $activo['codigo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Imprimir QR – <?= htmlspecialchars($activo['codigo']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <style>
    body { background: #f0f4f8; font-family: 'Segoe UI', sans-serif; }

    .screen-actions {
      max-width: 680px;
      margin: 24px auto 0;
      padding: 0 16px;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .qr-card-wrap {
      display: flex;
      justify-content: center;
      padding: 24px 16px 40px;
    }
    .qr-card {
      background: #fff;
      border: 2px solid #dee2e6;
      border-radius: 16px;
      padding: 28px 32px 24px;
      width: 340px;
      box-shadow: 0 4px 20px rgba(0,0,0,.10);
      text-align: center;
    }
    .qr-card .logo-wrap { margin-bottom: 16px; }
    .qr-card .logo-wrap img { max-height: 52px; max-width: 200px; object-fit: contain; }
    .qr-card .logo-texto {
      font-size: 1.15rem; font-weight: 800; color: #113456;
      letter-spacing: 2px; text-transform: uppercase;
    }
    .qr-card .divider { border: none; border-top: 2px solid #e2e8f0; margin: 12px 0; }
    .qr-card .qr-img {
      width: 220px; height: 220px;
      margin: 0 auto 14px; display: block;
      border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px;
    }
    .qr-card .activo-tipo {
      font-size: .72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .08em; color: #6c757d; margin-bottom: 4px;
    }
    .qr-card .activo-nombre {
      font-size: .98rem; font-weight: 700; color: #212529;
      line-height: 1.3; margin-bottom: 8px;
    }
    .qr-card .activo-codigo {
      display: inline-block; font-family: monospace; font-size: 1rem;
      font-weight: 700; background: #f0f4f8; border: 1px dashed #adb5bd;
      border-radius: 6px; padding: 4px 14px; letter-spacing: 2px;
      color: #113456; margin-bottom: 12px;
    }
    .qr-card .scan-hint { font-size: .7rem; color: #adb5bd; margin-top: 8px; }

    /* Estado de carga del botón PNG */
    .btn-png-loading { opacity: .7; pointer-events: none; }

    @media print {
      body { background: #fff; margin: 0; }
      .screen-actions { display: none !important; }
      .qr-card-wrap { padding: 0; justify-content: center; }
      .qr-card { border: 1.5px solid #ccc; box-shadow: none; border-radius: 10px; page-break-inside: avoid; }
      .multi-print .qr-card-wrap {
        display: grid; grid-template-columns: repeat(2, 340px);
        gap: 16px; justify-content: center; padding: 16px;
      }
    }
  </style>
</head>
<body>

  <div class="screen-actions align-items-center justify-content-center">
    <button id="btnPNG" onclick="descargarPNG(this)" class="btn btn-success">
      <i class="bi bi-file-image"></i> Descargar PNG
    </button>
    <a href="details_activo.php?id=<?= $id ?>" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left"></i> Volver al activo
    </a>
  </div>

  <?php
require_once __DIR__ . '/config.php';
 $cantidad = max(1, min(8, (int)($_GET['cantidad'] ?? 1))); ?>
  <?php
require_once __DIR__ . '/config.php';
 if ($cantidad > 1): ?><div class="multi-print"><?php
require_once __DIR__ . '/config.php';
 endif; ?>

  <?php
require_once __DIR__ . '/config.php';
 for ($c = 0; $c < $cantidad; $c++): ?>
  <div class="qr-card-wrap">
    <div class="qr-card" id="qr-card-<?= $c ?>">

      <div class="logo-wrap">
        <?php
require_once __DIR__ . '/config.php';
 $logo_path = $_SERVER['DOCUMENT_ROOT'] . '/assets/img/logo.png'; ?>
        <?php
require_once __DIR__ . '/config.php';
 if (file_exists($logo_path)): ?>
          <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Logo PROATAM" />
        <?php
require_once __DIR__ . '/config.php';
 else: ?>
          <div class="logo-texto">PROATAM</div>
        <?php
require_once __DIR__ . '/config.php';
 endif; ?>
      </div>

      <hr class="divider" />

      <img class="qr-img"
           src="<?= htmlspecialchars($qrUrl) ?>"
           alt="QR <?= htmlspecialchars($activo['codigo']) ?>"
           crossorigin="anonymous" />

      <div class="activo-tipo"><?= htmlspecialchars($activo['tipo_nombre'] ?? '') ?></div>
      <div class="activo-nombre"><?= htmlspecialchars($activo['nombre']) ?></div>
      <div class="activo-codigo"><?= htmlspecialchars($activo['codigo']) ?></div>

      <hr class="divider" />

      <div class="scan-hint">
        <i class="bi bi-qr-code-scan"></i>
        Escanea para ver información del activo
      </div>

    </div>
  </div>
  <?php
require_once __DIR__ . '/config.php';
 endfor; ?>

  <?php
require_once __DIR__ . '/config.php';
 if ($cantidad > 1): ?></div><?php
require_once __DIR__ . '/config.php';
 endif; ?>

  <script>
    function descargarPNG(btn) {
      // Capturar siempre la primera tarjeta
      var card = document.getElementById('qr-card-0');
      if (!card) card = document.querySelector('.qr-card');
      if (!card) return;

      btn.classList.add('btn-png-loading');
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generando...';

      html2canvas(card, {
        scale: 3,          // Alta resolución (3× = ~1020px de ancho → nítido en Word)
        useCORS: true,     // Necesario para imágenes cross-origin
        backgroundColor: '#ffffff',
        logging: false
      }).then(function(canvas) {
        var link = document.createElement('a');
        link.download = '<?= $nombreArchivo ?>.png';
        link.href = canvas.toDataURL('image/png');
        link.click();

        btn.classList.remove('btn-png-loading');
        btn.innerHTML = '<i class="bi bi-file-image"></i> Descargar PNG';
      }).catch(function(err) {
        console.error('html2canvas error:', err);
        btn.classList.remove('btn-png-loading');
        btn.innerHTML = '<i class="bi bi-file-image"></i> Descargar PNG';
        alert('No se pudo generar la imagen. Intenta de nuevo.');
      });
    }
  </script>

</body>
</html>

