<?php
    
class ReporteDB {    
    private $conexion = null;
        
    public function __construct($conexion){
        $this->conexion = $conexion;
    }

    public function findAll() {
        $sql = "
            SELECT
                R._id AS id_reporte,
                latitud, longitud
            FROM reporte_contaminacion AS R
        ";

        try {
            $statement = $this->conexion->query($sql);
            $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return $results;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }
    }

    public function find($id) {
        $sql = "
        SELECT reporte_contaminacion._id AS id_reporte,
                latitud, longitud, DATE_FORMAT(fecha_hora, '%d-%m-%Y') AS fecha,
                DATE_FORMAT(fecha_hora, '%H:%i:%s') AS hora,
                volumen, descripcion, nombre_usuario AS creador,
                fotografia AS foto
        FROM reporte_contaminacion
        JOIN volumen_residuo
            ON volumen_id = volumen_residuo._id
        JOIN ambientalista
            ON ambientalista_id = ambientalista._id
        WHERE reporte_contaminacion._id = :idReporte
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["idReporte" => $id]);
            $results = $statement->fetch(\PDO::FETCH_ASSOC);
            if ($results) {
                $results["residuos"] = $this->obtenerResiduosReporte($id);
            }   
            return $results;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }

    }

    public function insert($latitud, $longitud, $ambientalista_id, $foto, $descripcion, $volumen, $residuos) {
        $idReporteCreado = -1;
        $volumen_id = $this->econtrarIdVolumen($volumen);

        $sql = "
        INSERT INTO reporte_contaminacion(latitud, longitud, ambientalista_id,
                                        fotografia, descripcion, fecha_hora,
                                        volumen_id, tipo_residuo_id)
        VALUES (?, ?, ?, ?, ?, NOW(), ?, 1)
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $valores = array($latitud, $longitud, $ambientalista_id, $foto, $descripcion, $volumen_id);

            $this->conexion->beginTransaction();

            if ($statement->execute($valores)) {
                $idReporteCreado = $this->conexion->lastInsertId();
                if (!$this->insertarResiduosReporte($idReporteCreado, $residuos)) {
                    if ($this->conexion->inTransaction()) {
                        $this->conexion->rollback();
                        $idReporteCreado = -1;
                    }
                }
            }

            $this->conexion->commit();

            //echo $statement->errorInfo()[2];
            return $idReporteCreado;

        } catch (\PDOException $e) {
            //exit($e->getMessage());
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollback();
            }
            return -1;
        }

    }

    public function delete($id) {
        $sql1 = "DELETE FROM residuos_reporte WHERE reporte_id = :idReporte";
        $sql2 = "DELETE FROM reporte_contaminacion WHERE _id = :idReporte";

        try {
            $this->conexion->beginTransaction();

            $statement = $this->conexion->prepare($sql1);
            $statement->execute(["idReporte" => $id]);

            $statement = $this->conexion->prepare($sql2);
            $statement->execute(["idReporte" => $id]);

            $this->conexion->commit();

            return $statement->rowCount() > 0;

        } catch (\PDOException $e) {
            //exit($e->getMessage());
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollback();
            }
            return false;
        }
    }

    public function findAllReportesUsuario($idUsuario) {
        $sql = "
            SELECT
                R._id AS id_reporte,
                R.descripcion AS descripcion,
                DATE_FORMAT(R.fecha_hora, '%d/%m/%Y') AS fecha,
                DATE_FORMAT(R.fecha_hora, '%H:%i') AS hora,
                R.fotografia AS foto
            FROM reporte_contaminacion AS R
            WHERE R.ambientalista_id = :idUsuario
            ORDER BY R.fecha_hora
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["idUsuario" => $idUsuario]);
            $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return $results;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }
    }

    private function insertarResiduosReporte($reporte_id, $residuos) {
        $residuosId = array('Escombros' => 1, 'Envases' => 2, 'Cartón' => 3,
            'Bolsas' => 4, 'Eléctricos y electrónicos' => 5, 'Pilas y baterías' => 6,
            'Neumáticos' => 7, 'Medicamentos' => 8, 'Varios' => 9,);

        $sql = "INSERT INTO residuos_reporte(reporte_id, residuo_id) VALUES ";

        foreach ($residuos as &$residuo) {
            $sql .= " ({$reporte_id}, " . $residuosId[$residuo] . "),";
        }

        $sql[strlen($sql) - 1] = ";";

        try {
            return $this->conexion->exec($sql) > 0;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return false;
        }
    }


    public function obtenerResiduosReporte($idReporte) {            
        // consulta para obtener los tipos de residuos del reporte
        $sql = "
            SELECT tipo
            FROM residuos_reporte AS R
            JOIN tipo_residuo AS T
                ON R.residuo_id = T._id
            WHERE reporte_id = :idReporte
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["idReporte" => $idReporte]);
            $results = array_column($statement->fetchAll(\PDO::FETCH_OBJ), "tipo");
            return $results;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }
    }

    public function reporteTieneLimpieza($idReporte) {
        $sql = "SELECT * FROM limpiezas WHERE reporte_id = :idReporte";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["idReporte" => $idReporte]);
            return $statement->fetchColumn() > 0;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return false;
        }
    }

    private function econtrarIdVolumen($volumen) {
        $sql = "SELECT _id AS id FROM volumen_residuo WHERE volumen = :volumen";
        
        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["volumen" => $volumen]);
            $resultado = $statement->fetch(\PDO::FETCH_ASSOC);
            if ($resultado) {
                return $resultado["id"];
            }
            return 1; // default
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return 1;
        }
    }
    
}

?>