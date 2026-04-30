<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();

require_once __DIR__ . "/../conexion.php";

// Verificar que se proporcionÃ³ un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID de orden de compra no proporcionado");
}

$id = intval($_GET['id']);

// Obtener datos de la orden de compra
$sql = "SELECT oc.*, 
               e.nombre AS entidad, 
               u.nombres, u.apellidos, 
               c.nombre AS categoria,
               p.razon_social AS proveedor, p.rfc AS rfc_proveedor, p.direccion AS direccion_proveedor,
               p.telefono AS telefono_proveedor, p.email AS email_proveedor,
               r.folio AS folio_requisicion,
               pro.nombre_proyecto,
               ob.nombre_obra
        FROM ordenes_compra oc
        JOIN entidades e ON oc.entidad_id = e.id
        JOIN usuarios u ON oc.solicitante_id = u.id
        JOIN categorias c ON oc.categoria_id = c.id
        JOIN proveedores p ON oc.proveedor_id = p.id
        LEFT JOIN requisiciones r ON oc.requisicion_id = r.id
        LEFT JOIN proyectos pro ON oc.proyecto_id = pro.id
        LEFT JOIN obras ob ON oc.obra_id = ob.id
        WHERE oc.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$orden_compra = $stmt->get_result()->fetch_assoc();

if (!$orden_compra) {
    die("Orden de compra no encontrada");
}

// Obtener items de la orden de compra
$sql_items = "SELECT oci.*, 
                     ps.nombre AS producto, 
                     ps.tipo, 
                     un.nombre AS unidad
              FROM orden_compra_items oci
              LEFT JOIN productos_servicios ps ON oci.producto_id = ps.id
              LEFT JOIN unidades un ON oci.unidad_id = un.id
              WHERE oci.orden_compra_id = ?
              ORDER BY oci.id ASC";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $id);
$stmt_items->execute();
$items = $stmt_items->get_result();

// Incluir FPDF
require_once(__DIR__ . '/../fpdf/fpdf186/fpdf.php');

// â”€â”€â”€ ConfiguraciÃ³n de entidades â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Para agregar una nueva entidad: aÃ±ade un elemento al array con su nombre exacto
// (en mayÃºsculas), colores primario y secundario en RGB, y el nombre del logo.
function getEntityConfig(string $entidad): array
{
    $entidades = [
        'PROATAM' => [
            'primary'   => [17,  52,  86],   // #113456
            'secondary' => [63,  117, 85],   // #3f7555
            'logo'      => 'proatam.png',
        ],
        'LUBYCOMP' => [
            'primary'   => [36,  57,  68],   // #243944
            'secondary' => [208, 176, 89],   // #d0b059
            'logo'      => 'lubycomp.png',
        ],
        'DAVID GOMEZ' => [
            'primary'   => [251, 174, 23],   // #fbae17
            'secondary' => [0,   0,   0],    // #000000
            'logo'      => 'davidg.png',
        ],
        'INGETAM' => [
            'primary'   => [239, 163, 54],   // #efa336
            'secondary' => [0,   0,   0],    // #000000
            'logo'      => 'ingetam.png',
        ],
    ];

    $key = strtoupper(trim($entidad));

    // Devuelve la config de la entidad o PROATAM como fallback
    return $entidades[$key] ?? $entidades['PROATAM'];
}

$entityConfig = getEntityConfig($orden_compra['entidad']);
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

// Clase personalizada con diseÃ±o profesional y soporte UTF-8
class PDF extends FPDF
{
    private $primaryColor;
    private $secondaryColor;
    private $logoPath;
    private $lightBg = array(248, 249, 250);          // Fondo suave
    private $borderColor = array(206, 212, 218);      // Bordes sutiles
    private $textGray = array(73, 80, 87);            // Texto secundario

    public function setEntityConfig(array $config, string $assetsDir): void
    {
        $this->primaryColor   = $config['primary'];
        $this->secondaryColor = $config['secondary'];
        $this->logoPath       = $assetsDir . '/' . $config['logo'];
    }
    
