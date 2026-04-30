<?php
require_once "config.php";

require_once config.php;
session_start();
// Si ya está loggeado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$email = $_GET['email'] ?? '';
if (empty($email)) {
    header("Location: forgot_password.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Código</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/change_pass.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
    .button-57 i {
  display: block;
  margin-right: 0.50em;
  transform-origin: center center;
  transition: transform 0.3s ease-in-out;
  transform: rotate(0deg) scale(1.1);
}

.form-text {
    font-size: 0.875em;
    color: #6c757d;
    text-align: center;}
</style>
</head>
<body>
<div class="page-wrapper">
    <div class="form-container">

    <button class="btn-back-floating" onclick="history.back()" title="Regresar">
    <i class="bi bi-arrow-left"></i>
    <span>Regresar</span>
    </button>

        <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">
        <img
            src="<?= BASE_URL ?>/assets/img/proatam.png"
            alt="Logo PROATAM"
            width="200"
            height="auto"
            class="d-inline-block align-text-top"
        />
    </a>
        <h2>Verificar Código</h2>
        <p>Ingresa el código de 6 dígitos que enviamos a:<br><strong><?php
require_once "config.php";

require_once config.php; echo htmlspecialchars($email); ?></strong></p>

        <form id="verifyTokenForm">
            <input type="hidden" name="email" value="<?php
require_once "config.php";

require_once config.php; echo htmlspecialchars($email); ?>">
            
            <div class="form-group">
                <label for="token" class="form-label">Código de Verificación <span class="required">*</span></label>
                <input type="text" name="token" id="token" class="form-control" required 
                       placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                       oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                <div class="form-text">Ingresa el código de 6 dígitos que recibiste por correo.</div>
            </div>

            <button type="submit" class="button-57" id="submitBtn">
                <i class="bi bi-shield-check"></i><span>Verificar Código</span>
            </button>
        </form>

    </div>
</div>

    <script>
    document.getElementById('verifyTokenForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const token = document.getElementById('token').value.trim();
        const email = document.querySelector('input[name="email"]').value;
        const submitBtn = document.getElementById('submitBtn');
        
        if (!token || token.length !== 6) {
            Swal.fire({ 
                icon: 'warning', 
                title: 'Código inválido', 
                text: 'Ingresa un código de 6 dígitos.' 
            });
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i><span>Verificando...</span>';

        try {
            const formData = new FormData();
            formData.append('email', email);
            formData.append('token', token);

            const response = await fetch('verify_reset_token.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Código Verificado',
                    text: result.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    // Redirigir a la página de cambio de contraseña
                    window.location.href = `reset_password.php?token=${result.reset_token}`;
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message
                });
            }

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor.'
            });
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-shield-check"></i><span>Verificar Código</span>';
        }
    });

    // Auto-enfocar el campo de token
    document.getElementById('token').focus();
    </script>
</body>
</html>


