<?php
class DataFetcher {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function buscarInformacion($startDate = null, $endDate = null, $userId = null) {
        $sql = 
            "SELECT DISTINCT
                t.id AS id_tercero,
                CONCAT_WS(' ', NULLIF(t.nombre_nit, ''),
                NULLIF(t.segundo_nombre, ''),
                NULLIF(t.primer_apellido, ''),
                NULLIF(t.segundo_apellido, '')) AS beneficiario,
                t.municipio_id AS municipio,
                vt.valor_total,
                u.grupor_id,
                ap.radicado,
                CAST(ap.iva AS float) AS iva,
                CAST(ap.base_iva AS float) AS base_iva,
                CAST(ap.base_excenta AS float) AS base_excenta,
                ea.fecha,
                ea.usuario_id
                FROM anexo_pago ap
                INNER JOIN validacion_terceros vt ON ap.validacion_terceros_id = vt.id
                INNER JOIN tercero t ON vt.tercero_id = t.id
                INNER JOIN evento_tercero et ON vt.id = et.id_validacion_tercero
                INNER JOIN usuario u ON et.id_usuario = u.id
                INNER JOIN (
                SELECT anexo_pago_id, MAX(fecha) as ultima_fecha
                FROM evento_anexo
                GROUP BY anexo_pago_id) 
                    ultimo_evento ON ap.id = ultimo_evento.anexo_pago_id
                INNER JOIN evento_anexo ea ON ea.anexo_pago_id = ap.id 
                AND ea.fecha = ultimo_evento.ultima_fecha
                WHERE (ap.radicado IS NULL OR ap.radicado = '' OR LTRIM(RTRIM(ap.radicado)) = '')";
        
        $params = [];
        $whereClause = [];
        
        if ($startDate !== null) {
            $startDateTime = new DateTime($startDate);
            $endDateTime = new DateTime($endDate);
            $endDateTime->setTime(23, 59, 59);
            $whereClause[] = "ea.fecha BETWEEN ? AND ?";
            $params[] = $startDateTime->format('Y-m-d H:i:s');
            $params[] = $endDateTime->format('Y-m-d H:i:s');
        }
        
        if ($userId !== null) {
            $whereClause[] = "ea.usuario_id = ?";
            $params[] = $userId;
        }
        
        if (!empty($whereClause)) {
            $sql .= " AND " . implode(" AND ", $whereClause);
        }

        $sql .= " ORDER BY ea.fecha DESC";

        try {
            $stmt = sqlsrv_query($this->conn, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception("Error en la consulta: " . print_r(sqlsrv_errors(), true));
            }

            $data = array();
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $row = $this->processRow($row);
                $data[] = $row;
            }

            return $data;

        } catch (Exception $e) {
            error_log("Error en DataFetcher::buscarInformacion: " . $e->getMessage());
            throw $e;
        }
    }

    private function processRow($row) {
        $numericFields = ['valor_total', 'iva', 'base_iva', 'base_excenta'];
        foreach ($numericFields as $field) {
            if (!isset($row[$field]) || $row[$field] === null) {
                $row[$field] = 0;
            } else if (is_string($row[$field])) {
                $row[$field] = floatval($row[$field]);
            }
        }

        $textFields = ['beneficiario', 'municipio'];
        foreach ($textFields as $field) {
            if (!isset($row[$field]) || $row[$field] === null) {
                $row[$field] = '';
            }
        }

        $row['beneficiario'] = trim(preg_replace('/\s+/', ' ', $row['beneficiario']));

        return $row;
    }

    public function hasData($startDate = null, $endDate = null, $userId = null) {
        try {
            $data = $this->buscarInformacion($startDate, $endDate, $userId);
            return !empty($data);
        } catch (Exception $e) {
            return false;
        }
    }
}