    // FunciÃ³n para codificar texto a ISO-8859-1 (compatible con FPDF)
    function encodeText($text)
    {
        // Normalizar (quitar acentos) y convertir de UTF-8 a ISO-8859-1 para FPDF
        $text = $this->removeAccents($text);
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        } else {
            // Fallback para sistemas sin mbstring
            return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
        }
    }

    // Remueve acentos y caracteres especiales comunes cuantificando a su forma sin tilde
    private function removeAccents($str)
    {
        if (empty($str)) return $str;
        $unwanted_array = array(
            'Ã' => 'A', 'Ã€' => 'A', 'Ã‚' => 'A', 'Ã„' => 'A', 'Ãƒ' => 'A', 'Ã…' => 'A',
            'Ã¡' => 'a', 'Ã ' => 'a', 'Ã¢' => 'a', 'Ã¤' => 'a', 'Ã£' => 'a', 'Ã¥' => 'a',
            'Ã‰' => 'E', 'Ãˆ' => 'E', 'ÃŠ' => 'E', 'Ã‹' => 'E',
            'Ã©' => 'e', 'Ã¨' => 'e', 'Ãª' => 'e', 'Ã«' => 'e',
            'Ã' => 'I', 'ÃŒ' => 'I', 'ÃŽ' => 'I', 'Ã' => 'I',
            'Ã­' => 'i', 'Ã¬' => 'i', 'Ã®' => 'i', 'Ã¯' => 'i',
            'Ã“' => 'O', 'Ã’' => 'O', 'Ã”' => 'O', 'Ã–' => 'O', 'Ã•' => 'O',
            'Ã³' => 'o', 'Ã²' => 'o', 'Ã´' => 'o', 'Ã¶' => 'o', 'Ãµ' => 'o',
            'Ãš' => 'U', 'Ã™' => 'U', 'Ã›' => 'U', 'Ãœ' => 'U',
            'Ãº' => 'u', 'Ã¹' => 'u', 'Ã»' => 'u', 'Ã¼' => 'u',
            'Ã‘' => 'N', 'Ã±' => 'n',
            'Ã‡' => 'C', 'Ã§' => 'c'
        );
        $str = strtr($str, $unwanted_array);
        return $str;
    }
    
    // Cell con soporte para caracteres especiales
    function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        parent::Cell($w, $h, $this->encodeText($txt), $border, $ln, $align, $fill, $link);
    }
    
    // MultiCell con soporte para caracteres especiales
    function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
    {
        parent::MultiCell($w, $h, $this->encodeText($txt), $border, $align, $fill);
    }
    
    // Write con soporte para caracteres especiales
    function Write($h, $txt, $link = '')
    {
        parent::Write($h, $this->encodeText($txt), $link);
    }

   // Encabezado 
