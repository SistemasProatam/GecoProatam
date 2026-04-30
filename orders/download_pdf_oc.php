<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();

// Forzar descarga
$_GET['download'] = true;

// Incluir el generador de PDF
include('generate_pdf_oc.php');
?>

