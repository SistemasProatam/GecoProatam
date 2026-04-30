<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
// generar_folio.php — Devuelve el folio actual para una entidad (sin incrementar)
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();

header('Content-Type: application/json; charset=UTF-8');

$entidades_validas = ['PROATAM', 'INGETAM', 'LUBYCOMP', 'DAVID GOMEZ'];
$entidad = $_GET['entidad'] ?? 'PROATAM';
if (!in_array($entidad, $entidades_validas)) $entidad = 'PROATAM';

$prefijos = [
    'PROATAM'     => 'CO-PRO',
    'INGETAM'     => 'CO-ING',
    'LUBYCOMP'    => 'CO-LUB',
    'DAVID GOMEZ' => 'CO-DAG',
];

$prefijo   = $prefijos[$entidad];
$dir       = __DIR__ . '/data';
$folioFile = "$dir/folio_{$entidad}.txt";

if (!is_dir($dir)) mkdir($dir, 0755, true);
if (!file_exists($folioFile)) file_put_contents($folioFile, '1');

$num   = (int)file_get_contents($folioFile);
$folio = $prefijo . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);

echo json_encode(['folio' => $folio, 'numero' => $num]);

