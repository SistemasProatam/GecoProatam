<?php
require('../fpdf.php');

class PDF extends FPDF
{
function Header()
{
	global $title;

	// Arial bold 15
	$this->SetFont('Arial','B',15);
	// Calculamos ancho y posici�n del t�tulo.
	$w = $this->GetStringWidth($title)+6;
	$this->SetX((210-$w)/2);
	// Colores de los bordes, fondo y texto
	$this->SetDrawColor(0,80,180);
	$this->SetFillColor(230,230,0);
	$this->SetTextColor(220,50,50);
	// Ancho del borde (1 mm)
	$this->SetLineWidth(1);
	// T�tulo
	$this->Cell($w,9,$title,1,1,'C',true);
	// Salto de l�nea
	$this->Ln(10);
}

function Footer()
{
	// Posici�n a 1,5 cm del final
	$this->SetY(-15);
	// Arial it�lica 8
	$this->SetFont('Arial','I',8);
	// Color del texto en gris
	$this->SetTextColor(128);
	// N�mero de p�gina
	$this->Cell(0,10,'P�gina '.$this->PageNo(),0,0,'C');
}

function ChapterTitle($num, $label)
{
	// Arial 12
	$this->SetFont('Arial','',12);
	// Color de fondo
	$this->SetFillColor(200,220,255);
	// T�tulo
	$this->Cell(0,6,"Cap�tulo $num : $label",0,1,'L',true);
	// Salto de l�nea
	$this->Ln(4);
}

function ChapterBody($file)
{
	// Leemos el fichero
	$txt = file_get_contents($file);
	// Times 12
	$this->SetFont('Times','',12);
	// Imprimimos el texto justificado
	$this->MultiCell(0,5,$txt);
	// Salto de l�nea
	$this->Ln();
	// Cita en it�lica
	$this->SetFont('','I');
	$this->Cell(0,5,'(fin del extracto)');
}

function PrintChapter($num, $title, $file)
{
	$this->AddPage();
	$this->ChapterTitle($num,$title);
	$this->ChapterBody($file);
}
}

$pdf = new PDF();
$title = '20000 Leguas de Viaje Submarino';
$pdf->SetTitle($title);
$pdf->SetAuthor('Julio Verne');
$pdf->PrintChapter(1,'UN RIZO DE HUIDA','20k_c1.txt');
$pdf->PrintChapter(2,'LOS PROS Y LOS CONTRAS','20k_c2.txt');
$pdf->Output();
?>