function Header()
{
    global $orden_compra;

    // Franja verde superior
        $this->SetY(0);
        $this->SetFillColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        $this->Rect(0, $this->GetY(), 210, 2, 'F');
    
    // Eliminamos el Rect completo y dibujamos solo las lÃ­neas necesarias
    $this->SetLineWidth(0.5);
    $this->SetDrawColor(0, 0, 0);
    
    // LÃ­neas verticales del encabezado
    $this->Line(10, 10, 10, 42); // LÃ­nea vertical izquierda
    $this->Line(200, 10, 200, 42); // LÃ­nea vertical derecha
    
    // LÃ­nea horizontal SUPERIOR del encabezado
    $this->Line(10, 10, 200, 10);
    
    // LÃ­nea vertical divisora (despuÃ©s del logo)
    $this->Line(80, 10, 80, 42);
    
    // LÃ­nea vertical divisora (antes del cÃ³digo)
    $this->Line(145, 10, 145, 42);
    
    // LÃ­neas horizontales para separar las secciones
    $this->Line(80, 27.5, 200, 27.5); // LÃ­nea horizontal superior derecha
    
    // Logo (dinÃ¡mico segÃºn entidad)
    if (!empty($this->logoPath) && file_exists($this->logoPath)) {
        $this->Image($this->logoPath, 15, 20, 60);
    }
    
    // SecciÃ³n central - Tipo de Documento
    $this->SetFont('Arial', 'B', 11);
    $this->SetXY(80, 13);
    $this->Cell(65, 6, 'Tipo de Documento:', 0, 1, 'C');
    $this->SetX(80);
    $this->Cell(65, 6, 'Formato', 0, 1, 'C');
    
    // Fecha de elaboraciÃ³n
    $this->SetFont('Arial', 'B', 9);
    $this->SetXY(80, 30);
    $this->Cell(65, 5, 'Fecha de ElaboraciÃ³n:', 0, 1, 'C');
    $this->SetFont('Arial', '', 9);
    $this->SetX(80);
    $fecha_formato = date('d-M-Y', strtotime($orden_compra['fecha_solicitud']));
    $fecha_formato = strtoupper($fecha_formato);
    $this->Cell(65, 5, $fecha_formato, 0, 1, 'C');
    
    // SecciÃ³n derecha - CÃ³digo y datos
    $this->SetFont('Arial', 'B', 9);
    $this->SetXY(145, 13);
    $this->Cell(55, 5, 'CÃ³digo: POARH-03-4', 0, 1, 'C');
    
    $this->SetX(145);
    $this->Cell(55, 5, 'RevisiÃ³n: 01', 0, 1, 'C');
    
    // Folio de la orden
    $this->SetFont('Arial', 'B', 9);
    $this->SetXY(145, 30);
    $folio_text = 'FOLIO: ' . (isset($orden_compra['folio']) ? $orden_compra['folio'] : 'N/A');
    $this->Cell(55, 7, $folio_text, 0, 1, 'C');
    
    // LÃ­nea INFERIOR 
    $this->SetLineWidth(0.5);
    $this->Line(10, 42, 200, 42);
    
    $this->Ln(3);
}

    // Pie de pÃ¡gina
    function Footer()
    {
        // Franja verde superior
        $this->SetY(-20);
        $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Rect(0, $this->GetY(), 210, 2, 'F');
        
        // InformaciÃ³n del pie
        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($this->textGray[0], $this->textGray[1], $this->textGray[2]);
        
        // Fecha de generaciÃ³n (izquierda)
        $this->SetX(15);
        $this->Cell(60, 5, 'Generado: ' . date('d/m/Y H:i'), 0, 0, 'L');

         // NÃºmero de pÃ¡gina
        $this->SetX(145);
        $this->Cell(55, 5, 'PÃ¡gina ' . $this->PageNo() . ' de {nb}', 0, 1, 'C');
        
        // Barra azul marino inferior
        $this->SetY(-10);
        $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Rect(0, $this->GetY() + 10, 210, 10, 'F');
    }

    // RectÃ¡ngulo redondeado
    function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
        $xc = $x+$w-$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));
        $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
        $xc = $x+$w-$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x+$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
        $xc = $x+$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
        $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }

    // TÃ­tulo de secciÃ³n
    function SectionTitle($title)
    {
        // Barra lateral verde
        $this->SetFillColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        $this->Rect($this->GetX(), $this->GetY(), 3, 7, 'F');
        
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Cell(3, 7, '', 0, 0);
        $this->Cell(0, 7, $this->encodeText($title), 0, 1, 'L');
        $this->SetTextColor(0);
        $this->Ln(2);
    }

    // Card de informaciÃ³n (opcionalmente dibuja el fondo).
    // $draw: si es false, no dibuja el rectÃ¡ngulo; $padding controla el espacio interno vertical.
    function InfoCard($height, $draw = true, $padding = 4)
    {
        $y = $this->GetY();

        if ($draw) {
            // Fondo de la card
            $this->SetFillColor($this->lightBg[0], $this->lightBg[1], $this->lightBg[2]);
            $this->RoundedRect(15, $y, 180, $height, 3, 'F');

            // Borde sutil
            $this->SetDrawColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
            $this->SetLineWidth(0.2);
            $this->RoundedRect(15, $y, 180, $height, 3, 'D');
        }

        $this->SetY($y + $padding);
    }

    // Campo de informaciÃ³n
    function InfoField($label, $value, $width = 90, $newLine = false)
    {
        // Ajuste de anchos para etiqueta y valor. Si el ancho total es pequeÃ±o, usar etiqueta mÃ¡s estrecha.
        $labelWidth = ($width > 60) ? 40 : 28;
        $valueWidth = max(20, $width - $labelWidth);

        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($this->textGray[0], $this->textGray[1], $this->textGray[2]);
        $this->Cell($labelWidth, 5, $this->encodeText($label), 0, 0);

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(0, 0, 0);

        // Si el valor es demasiado ancho o se solicita salto de lÃ­nea, usar MultiCell para que haga wrap correctamente.
        $displayValue = $this->encodeText($value);
        if ($newLine || $this->GetStringWidth($displayValue) > $valueWidth) {
            // Guardar X actual para posicionar al final correctamente
            $xBefore = $this->GetX();
            $yBefore = $this->GetY();

            // Colocar el valor en MultiCell con el ancho restante
            $this->MultiCell($valueWidth, 5, $displayValue, 0, 'L', false);

            // Mover el cursor al final de la zona (alineado a la derecha del card)
            $this->SetXY($xBefore + $width, $this->GetY());
        } else {
            $this->Cell($valueWidth, 5, $displayValue, 0, $newLine ? 1 : 0);
        }
    }

    // Tabla moderna
    function TablaItems($header, $data)
    {
        // Cabecera
        $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        $this->SetLineWidth(0.2);
        $this->SetFont('Arial', 'B', 9);
        
        $w = array(12, 78, 22, 23, 27, 28);
        
        // Cabecera
        for($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 9, $this->encodeText($header[$i]), 1, 0, 'C', true);
        }
        $this->Ln();
        
        // Contenido
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(0);
        
        $fill = false;
        $contador = 1;
        $total_general = 0;
        
        foreach($data as $row) {
            if($fill) {
                $this->SetFillColor($this->lightBg[0], $this->lightBg[1], $this->lightBg[2]);
            } else {
                $this->SetFillColor(255, 255, 255);
            }
            
            // NÃºmero con fondo verde claro
            if($contador % 2 == 1) {
                $this->SetFillColor(235, 245, 238);
            }
            $this->Cell($w[0], 7, $contador, 'LR', 0, 'C', true);
            
            $this->SetFillColor($fill ? $this->lightBg[0] : 255, $fill ? $this->lightBg[1] : 255, $fill ? $this->lightBg[2] : 255);
            $this->Cell($w[1], 7, $this->encodeText($row['descripcion']), 'LR', 0, 'L', true);
            $this->Cell($w[2], 7, number_format($row['cantidad'], 2), 'LR', 0, 'R', true);
            $this->Cell($w[3], 7, $this->encodeText($row['unidad']), 'LR', 0, 'C', true);
            $this->Cell($w[4], 7, '$' . number_format($row['precio_unitario'] ?? 0, 2), 'LR', 0, 'R', true);
            
            $subtotal = ($row['cantidad'] * ($row['precio_unitario'] ?? 0));
            $total_general += $subtotal;
            
            // Subtotal con fuente mÃ¡s destacada
            $this->SetFont('Arial', 'B', 9);
            $this->Cell($w[5], 7, '$' . number_format($subtotal, 2), 'LR', 0, 'R', true);
            $this->SetFont('Arial', '', 9);
            $this->Ln();
            
            $fill = !$fill;
            $contador++;
        }
        
        // LÃ­nea de cierre con color
        $this->SetDrawColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        $this->SetLineWidth(0.5);
        $this->Cell(array_sum($w), 0, '', 'T');
        
        return $total_general;
    }

    // Card de totales
    function TotalesCard($subtotal, $iva, $total)
    {
        $yInicio = $this->GetY();
        
        // Card de totales
        $this->SetFillColor($this->lightBg[0], $this->lightBg[1], $this->lightBg[2]);
        $this->RoundedRect(125, $yInicio, 70, 30, 3, 'F');
        $this->SetDrawColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        $this->SetLineWidth(0.2);
        $this->RoundedRect(125, $yInicio, 70, 30, 3, 'D');
        
        // Contenido dentro del card
        $this->SetY($yInicio + 4);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($this->textGray[0], $this->textGray[1], $this->textGray[2]);
        
        // Subtotal
        $this->SetX(128);
        $this->Cell(32, 5, 'SUBTOTAL:', 0, 0, 'L');
        $this->SetTextColor(0);
        $this->Cell(35, 5, '$' . number_format($subtotal, 2), 0, 1, 'R');
        
        // IVA
        $this->SetX(128);
        $this->SetTextColor($this->textGray[0], $this->textGray[1], $this->textGray[2]);
        // Calcular el porcentaje de IVA realmente utilizado
        $iva_percent = ($subtotal > 0 && $iva > 0) ? round(($iva / $subtotal) * 100, 2) : 0;
        $iva_label = 'IVA (' . $iva_percent . '%):';
        $this->Cell(32, 5, $iva_label, 0, 0, 'L');
        $this->SetTextColor(0);
        $this->Cell(35, 5, '$' . number_format($iva, 2), 0, 1, 'R');
        
        // Separador
        $this->SetDrawColor($this->borderColor[0], $this->borderColor[1], $this->borderColor[2]);
        $this->Line(128, $this->GetY() + 2, 192, $this->GetY() + 2);
        
        $this->Ln(3);
        
        // Total 
        $yTotal = $this->GetY();
        
        $this->SetY($yTotal + 1);
        $this->SetX(128);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(32, 6, 'TOTAL:', 0, 0, 'L');
        $this->Cell(32, 6, '$' . number_format($total, 2), 0, 1, 'R');
        
        $this->SetTextColor(0);
    }
}

