<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";
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

// Regenerar el PNG si no estÃ¡ en disco
if (!$qrRutaActual || !file_exists(__DIR__ . '/..' . $qrRutaActual)) {
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
  <title>Imprimir QR â€“ <?= htmlspecialchars($activo['codigo']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/print_qr.css">
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
$cantidad = max(1, min(8, (int)($_GET['cantidad'] ?? 1))); ?>
  <?php
if ($cantidad > 1): ?><div class="multi-print"><?php
endif; ?>

  <?php
for ($c = 0; $c < $cantidad; $c++): ?>
  <div class="qr-card-wrap">
    <div class="qr-card" id="qr-card-<?= $c ?>">

      <div class="logo-wrap">
        <?php
$logo_path = __DIR__ . '/../assets/img/logo.png'; ?>
        <?php
if (file_exists($logo_path)): ?>
          <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Logo PROATAM" />
        <?php
else: ?>
          <div class="logo-texto">PROATAM</div>
        <?php
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
        Escanea para ver informaciÃ³n del activo
      </div>

    </div>
  </div>
  <?php
endfor; ?>

  <?php
if ($cantidad > 1): ?></div><?php
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
        scale: 3,          // Alta resoluciÃ³n (3Ã— = ~1020px de ancho â†’ nÃ­tido en Word)
        useCORS: true,     // Necesario para imÃ¡genes cross-origin
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
        UI.toast.error('No se pudo generar la imagen. Intenta de nuevo.');
      });
    }
  </script>

</body>
</html>




