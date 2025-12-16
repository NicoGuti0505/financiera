<?php
// Incluir configuración y autoload correctamente
$pathParts = explode('\\', dirname(__FILE__));
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
require_once str_repeat('../', $levelsUp) . 'config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Consulta SQL con conexión desde $conn (de config.php)
$sql = "
SELECT 
    s.rad_via,
    e.fecha_solicitud,
    a.tipo_documento,
    a.numero_documento,
    CONCAT(s.nombre,' ', s.segundo_n, ' ', s.primer_p, ' ', s.segundo_p) AS nombre_completo,
    a.celular_principal,
    a.correo_principal,
    m.descripcion_dep,
    m.descripcion_mun,
    a.direccion_Residencia_cargue,
    e.estado_proceso,
    e.observacion,
    e.fecha_estado,
    s.val_rembolso,
    s.numero_identificacion,
    b.descripcion AS banco,
    t.descripcion AS tipo_cuenta,
    s.numero_cuenta,
    CONCAT(s.nombre,' ', s.segundo_n, ' ', s.primer_p, ' ', s.segundo_p) AS titular_cuenta
FROM solicitudes s
JOIN evento_solicitudes e ON s.radicado = e.radicado
JOIN afiliado a ON s.numero_identificacion_titular = a.numero_documento
JOIN municipio m ON a.codigo_dane_municipio_atencion = m.id
JOIN banco b ON s.entidad_bancaria = b.id
JOIN tipo_cuenta t ON s.tipo_cuenta = t.id
ORDER BY TRY_CAST(s.rad_via AS INT)
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Encabezados agrupados
$sheet->setCellValue('A1', 'CONSECUTIVO');
$sheet->setCellValue('B1', 'FECHA SOLICITUD');
$sheet->setCellValue('C1', 'INFORMACIÓN DEL USUARIO');
$sheet->mergeCells('C1:J1');
$sheet->setCellValue('K1', 'REMISIÓN DEL SERVICIO');
$sheet->mergeCells('K1:L1');
$sheet->setCellValue('M1', 'SOLICITUD');
$sheet->mergeCells('M1:O1');
$sheet->setCellValue('P1', 'RECONOCIMIENTO DE REEMBOLSO POR VIÁTICOS');
$sheet->mergeCells('P1:T1');

// Subencabezados
$subHeaders = [
    'A2' => 'CONSECUTIVO',
    'B2' => 'FECHA SOLICITUD',
    'C2' => 'TIPO DE DOCUMENTO',
    'D2' => 'NUMERO DE DOCUMENTO USUARIO',
    'E2' => 'NOMBRE DEL USUARIO',
    'F2' => 'NUMERO DE CONTACTO',
    'G2' => 'CORREO ELECTRÓNICO',
    'H2' => 'DEPARTAMENTO DE RESIDENCIA',
    'I2' => 'MUNICIPIO DE RESIDENCIA',
    'J2' => 'DIRECCIÓN DE RESIDENCIA',
    'K2' => 'FECHA DE PROGRAMACIÓN',
    'L2' => 'MUNICIPIO DE ATENCIÓN',
    'M2' => 'ESTADO',
    'N2' => 'OBSERVACIONES',
    'O2' => 'FECHA CAMBIO DE ESTADO',
    'P2' => 'VALOR TOTAL SOLICITADO',
    'Q2' => 'NUMERO DE DOCUMENTO TITULAR CUENTA BANCARIA',
    'R2' => 'ENTIDAD BANCARIA',
    'S2' => 'TIPO DE CUENTA BANCARIA',
    'T2' => 'N° DE CUENTA BANCARIA',
    'U2' => 'NOMBRE DEL TITULAR DE LA CUENTA BANCARIA',
];

foreach ($subHeaders as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// Estilos de encabezado
$headerStyle = [
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FFBDD7EE'],
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
    ],
];

$sheet->getStyle('A1:U2')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(30);
$sheet->getRowDimension(2)->setRowHeight(45);

// Cargar los datos
$row = 3;
while ($d = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $sheet->setCellValue("A{$row}", $d['rad_via']);
    $sheet->setCellValue("B{$row}", $d['fecha_solicitud'] instanceof DateTime ? $d['fecha_solicitud']->format('Y-m-d') : '');
    $sheet->setCellValue("C{$row}", $d['tipo_documento']);
    $sheet->setCellValue("D{$row}", $d['numero_documento']);
    $sheet->setCellValue("E{$row}", $d['nombre_completo']);
    $sheet->setCellValue("F{$row}", $d['celular_principal']);
    $sheet->setCellValue("G{$row}", $d['correo_principal']);
    $sheet->setCellValue("H{$row}", $d['descripcion_dep']);
    $sheet->setCellValue("I{$row}", $d['descripcion_mun']);
    $sheet->setCellValue("J{$row}", $d['direccion_Residencia_cargue']);
    $sheet->setCellValue("K{$row}", $d['fecha_solicitud'] instanceof DateTime ? $d['fecha_solicitud']->format('Y-m-d') : '');
    $sheet->setCellValue("L{$row}", $d['descripcion_mun']);
    $sheet->setCellValue("M{$row}", $d['estado_proceso']);
    $sheet->setCellValue("N{$row}", $d['observacion']);
    $sheet->setCellValue("O{$row}", $d['fecha_estado'] instanceof DateTime ? $d['fecha_estado']->format('Y-m-d') : '');
    $sheet->setCellValue("P{$row}", $d['val_rembolso']);
    $sheet->setCellValue("Q{$row}", $d['numero_identificacion']);
    $sheet->setCellValue("R{$row}", $d['banco']);
    $sheet->setCellValue("S{$row}", $d['tipo_cuenta']);
    $sheet->setCellValue("T{$row}", $d['numero_cuenta']);
    $sheet->setCellValue("U{$row}", $d['titular_cuenta']);
    $row++;
}

// Autoajustar columnas
foreach (range('A', 'U') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Salida del archivo
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="viaticos_formato.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
ob_clean();
flush();
$writer->save('php://output');
exit;