// Crear PDF
$pdf = new PDF();
$pdf->setEntityConfig($entityConfig, __DIR__ . '/../assets/img');
$pdf->AliasNbPages();
$pdf->AddPage();

// InformaciÃ³n bÃ¡sica
$pdf->SetY(63);

// InformaciÃ³n del proveedor
$pdf->SectionTitle('INFORMACION GENERAL');

$lineas_info = 3;
if ($orden_compra['nombre_proyecto']) $lineas_info++;
if ($orden_compra['nombre_obra']) $lineas_info++;
$altura_info = $lineas_info * 5 + 8;

$pdf->InfoCard($altura_info, false, 2);
$pdf->SetX(22);

$pdf->InfoField('Entidad:', $orden_compra['entidad'], 85);
$pdf->InfoField('Estado:', ucfirst($orden_compra['estado']), 85, true);

$pdf->SetX(22);
$pdf->InfoField('Categoria:', $orden_compra['categoria'], 85);
$pdf->InfoField('Solicitante:', $orden_compra['nombres'] . ' ' . $orden_compra['apellidos'], 85, true);

$pdf->SetX(22);
$folio_requisicion = $orden_compra['folio_requisicion'] ? $orden_compra['folio_requisicion'] : 'N/A';
$pdf->InfoField('Requisicion:', $folio_requisicion, 170, true);

