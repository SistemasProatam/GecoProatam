<?php
require_once __DIR__ . '/config.php';

// Incluir el gestor de sesiones UNA sola vez
require_once "includes/session_manager.php";
require_once "includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Inicio</title>
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
      crossorigin="anonymous"
    />
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    />
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/index.css" />
  </head>
  <body>
        <?php include 'includes/navbar.php'; ?>
        
    <div class="content-wrapper">
        <div class="welcome-content">
          <?php
          $primerNombre = explode(' ', trim($_SESSION['nombres']))[0] ?? '';
          $primerApellido = explode(' ', trim($_SESSION['apellidos']))[0] ?? '';
          ?>
          <h1><strong>Bienvenido <?php
          echo htmlspecialchars($primerNombre . ' ' . $primerApellido); ?></strong></h1>
          <p class="welcome-title"><strong> Sistema de Gestión</strong></p>
          <img class="welcome-img" src="<?= BASE_URL ?>/assets/img/proatam.png" alt="Alternate Text" />
        </div>
    </div>
          <?php include 'includes/footer.php'; ?>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
      crossorigin="anonymous"
    ></script>

  </body>
</html>

