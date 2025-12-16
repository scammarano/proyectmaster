<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;

$type = $_GET['type'];
$project_id = (int)$_GET['project_id'];

ob_start();
include __DIR__ . "/report_{$type}.php";
$html = ob_get_clean();

$pdf = new Dompdf();
$pdf->loadHtml($html);
$pdf->setPaper('A4');
$pdf->render();
$pdf->stream("reporte_{$type}_{$project_id}.pdf");