if ($orden_compra['nombre_proyecto']) {
    $pdf->SetX(22);
    $pdf->InfoField('Proyecto:', $orden_compra['nombre_proyecto'], 170, true);
}

if ($orden_compra['nombre_obra']) {
    $pdf->SetX(22);
    $pdf->InfoField('Obra:', $orden_compra['nombre_obra'], 170, true);
}

$pdf->Ln(6);

// InformaciÃ³n del proveedor
$pdf->SectionTitle('INFORMACION DEL PROVEEDOR');

$lineas_proveedor = 1;
if ($orden_compra['rfc_proveedor'] || $orden_compra['telefono_proveedor']) $lineas_proveedor++;
if ($orden_compra['email_proveedor']) $lineas_proveedor++;
if ($orden_compra['direccion_proveedor']) $lineas_proveedor++;
$altura_proveedor = $lineas_proveedor * 5 + 8;

$pdf->InfoCard($altura_proveedor, false, 2);
$pdf->SetX(22);
$pdf->InfoField('Nombre:', $orden_compra['proveedor'], 170, true);

if ($orden_compra['rfc_proveedor'] || $orden_compra['telefono_proveedor']) {
    $pdf->SetX(22);
    if ($orden_compra['rfc_proveedor']) {
        $pdf->InfoField('RFC:', $orden_compra['rfc_proveedor'], 85);
    }
    if ($orden_compra['telefono_proveedor']) {
        $pdf->InfoField('Telefono:', $orden_compra['telefono_proveedor'], 85, true);
    } else {
        $pdf->Ln();
    }
}

