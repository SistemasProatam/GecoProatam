<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
// ============================================================
// generar_pdf.php — Solo genera y envía el PDF (VERSIÓN CORREGIDA)
// Cambios: Tabla estilo amarillo, totales compactos, firma en primera hoja
// ============================================================
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (ob_get_length()) ob_end_clean();
    die('Error PHP [' . $errno . ']: ' . $errstr . ' en ' . basename($errfile) . ':' . $errline);
});
ob_start();
require_once __DIR__ . '/../fpdf/fpdf186/fpdf.php';

// ── Helpers ───────────────────────────────────────────────────
function p(string $key): string {
    return isset($_POST[$key]) ? trim(strip_tags($_POST[$key])) : '';
}
function pArr(string $key): array {
    return isset($_POST[$key]) && is_array($_POST[$key]) ? $_POST[$key] : [];
}
function moneda(float $n): string {
    return '$' . number_format($n, 2, '.', ',');
}
function L(string $str): string {
    if (empty($str)) return '';
    $map = [
        'Á'=>'A','À'=>'A','Â'=>'A','Ä'=>'A','Ã'=>'A','á'=>'a','à'=>'a','â'=>'a','ä'=>'a','ã'=>'a',
        'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'Ó'=>'O','Ò'=>'O','Ô'=>'O','Ö'=>'O','Õ'=>'O','ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
        'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'Ñ'=>'N','ñ'=>'n','Ç'=>'C','ç'=>'c',
    ];
    $str = strtr($str, $map);
    if (function_exists('mb_convert_encoding')) return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $str);
}

// ── Validaciones mínimas ──────────────────────────────────────
if (empty(trim($_POST['atencion'] ?? ''))) {
    if (ob_get_length()) ob_end_clean();
    die('Error: falta campo atención.');
}

// ── Datos POST ────────────────────────────────────────────────
$folio        = p('folio');
$fecha        = p('fecha');
$atencion     = p('atencion');
$compania     = p('compania');
$lugar        = p('lugar');
$tiempo       = p('tiempo');
$formaPago    = p('forma_pago');
$vigencia     = p('vigencia');
$monedaTxt    = p('moneda')   ?: 'MXN';
$notas        = p('notas');
$alcExtras    = p('alcances_extra');
$emisorNombre = p('emisor_nombre');
$emisorDepto  = p('emisor_depto');
$entidadClave = strtoupper(trim(p('entidad') ?: 'PROATAM'));
$tasaIva      = in_array((int)p('tasa_iva'), [0, 8, 16]) ? (int)p('tasa_iva') : 16;
$alcSelec     = pArr('alcances');

$alcancesMap = [
    'ejecucion'   => 'Ejecucion de los trabajos solicitados conforme a lo indicado por el cliente.',
    'materiales'  => 'Suministro de materiales y mano de obra especializada.',
    'supervision' => 'Supervision tecnica durante toda la obra.',
    'limpieza'    => 'Limpieza general al finalizar los trabajos.',
    'garantia'    => 'Garantia sobre los trabajos realizados.',
    'herramienta' => 'Herramienta y equipo necesario para la ejecucion.',
    'seguridad'   => 'Medidas de seguridad e higiene en obra.',
    'entrega'     => 'Entrega de memoria fotografica al concluir.',
];

// ── Calcular totales ──────────────────────────────────────────
$subtotal = 0;
$filas    = [];
foreach (pArr('desc') as $i => $desc) {
    if (trim($desc) === '') continue;
    $cant  = (float)(pArr('cantidad')[$i] ?? 0);
    $prec  = (float)(pArr('precio')[$i]   ?? 0);
    $imp   = $cant * $prec;
    $subtotal += $imp;
    $filas[] = [
        'desc'   => $desc,
        'unidad' => pArr('unidad')[$i] ?? '',
        'cant'   => $cant,
        'precio' => $prec,
        'imp'    => $imp,
    ];
}
$iva   = $subtotal * ($tasaIva / 100);
$total = $subtotal + $iva;

