<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ExcelExporter {
    private $spreadsheet;
    private $sheet;

    public function __construct() {
        $this->spreadsheet = new Spreadsheet();
        $this->sheet = $this->spreadsheet->getActiveSheet();
    }

    public function export($data) {
        $this->configureInitialStyles();
        $this->createHeaders();
        $this->fillData($data);
        return $this->saveFile();
    }

    private function configureInitialStyles() {
        // Centrar el texto en las celdas combinadas
        $this->sheet->getStyle('B2:AD4')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);  // Ajustar texto

        // Establecer altura de filas (37.5 puntos)
        $this->sheet->getRowDimension(2)->setRowHeight(37.5);
        $this->sheet->getRowDimension(3)->setRowHeight(12.75);
        $this->sheet->getRowDimension(4)->setRowHeight(33.75);

        // Establecer ancho de columnas (0.58)
        $this->sheet->getColumnDimension('A')->setWidth(0.58);
        $this->sheet->getColumnDimension('B')->setWidth(19.57);
        $this->sheet->getColumnDimension('C')->setWidth(14.71);
        $this->sheet->getColumnDimension('D')->setWidth(21.71);
        $this->sheet->getColumnDimension('E')->setWidth(15.14);
        $this->sheet->getColumnDimension('F')->setWidth(21.29);
        $this->sheet->getColumnDimension('G')->setWidth(18.18);
        $this->sheet->getColumnDimension('H')->setWidth(27.00);
        $this->sheet->getColumnDimension('I')->setWidth(15.86);
        $this->sheet->getColumnDimension('J')->setWidth(6.14);
        $this->sheet->getColumnDimension('K')->setWidth(10.86);
        $this->sheet->getColumnDimension('L')->setWidth(16);
        $this->sheet->getColumnDimension('M')->setWidth(19.17);
        $this->sheet->getColumnDimension('N')->setWidth(18.43);
        $this->sheet->getColumnDimension('O')->setWidth(12.71);
        $this->sheet->getColumnDimension('P')->setWidth(12.86);
        $this->sheet->getColumnDimension('Q')->setWidth(12.86);
        $this->sheet->getColumnDimension('R')->setWidth(13.46);
        $this->sheet->getColumnDimension('S')->setWidth(15.29);
        $this->sheet->getColumnDimension('T')->setWidth(18.43);
        $this->sheet->getColumnDimension('U')->setWidth(12.57);
        $this->sheet->getColumnDimension('V')->setWidth(10.71);
        $this->sheet->getColumnDimension('W')->setWidth(13.43);
        $this->sheet->getColumnDimension('X')->setWidth(10.71);
        $this->sheet->getColumnDimension('Y')->setWidth(13.43);
        $this->sheet->getColumnDimension('Z')->setWidth(10.71);
        $this->sheet->getColumnDimension('AA')->setWidth(14);
        $this->sheet->getColumnDimension('AB')->setWidth(10.71);
        $this->sheet->getColumnDimension('AC')->setWidth(15);
        $this->sheet->getColumnDimension('AD')->setWidth(13.43);


        
        // Aplicar bordes
        $this->sheet->getStyle('B2:AD4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $this->sheet->getStyle('B2:AD4')->getFont()
        ->setBold(true)           // Negrita
        ->setItalic(true)         // Cursiva
        ->setSize(14);            // Tamaño de letra 14

        $this->sheet->getStyle('B3:AD4')->getFont()
        ->setBold(true)           // Negrita
        ->setItalic(true)         // Cursiva
        ->setSize(11);            // Tamaño de letra 11

        $this->sheet->getStyle('B2:T3')->getFont()
        ->setColor(new Color(Color::COLOR_WHITE)); // Texto blanco

        // Rojo - Exclusivo Cliente
        $this->sheet->getStyle('B2:E3')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('C00000');
        
        // Naranja - Exclusivo Coordinación del negocio
        $this->sheet->getStyle('F2:T3')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('C65911');
        
        // Verde - Exclusivo Jefatura de impuestos
        $this->sheet->getStyle('U2:AD2')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('006666');

        // GRIS CLARO
        $this->sheet->getStyle('B4:E4')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('BFBFBF');

        // GRIS CLARO
        $this->sheet->getStyle('F4:T4')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('757171');

        // VERDE CLARO
        $this->sheet->getStyle('U3:AC3')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('339966');

        // GRIS SUPER CLARO
        $this->sheet->getStyle('U4:AC4')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('F2F2F2');

        // AZUL
        $this->sheet->getStyle('AD3:AD4')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('2F75B5');
    }

    private function createHeaders() {
        // Título principal
        //$this->sheet->setCellValue('F2', 'Anexo entrega de pagos');

        // Primera fila de encabezados
        $headers1 = [
            'Exclusivo - Cliente', '', '', '',
            'Exclusivo - Coordinación del negocio', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
            'Exclusivo - Jefatura de impuestos', '', '', '', '', '', '', '', '', '',
        ];
        $this->sheet->fromArray($headers1, NULL, 'B2');

        // Segunda fila de encabezados
        $headers2 = [
            '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
            'Retefuente', '', 'ReteICA', '', 'ReteIVA', '', 'Estampillas', '', 'Tasa Bomberil', 'Usuario de Impuestos'
        ];
        $this->sheet->fromArray($headers2, NULL, 'B3');

        // Encabezados de columnas
        $headers3 = [
            'ORDEN DE OPERACION', 'ID TERCERO', 'Beneficiario pago', 'Valor', 'Unidad de negocio',
            'Usuario encargado del negocio', 'Nombre del Archivo', 'Articulo', 'Ubicación','',
            'IVA (Para pagos fraccionados)', 'Base de IVA (Para pagos fraccionados)', 'Base Exenta',
            'Base de Retegarantia','Retegarantía Valor','Retegarantía %', 'Valor Amortizaciòn',
            'Valor Contrato', 'Valor Otro sí',
            'Base', 'Tarifa', 'Base', 'Tarifa', 'Base', 'Tarifa', 'Base', 'Tarifa','Tarifa',''
        ];
        $this->sheet->fromArray($headers3, NULL, 'B4');

        // Combinar celdas
        $this->mergeCells();
    }

    private function mergeCells() {
        $this->sheet->mergeCells('B2:E3');
        $this->sheet->mergeCells('F2:T3');
        $this->sheet->mergeCells('U2:AD2');
        $this->sheet->mergeCells('J4:K4');
        $this->sheet->mergeCells('U3:V3');
        $this->sheet->mergeCells('W3:X3');
        $this->sheet->mergeCells('Y3:Z3');
        $this->sheet->mergeCells('AA3:AB3');
        $this->sheet->mergeCells('AD3:AD4');
    }

    private function fillData($data) {
        $row = 5;
        foreach ($data as $item) {
            // Llenar datos básicos
            //$this->sheet->setCellValue('A' . $row, $item['radicado']);

            $this->sheet->setCellValue('C' . $row, $item['id_tercero']);
            $this->sheet->setCellValue('D' . $row, $item['beneficiario']);
            $this->sheet->setCellValue('E' . $row, floatval($item['valor_total']));
            $this->sheet->setCellValue('F' . $row, 'AP 221');
            $this->sheet->setCellValue('G' . $row, $item['usuario_id']);
            $this->sheet->setCellValue('H' . $row, 'AP 221 - -');
            $this->sheet->setCellValue('I' . $row, isset($item['articulo']) ? $item['articulo'] : '');
            $this->sheet->setCellValue('J' . $row, substr($item['municipio'], 0, 2));
            $this->sheet->setCellValue('K' . $row, substr($item['municipio'], -3));
            $this->sheet->setCellValue('L' . $row, !empty($item['iva']) ? floatval($item['iva']) * floatval($item['base_iva']) / 100 : '');
            $this->sheet->setCellValue('M' . $row, !empty($item['base_iva']) ? floatval($item['base_iva']) : '');
            $this->sheet->setCellValue('N' . $row, !empty($item['base_excenta']) ? floatval($item['base_excenta']) : '');
            
            $row++;
        }

        $this->applyDataFormats($row);
    }

    private function applyDataFormats($lastRow) {
        // Formato de porcentaje para IVA
        //$this->sheet->getStyle('J7:J' . $lastRow)->getNumberFormat()
        //->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        
        // Formato de moneda para valores
        $moneyColumns = ['E5:E', 'L5:L', 'M5:M', 'N5:N'];
        foreach ($moneyColumns as $column) {
            $this->sheet->getStyle($column . $lastRow)
            ->getNumberFormat()
            ->setFormatCode('"$"#,##0.00');
        }
        $moneyColumns = ['C5:C', 'J5:J', 'K5:K'];
        foreach ($moneyColumns as $column) {
            $this->sheet->getStyle($column . $lastRow)
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        // Aplicar bordes a todos los datos
        $this->sheet->getStyle('B2:' . $this->sheet->getHighestColumn() . $lastRow)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        for ($i = 1; $i <= 4; $i++) {
            switch ($i) {
                case 1:
                    $color = 'C00000';
                    $rango = 'B2:E';
                    break;
                case 2:
                    $color = 'C65911';
                    $rango = 'F2:T';
                    break;
                case 3:
                    $color = '006666';
                    $rango = 'U2:AD';
                    break;
            }

            $borderStyle = [
                'borders' => [
                    'outline' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => $color] // Color rojo para bordes externos
                    ]
                ]
            ];
            $this->sheet->getStyle($rango. $lastRow)->applyFromArray($borderStyle);
            
        }

        $this->sheet->getStyle('B5:' . $this->sheet->getHighestColumn() . $lastRow)->getFont()
            ->setSize(8);            // Tamaño de letra 8;
    }

    private function saveFile() {
        // Generar nombre único para el archivo
        $filename = 'exports_' . date('Y-m-d_His') . '.xlsx';
        $filepath = '../../../exports/' . $filename;
        
        try {
            $writer = new Xlsx($this->spreadsheet);
            $writer->save($filepath);
            
            // Liberar memoria
            $this->spreadsheet->disconnectWorksheets();
            unset($this->spreadsheet);
            
            return $filepath;
        } catch (Exception $e) {
            throw new Exception("Error al guardar el archivo Excel: " . $e->getMessage());
        }
    }
}