if ($orden_compra['email_proveedor']) {
    $pdf->SetX(22);
    $pdf->InfoField('Correo electronico: ', $orden_compra['email_proveedor'], 170, true);
}

if ($orden_compra['direccion_proveedor']) {
    $pdf->SetX(22);
    $pdf->InfoField('Direccion:', $orden_compra['direccion_proveedor'], 170, true);
}

$pdf->Ln(6);

// Tabla de items
$pdf->SectionTitle('DETALLES DE LA ORDEN DE COMPRA');

$header = array('#', 'Descripcion', 'Cantidad', 'Unidad', 'P. Unitario', 'Subtotal');

$data = array();
while($item = $items->fetch_assoc()) {
    $descripcion = !empty($item['producto']) ? $item['producto'] : $item['descripcion'];
    $unidad = $item['unidad'] ? $item['unidad'] : 'PZA';
    
    if (strlen($descripcion) > 50) {
        $descripcion = substr($descripcion, 0, 47) . '...';
    }
    
    $data[] = array(
        'descripcion' => $descripcion,
        'cantidad' => $item['cantidad'],
        'unidad' => $unidad,
        'precio_unitario' => $item['precio_unitario'],
        'subtotal' => $item['cantidad'] * $item['precio_unitario']
    );
}

$total_calculado = $pdf->TablaItems($header, $data);

// Totales con mÃ©todo mejorado
$pdf->Ln(6);
$pdf->TotalesCard(
    $orden_compra['subtotal'] ?: $total_calculado,
    $orden_compra['iva'],
    $orden_compra['total']
);

// DescripciÃ³n y observaciones
if (!empty($orden_compra['descripcion'])) {
    $pdf->Ln(10);
    $pdf->SectionTitle('DESCRIPCIÃ“N');
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, $orden_compra['descripcion']);
}

if (!empty($orden_compra['observaciones'])) {
    $pdf->Ln(5);
    $pdf->SectionTitle('OBSERVACIONES');
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, $orden_compra['observaciones']);
}

// Firmas con diseÃ±o moderno
$pdf->SetY(-60);
$pdf->Ln(8);

// Cuadros de firma
$yFirma = $pdf->GetY();

$pdf->SetDrawColor(206, 212, 218);
$pdf->SetLineWidth(0.2);
$pdf->SetFillColor(248, 249, 250);
$pdf->RoundedRect(25, $yFirma, 60, 25, 2, 'FD');
$pdf->RoundedRect(125, $yFirma, 60, 25, 2, 'FD');

// LÃ­neas de firma
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.5);
$pdf->Line(30, $yFirma + 15, 80, $yFirma + 15);
$pdf->Line(130, $yFirma + 15, 180, $yFirma + 15);

$pdf->SetY($yFirma + 16);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(90, 4, 'Solicitado por', 0, 0, 'C');
$pdf->Cell(110, 4, 'Autorizado por', 0, 1, 'C');

$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(73, 80, 87);

// Primera fila de nombres
$pdf->Cell(90, 4, 'Diana Giselle RamÃ­rez Ãvila', 0, 0, 'C');
$pdf->Cell(110, 4, 'Director/Responsable', 0, 1, 'C');

// Output
$filename = 'Orden_Compra_' . $orden_compra['folio'] . '.pdf';

if (isset($_GET['download'])) {
    $pdf->Output('D', $filename);
} else {
    $pdf->Output('I', $filename);
}

$conn->close();
?>


