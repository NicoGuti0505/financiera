<?php

// Ruta al archivo de configuración
$pathParts = explode('\\', dirname(__FILE__));
$levelsUp = count($pathParts) - count(array_slice($pathParts, 0, array_search('php', $pathParts)));
require_once str_repeat('../', $levelsUp) . 'config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : null;
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : null;

if (!$start_date || !$end_date) {
    die("Error: No se recibieron las fechas correctamente.");
}

$estados = ['Subsanacion', 'aprobado', 'rechazado'];

$query = "SELECT DISTINCT
            CONCAT(a.primer_nombre, ' ', a.segundo_nombre, ' ', a.primer_apellido, ' ', a.segundo_apellido) AS usuario,
            a.tipo_documento,
            a.celular_principal,
            m.descripcion_dep,
            m.descripcion_mun,
            a.region,
            a.nombre_prestador,
            s.radicado,
            s.numero_identificacion_titular,
            CONCAT(s.nombre, ' ', s.segundo_n, ' ', s.primer_p, ' ', s.segundo_p) AS usuario_banco,
            b.descripcion AS banco,
            s.numero_identificacion,
            t.descripcion AS tipo_cuenta,
            s.numero_cuenta,
            s.val_rembolso,
            s.proceso,
            s.motivo_rembolso,
            e.evento,
            e.fecha_solicitud,
            e.estado_proceso,
            e.fecha_estado,
			m.region_id,
			m.descripcion_dep,
			s.rad_ant
          FROM solicitudes s
          JOIN afiliado a ON s.numero_identificacion_titular = a.numero_documento
          JOIN evento_solicitudes e ON s.radicado = e.radicado
          JOIN banco b ON s.entidad_bancaria = b.id
          JOIN tipo_cuenta t ON s.tipo_cuenta = t.id
          JOIN municipio m ON a.codigo_dane_municipio_atencion = m.id
          WHERE e.estado_proceso IN (?, ?, ?) AND e.fecha_solicitud BETWEEN ? AND ? AND s.proceso_tercero='Anticipo'";

$params = array_merge($estados, [$start_date, $end_date]); 
$stmt = sqlsrv_query($conn, $query, $params);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

if (!sqlsrv_has_rows($stmt)) {
    die('No se encontraron registros.');
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Definir encabezados
$headers = [
    "CONSECUTIVO",
    "FECHA CORREO",
    "FECHA",
    "NIT",
    "TITULAR",
    "NOMBRE USUARIO",
    "TIPO ID",
    "No. ID",
    "TELEFONO PACIENTE",
    "DPTO",
    "REGIONAL",
    "TIPO SOLICITUD",
    "DESCRIPCIÓN DE LA SOLICITUD",
    "VALOR TOTAL SOLICITADO",
    "VALOR TOTAL APROBADO",
    "VALOR PAGADO",
    "¿REQUIRIÓ SUBSANACIÓN?",
    "ESTADO",
    "FECHA DE PAGO",
    "OBSERVACIONES",
    "VAUCHER"
];

$columnLetters = range('A', 'Z');


// Aplicar encabezados
foreach ($headers as $index => $header) {
    $cell = $columnLetters[$index] . '1';
    $sheet->setCellValue($cell, $header);
    $sheet->getStyle($cell)->getAlignment()->setWrapText(true);
}

// Estilo azul claro para las columnas de A a U
$blueHeaderStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => '000000']], // Texto negro
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'A9D0F5']] // Azul claro
];

$sheet->getStyle('A1:U1')->applyFromArray($blueHeaderStyle);


$motivosMap = [
    'tecnologia_salud' => 'Tecnología en salud',
    'servicio_salud' => 'Servicio en salud '
];




$rowIndex = 2;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    if (!$row) {
        continue; // Salta iteraciones vacías
    }
    
    // Normalizar el valor antes de buscarlo en el array
    $motivoBD = trim(strtolower($row['motivo_rembolso']));
    
    $motivo = isset($motivosMap[$motivoBD]) ? $motivosMap[$motivoBD] : 'Otro';
    $sheet->setCellValue('A' . $rowIndex, "REU-FOM-" . ($row['rad_ant'] ?? 'SIN-CONSECUTIVO'));
    $sheet->setCellValue('C' . $rowIndex, $row['fecha_solicitud']->format('d/m/Y'));
    $sheet->setCellValue('D' . $rowIndex, $row['numero_identificacion']);
    $sheet->setCellValue('E' . $rowIndex, $row['nombre_prestador']);
    $sheet->setCellValue('F' . $rowIndex, $row['usuario']);
    $sheet->setCellValue('G' . $rowIndex, $row['tipo_documento']);
    $sheet->setCellValue('H' . $rowIndex, $row['numero_identificacion_titular']);
    $sheet->setCellValue('I' . $rowIndex, $row['celular_principal']);
    
    
    
    $sheet->setCellValue('J' . $rowIndex, $row['descripcion_dep']);
    $sheet->setCellValue('K' . $rowIndex, "REGION-" . ($row['region_id'] ?? 'SIN-CONSECUTIVO'));
    $sheet->setCellValue('L' . $rowIndex, $motivo);
   
    $sheet->setCellValue('N' . $rowIndex, $row['val_rembolso']);
    $sheet->setCellValue('O' . $rowIndex, $row['val_rembolso']);


    // Aplicar alineación centrada a todas las celdas de la fila
    foreach (range('A', 'U') as $col) {
        $sheet->getStyle($col . $rowIndex)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    }

    

    $rowIndex++;
}



// Aplicar bordes a las celdas
$lastRow = $rowIndex - 1;
$borderStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];
$sheet->getStyle("A1:U{$lastRow}")->applyFromArray($borderStyle);

// Ajustar el ancho de las columnas
foreach ($columnLetters as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$sheet->getStyle('L2:L' . $lastRow)
      ->getNumberFormat()
      ->setFormatCode('"$"#,##0.00');

$sheet->getStyle('N2:N' . $lastRow)
      ->getNumberFormat()
      ->setFormatCode('"$"#,##0.00');

$writer = new Xlsx($spreadsheet); // 🔹 Asegúrate de crear el objeto Writer antes de usarlo

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Matriz_Masiva.xlsx"');
header('Cache-Control: max-age=0');

ob_clean();
flush();
$writer->save('php://output'); // 🔹 Guardar correctamente
exit;



?>