// Fecha formateada
$meses    = ['','ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
$fechaTs  = $fecha ? strtotime($fecha) : time();
$fechaRef = date('d', $fechaTs) . '-' . $meses[(int)date('m', $fechaTs)] . '-' . date('Y', $fechaTs);

// ── Entidades ─────────────────────────────────────────────────
function getEntityConfig(string $ent): array {
    $cfg = [
        'PROATAM'     => ['primary'=>[17,52,86],   'secondary'=>[63,117,85],  'name'=>'PROATAM S.A. DE C.V.', 'logo'=>'proatam.png'],
        'INGETAM'     => ['primary'=>[239,163,54],  'secondary'=>[0,0,0],      'name'=>'INGETAM S.A. DE C.V.', 'logo'=>'ingetam.png'],
        'LUBYCOMP'    => ['primary'=>[36,57,68],    'secondary'=>[208,176,89], 'name'=>'LUBYCOMP',             'logo'=>'lubycomp.png'],
        'DAVID GOMEZ' => ['primary'=>[251,174,23],  'secondary'=>[0,0,0],      'name'=>'DAVID GOMEZ',          'logo'=>'davidg.png'],
    ];
    return $cfg[strtoupper(trim($ent))] ?? $cfg['PROATAM'];
}
$entityConfig = getEntityConfig($entidadClave);
$assetsDir    = __DIR__ . '/../assets/img';

// ══════════════════════════════════════════════════════════════
//  CLASE PDF MODIFICADA - Totales compactos
// ══════════════════════════════════════════════════════════════
class CotizacionPDF extends FPDF {
    private array  $cPrimary   = [17,52,86];
    private array  $cSecondary = [63,117,85];
    private string $logoPath   = '';
    private string $entityName = 'PROATAM S.A. DE C.V.';
    private array  $cBorde     = [206,212,218];
    private array  $cTexto     = [30,30,30];
    private array  $cGris      = [100,100,100];
    private array  $cFondo     = [248,249,250];
    private array  $cFilaPar   = [255,248,225];
    public  string $folio      = '';
    public  string $fecha      = '';
    public  int    $tasaIva    = 16;

    public function setEntityConfig(array $cfg, string $dir): void {
        $this->cPrimary   = $cfg['primary'];
        $this->cSecondary = $cfg['secondary'];
        $this->entityName = $cfg['name'];
        $this->logoPath   = $dir . '/' . $cfg['logo'];
    }
    public function __construct(string $folio, string $fecha) {
        parent::__construct('P','mm','Letter');
        $this->folio = $folio;
        $this->fecha = $fecha;
    }

    public function Header() {
        $this->SetFillColor(...$this->cSecondary);
        $this->Rect(0,0,216,2,'F');
        $mL=10; $pW=196; $y0=5; $hH=34;
        $this->SetDrawColor(0,0,0); $this->SetLineWidth(0.4);
        $this->Rect($mL,$y0,$pW,$hH);
        $w1=60; $w2=90; $w3=$pW-$w1-$w2; $hMid=$hH/2;
        $this->Line($mL+$w1,$y0,$mL+$w1,$y0+$hH);
        $this->Line($mL+$w1+$w2,$y0,$mL+$w1+$w2,$y0+$hH);
        $this->Line($mL+$w1,$y0+$hMid,$mL+$pW,$y0+$hMid);

        if (!empty($this->logoPath) && file_exists($this->logoPath)) {
            list($aW,$aH) = getimagesize($this->logoPath);
            $lW=50; $lH=($aH/$aW)*$lW;
            $this->Image($this->logoPath,$mL+5,$y0+($hH/2)-($lH/2),$lW);
        } else {
            $this->SetFont('Arial','B',11); $this->SetTextColor(...$this->cPrimary);
            $this->SetXY($mL,$y0+($hH/2)-4); $this->Cell($w1,8,L($this->entityName),0,0,'C');
        }

        $x2=$mL+$w1;
        $this->SetFont('Arial','B',8); $this->SetTextColor(...$this->cGris);
        $this->SetXY($x2,$y0+2); $this->Cell($w2,5,L('Tipo de Documento:'),0,1,'C');
        $this->SetFont('Arial','B',13); $this->SetTextColor(...$this->cPrimary);
        $this->SetX($x2); $this->Cell($w2,9,L('Cotizacion'),0,0,'C');
        $this->SetFont('Arial','B',8); $this->SetTextColor(...$this->cGris);
        $this->SetXY($x2,$y0+$hMid+2); $this->Cell($w2,5,L('Fecha de Elaboracion:'),0,1,'C');
        $this->SetFont('Arial','B',10); $this->SetTextColor(...$this->cTexto);
        $this->SetX($x2); $this->Cell($w2,7,L($this->fecha),0,0,'C');

        $x3=$mL+$w1+$w2;
        $this->SetFont('Arial','B',8); $this->SetTextColor(...$this->cGris);
        $this->SetXY($x3,$y0+2); $this->Cell($w3,5,'Codigo: COT-01',0,1,'C');
        $this->SetX($x3); $this->Cell($w3,5,'Revision: 01',0,0,'C');
        $this->SetXY($x3,$y0+$hMid+2); $this->Cell($w3,5,'FOLIO:',0,1,'C');
        $this->SetFont('Arial','B',10); $this->SetTextColor(...$this->cPrimary);
        $this->SetX($x3); $this->Cell($w3,7,L($this->folio),0,0,'C');

        $this->SetY($y0+$hH+5); $this->SetTextColor(...$this->cTexto);
    }

    public function Footer() {
        $this->SetY(-12);
        $this->SetFont('Arial','',7.5);
        $this->SetTextColor(...$this->cGris);
        $this->Cell(0, 5, L('Pagina ') . $this->PageNo() . L(' de {nb}'), 0, 0, 'C');
    }

    function RoundedRect($x,$y,$w,$h,$r,$style='') {
        $k=$this->k; $hp=$this->h;
        $op=($style=='F')?'f':(($style=='FD'||$style=='DF')?'B':'S');
        $a=4/3*(sqrt(2)-1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k));
        $xc=$x+$w-$r; $yc=$y+$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-$y)*$k));
        $this->_Arc($xc+$r*$a,$yc-$r,$xc+$r,$yc-$r*$a,$xc+$r,$yc);
        $xc=$x+$w-$r; $yc=$y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        $this->_Arc($xc+$r,$yc+$r*$a,$xc+$r*$a,$yc+$r,$xc,$yc+$r);
        $xc=$x+$r; $yc=$y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        $this->_Arc($xc-$r*$a,$yc+$r,$xc-$r,$yc+$r*$a,$xc-$r,$yc);
        $xc=$x+$r; $yc=$y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k));
        $this->_Arc($xc-$r,$yc-$r*$a,$xc-$r*$a,$yc-$r,$xc,$yc-$r);
        $this->_out($op);
    }
    function _Arc($x1,$y1,$x2,$y2,$x3,$y3) {
        $h=$this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x1*$this->k,($h-$y1)*$this->k,$x2*$this->k,($h-$y2)*$this->k,$x3*$this->k,($h-$y3)*$this->k));
    }

    public function seccion(string $titulo): void {
        $this->Ln(4); $y=$this->GetY();
        $this->SetFillColor(...$this->cSecondary); $this->Rect(10,$y,3,7,'F');
        $this->SetFillColor(245,247,249); $this->Rect(13,$y,193,7,'F');
        $this->SetDrawColor(...$this->cBorde); $this->SetLineWidth(0.2);
        $this->Rect(10,$y,196,7,'D');
        $this->SetFont('Arial','B',10); $this->SetTextColor(...$this->cPrimary);
        $this->SetXY(16,$y+1); $this->Cell(186,5,L(strtoupper($titulo)),0,1,'L');
        $this->Ln(2); $this->SetTextColor(...$this->cTexto);
    }

    public function tablaConceptos(array $filas): void {
        $this->seccion('Descripcion de Conceptos');
        $mL = 10;
        $w = [12, 88, 22, 24, 26, 24];
        
        $heads = ['#', L('Descripcion'), 'Cantidad', 'Unidad', 'P. Unitario', 'Subtotal'];
        $aligns = ['C', 'L', 'C', 'C', 'R', 'R'];
        $rowH = 9; // Altura reducida para ahorrar espacio

        // Cabecera
        $this->SetFillColor(...$this->cPrimary);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(...$this->cBorde);
        $this->SetLineWidth(0.2);
        $this->SetFont('Arial', 'B', 8);
        $this->SetX($mL);
        
        for ($k = 0; $k < count($heads); $k++) {
            $this->Cell($w[$k], $rowH, $heads[$k], 1, 0, $aligns[$k], true);
        }
        $this->Ln();
        
        // Datos
        $this->SetTextColor(...$this->cTexto);
        $this->SetFont('Arial', '', 8);
        $fill = false;
        $cnt = 1;

        foreach ($filas as $f) {
            $y0 = $this->GetY();
            
            if ($y0 + $rowH > $this->h - 45) { // Más espacio reservado para firmas
                $this->AddPage();
                $this->SetFillColor(...$this->cPrimary);
                $this->SetTextColor(255, 255, 255);
                $this->SetFont('Arial', 'B', 8);
                $this->SetX($mL);
                for ($k = 0; $k < count($heads); $k++) {
                    $this->Cell($w[$k], $rowH, $heads[$k], 1, 0, $aligns[$k], true);
                }
                $this->Ln();
                $this->SetTextColor(...$this->cTexto);
                $this->SetFont('Arial', '', 8);
                $y0 = $this->GetY();
            }

            $bgColor = $fill ? $this->cFilaPar : [255, 255, 255];
            $this->SetFillColor(...$bgColor);
            
            $this->SetX($mL);
            $this->Cell(array_sum($w), $rowH, '', 0, 0, 'L', true);
            $this->SetXY($mL, $y0);
            
            $this->SetFillColor(235, 245, 238);
            $this->Cell($w[0], $rowH, (string)$cnt, 'LRTB', 0, 'C', true);
            
            $desc = L($f['desc']);
            $maxW = $w[1] - 4;
            $originalDesc = $desc;
            while ($this->GetStringWidth($desc) > $maxW && strlen($desc) > 5) {
                $desc = mb_substr($desc, 0, -1, 'UTF-8');
            }
            if ($desc !== $originalDesc && $originalDesc !== '') {
                $desc = rtrim($desc, ' .') . '...';
            }
            $this->SetFillColor(...$bgColor);
            $this->Cell($w[1], $rowH, $desc, 'LRTB', 0, 'L', true);
            
            $this->Cell($w[2], $rowH, number_format($f['cant'], 2), 'LRTB', 0, 'C', true);
            $this->Cell($w[3], $rowH, L($f['unidad']), 'LRTB', 0, 'C', true);
            $this->Cell($w[4], $rowH, moneda($f['precio']), 'LRTB', 0, 'R', true);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell($w[5], $rowH, moneda($f['imp']), 'LRTB', 0, 'R', true);
            $this->SetFont('Arial', '', 8);
            
            $this->Ln();
            $fill = !$fill;
            $cnt++;
        }
        
        $this->SetDrawColor(...$this->cSecondary);
        $this->SetLineWidth(0.4);
        $this->SetX($mL);
        $this->Cell(array_sum($w), 0, '', 'T');
        $this->SetLineWidth(0.2);
    }

    // BLOQUE TOTALES - VERSIÓN COMPACTA (más pequeña)
    public function bloqueTotales(float $sub, float $iva, float $total): void {
        $this->Ln(4);
        $mL = 10;
        $totalW = 196;
        $boxW = 65;  // Más angosto
        $boxX = $mL + $totalW - $boxW;
        $yStart = $this->GetY();
        
        // Caja más pequeña
        $this->SetFillColor(248, 249, 250);
        $this->RoundedRect($boxX, $yStart, $boxW, 24, 2, 'F');
        $this->SetDrawColor(...$this->cBorde);
        $this->RoundedRect($boxX, $yStart, $boxW, 24, 2, 'D');
        
        $this->SetY($yStart + 3);
        $this->SetX($boxX + 6);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(...$this->cGris);
        $this->Cell(28, 5, 'SUBTOTAL:', 0, 0, 'L');
        $this->SetTextColor(...$this->cTexto);
        $this->Cell($boxW - 34, 5, moneda($sub), 0, 1, 'R');
        
        $this->SetX($boxX + 6);
        $this->SetTextColor(...$this->cGris);
        $this->Cell(28, 5, 'IVA (' . $this->tasaIva . '%):', 0, 0, 'L');
        $this->SetTextColor(...$this->cTexto);
        $this->Cell($boxW - 34, 5, moneda($iva), 0, 1, 'R');
        
        // Línea separadora delgada
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.2);
        $this->Line($boxX + 4, $this->GetY() + 1, $boxX + $boxW - 4, $this->GetY() + 1);
        
        $this->Ln(3);
        $this->SetX($boxX + 6);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(...$this->cPrimary);
        $this->Cell(28, 6, 'TOTAL:', 0, 0, 'L');
        $this->SetTextColor(...$this->cSecondary);
        $this->Cell($boxW - 34, 6, moneda($total), 0, 1, 'R');
        
        $this->SetTextColor(...$this->cTexto);
        $this->SetFont('Arial', '', 8);
    }

    // FIRMAS - más compactas
    public function firmas(string $emisorNombre, string $emisorDepto): void {
        $this->Ln(6);
        $this->SetFont('Arial', '', 8);
        $nombre = $emisorNombre ?: 'PROATAM S.A. DE C.V.';
        $ancho = 55;
        
        $lNombre = max(1, ceil($this->GetStringWidth(L($nombre)) / $ancho));
        $lDepto = max(1, ceil($this->GetStringWidth(L($emisorDepto)) / $ancho));
        $hCaja = max(28, 14 + ($lNombre * 4) + ($lDepto * 4)); // Altura reducida
        
        $yF = $this->GetY();
        
        $this->SetDrawColor(...$this->cBorde);
        $this->SetLineWidth(0.2);
        $this->SetFillColor(...$this->cFondo);
        $this->RoundedRect(25, $yF, 65, $hCaja, 2, 'FD');
        $this->RoundedRect(126, $yF, 65, $hCaja, 2, 'FD');
        
        $yL = $yF + 12;
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.4);
        $this->Line(30, $yL, 85, $yL);
        $this->Line(131, $yL, 186, $yL);
        
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(...$this->cTexto);
        $this->SetXY(30, $yL - 5);
        $this->Cell(55, 4, L('Emitido por'), 0, 0, 'C');
        $this->SetXY(131, $yL - 5);
        $this->Cell(55, 4, L('Autorizado por'), 0, 0, 'C');
        
        $yN = $yL + 3;
        $this->SetFont('Arial', '', 7.5);
        $this->SetTextColor(...$this->cGris);
        $this->SetXY(30, $yN);
        $this->MultiCell(55, 4, L($nombre), 0, 'C');
        $this->SetFont('Arial', '', 7);
        $this->SetXY(30, $this->GetY() + 1);
        $this->MultiCell(55, 3.5, L($emisorDepto), 0, 'C');
        
        $this->SetFont('Arial', '', 7.5);
        $this->SetXY(131, $yN);
        $this->MultiCell(55, 4, 'Director / Responsable', 0, 'C');
        $this->SetFont('Arial', '', 7);
        $this->SetXY(131, $this->GetY() + 2);
        $this->MultiCell(55, 3.5, 'Nombre y Firma del Cliente', 0, 'C');
    }
}

