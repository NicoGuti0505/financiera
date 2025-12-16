<?php

/**
 * Genera un nombre de archivo único para la exportación
 * 
 * @param string|null $userId ID del usuario si está filtrando por usuario
 * @param string|null $startDate Fecha inicial si está filtrando por fechas
 * @param string|null $endDate Fecha final si está filtrando por fechas
 * @return string Nombre del archivo generado
 */
function generateFilename($userId = null, $startDate = null, $endDate = null): string {
    $filename = 'exports_';
    
    if ($userId) {
        $filename .= 'user_' . $userId . '_';
    }
    
    if ($startDate !== null) {
        $filename .= date('Y-m-d', strtotime($startDate));
        if ($startDate !== $endDate) {
            $filename .= '_to_' . date('Y-m-d', strtotime($endDate));
        }
    }
    
    $filename .= '_' . date('His') . '.xlsx';
    return $filename;
}

/**
 * Concatena los nombres del tercero eliminando valores nulos
 * 
 * @param string|null $nombreNit Nombre o razón social
 * @param string|null $segundoNombre Segundo nombre
 * @param string|null $primerApellido Primer apellido
 * @param string|null $segundoApellido Segundo apellido
 * @return string Nombre completo concatenado
 */
function concatNombreTercero(?string $nombreNit, ?string $segundoNombre, ?string $primerApellido, ?string $segundoApellido): string {
    $partes = array_filter([$nombreNit, $segundoNombre, $primerApellido, $segundoApellido], function($parte) {
        return !is_null($parte) && trim($parte) !== '';
    });
    
    return implode(' ', $partes);
}

/**
 * Formatea un valor numérico para mostrar en Excel
 * 
 * @param float|null $valor Valor a formatear
 * @param int $decimales Número de decimales a mostrar
 * @return float|null Valor formateado
 */
function formatearValor(?float $valor, int $decimales = 2): ?float {
    if (is_null($valor)) {
        return null;
    }
    return round($valor, $decimales);
}

/**
 * Calcula el valor del IVA
 * 
 * @param float|null $iva Porcentaje de IVA
 * @param float|null $baseIva Base del IVA
 * @return float|null Valor del IVA calculado
 */
function calcularIVA(?float $iva, ?float $baseIva): ?float {
    if (is_null($iva) || is_null($baseIva)) {
        return null;
    }
    return ($iva * $baseIva) / 100;
}

/**
 * Valida las fechas de entrada
 * 
 * @param string|null $startDate Fecha inicial
 * @param string|null $endDate Fecha final
 * @return bool True si las fechas son válidas
 * @throws Exception Si las fechas son inválidas
 */
function validarFechas(?string $startDate, ?string $endDate): bool {
    if ($startDate && $endDate) {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        if ($start > $end) {
            throw new Exception("La fecha inicial no puede ser mayor que la fecha final");
        }
        
        $today = new DateTime();
        if ($start > $today || $end > $today) {
            throw new Exception("Las fechas no pueden ser futuras");
        }
    }
    
    return true;
}

/**
 * Verifica y crea el directorio de exportación si no existe
 * 
 * @param string $path Ruta del directorio
 * @return bool True si el directorio existe o fue creado
 * @throws Exception Si no se puede crear el directorio
 */
function verificarDirectorioExportacion(string $path): bool {
    if (!file_exists($path)) {
        if (!mkdir($path, 0777, true)) {
            throw new Exception("No se pudo crear el directorio de exportación");
        }
    }
    
    if (!is_writable($path)) {
        throw new Exception("El directorio de exportación no tiene permisos de escritura");
    }
    
    return true;
}

/**
 * Limpia archivos antiguos del directorio de exportación
 * 
 * @param string $path Ruta del directorio
 * @param int $minutosAntiguedad Minutos de antigüedad para considerar un archivo viejo
 * @return void
 */
function limpiarArchivosAntiguos(string $path, int $minutosAntiguedad = 60): void {
    if (!is_dir($path)) {
        return;
    }
    
    $files = glob($path . "/*.xlsx");
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= 60 * $minutosAntiguedad) {
                @unlink($file);
            }
        }
    }
}

/**
 * Formatea el monto en formato moneda
 * 
 * @param float|null $monto Monto a formatear
 * @return string Monto formateado
 */
function formatearMonto(?float $monto): string {
    if (is_null($monto)) {
        return '';
    }
    return number_format($monto, 2, '.', ',');
}

?>