// ══════════════════════════════════════════════════════════════
//  CONSTRUIR PDF
// ══════════════════════════════════════════════════════════════
$pdf = new CotizacionPDF($folio, $fechaRef);
$pdf->tasaIva = $tasaIva;
$pdf->setEntityConfig($entityConfig, $assetsDir);
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 22); // Margen inferior más pequeño
$pdf->AddPage();

// Info general (más compacta)
$pdf->seccion('Informacion General');
$wL = 32; $wV = 65; $rowH = 5; $mL = 10;
$infoRows = [
    ['Atencion' => $atencion, 'Compania' => $compania],
    ['Lugar'    => $lugar,    'Fecha'    => $fechaRef],
    ['Vigencia' => $vigencia, 'Moneda'   => $monedaTxt],
    ['Entidad'  => $entityConfig['name'], 'Tiempo de ejecucion' => $tiempo],
];
foreach ($infoRows as $row) {
    $y = $pdf->GetY();
    $x = $mL;
    foreach ($row as $lbl => $val) {
        $lblW = ($lbl === 'Tiempo de ejecucion') ? 42 : $wL;
        $valW = ($lbl === 'Tiempo de ejecucion') ? 55 : $wV;
        $pdf->SetXY($x, $y);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(17, 52, 86);
        $pdf->Cell($lblW, $rowH, L($lbl . ':'), 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetXY($x + $lblW, $y);
        $pdf->Cell($valW, $rowH, L($val), 0, 0, 'L');
        $x += $lblW + $valW + 3;
    }
    $pdf->SetY($y + $rowH);
}
$pdf->Ln(3);

$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(50, 50, 50);
$pdf->SetX(10);
$pdf->MultiCell(196, 4.5, L('Por medio de la presente, me permito presentar a su consideracion la siguiente propuesta, correspondiente a los trabajos y servicios solicitados. La presente cotizacion incluye los alcances y condiciones generales para su adecuada ejecucion.'), 0, 'J');

$pdf->tablaConceptos($filas);
$pdf->bloqueTotales($subtotal, $iva, $total);

// Alcances (más compactos)
$pdf->seccion('Alcances');
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(40, 40, 40);
$bullet = chr(149);
foreach ($alcSelec as $clave) {
    if (!isset($alcancesMap[$clave])) continue;
    $pdf->SetX(14);
    $pdf->Cell(5, 4.5, $bullet, 0, 0, 'C');
    $pdf->MultiCell(184, 4.5, L($alcancesMap[$clave]), 0, 'L');
}
if ($alcExtras) {
    $pdf->SetX(14);
    $pdf->Cell(5, 4.5, $bullet, 0, 0, 'C');
    $pdf->MultiCell(184, 4.5, L($alcExtras), 0, 'L');
}

// Condiciones generales (más compactas)
if ($formaPago || $notas) {
    $pdf->seccion('Condiciones Generales');
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(40, 40, 40);
    if ($formaPago) {
        $pdf->SetX(14);
        $pdf->Cell(5, 4.5, $bullet, 0, 0, 'C');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(17, 52, 86);
        $pdf->Cell(40, 4.5, L('Forma de pago:'), 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->MultiCell(144, 4.5, L($formaPago), 0, 'L');
    }
    if ($notas) {
        $pdf->SetX(14);
        $pdf->Cell(5, 4.5, $bullet, 0, 0, 'C');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(17, 52, 86);
        $pdf->Cell(40, 4.5, L('Observaciones:'), 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->MultiCell(144, 4.5, L($notas), 0, 'L');
    }
}

$pdf->firmas($emisorNombre, $emisorDepto);

// ── ENVIAR PDF ────────────────────────────────────────────────
if (ob_get_length()) ob_end_clean();
$filename = 'Cotizacion_' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $folio) . '.pdf';
$isInline = isset($_GET['inline']) && $_GET['inline'] === '1';
$pdf->Output($isInline ? 'I' : 'D', $filename);
exit;

