<?php
    
class EventoDB {    
    private $conexion = null;
        
    public function __construct($conexion){
        $this->conexion = $conexion;
    }

    public function findAll() {
        $query = "
            SELECT E._id AS id_evento,
                    R.latitud, R.longitud
            FROM evento_limpieza AS E
            JOIN reporte_contaminacion AS R
                ON E.reporte_id = R._id
            WHERE NOW() <= E.fecha_hora
        ";

        try {
            $statement = $this->conexion->query($query);
            $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return $results;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }
    }

    public function find($id) {
        $query = "
            SELECT
                E._id AS id_evento,
                E.titulo AS titulo,
                R._id AS reporte_id,
                nombre_usuario AS nombre_creador,
                ambientalista._id AS id_creador,
                E.descripcion AS descripcion,
                E.administrado AS administrado,
                DATE_FORMAT(E.fecha_hora, '%d/%m/%Y') AS fecha,
                DATE_FORMAT(E.fecha_hora, '%H:%i:%s') AS hora,
                R.fotografia AS foto
            FROM evento_limpieza AS E
            JOIN ambientalista
                ON E.ambientalista_id = ambientalista._id
            JOIN reporte_contaminacion AS R
                ON E.reporte_id = R._id
            WHERE E._id = :idEvento
        ";

        try {
            $statement = $this->conexion->prepare($query);
            $statement->execute(["idEvento" => $id]);
            $results = $statement->fetch(\PDO::FETCH_ASSOC);
            $finalResult = array();
            if ($results) {
                $finalResult["id_evento"] = $results["id_evento"];
                $finalResult['titulo'] = $results["titulo"];
                $finalResult['id_reporte'] = $results["reporte_id"];
                $finalResult['descripcion'] = $results["descripcion"];
                $finalResult['fecha'] = $results["fecha"];
                $finalResult['hora'] = $results["hora"];
                $finalResult['foto'] = $results["foto"];
                $finalResult['administrado'] = $results["administrado"];
                $finalResult["creador"]['id'] = $results["id_creador"];
                $finalResult["creador"]['nombre'] = $results["nombre_creador"];
            }
            return $finalResult;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }

    }

    public function findByTitulo($titulo) {
        $sql = "
            SELECT
                E._id AS id_evento,
                E.titulo AS titulo,
                DATE_FORMAT(E.fecha_hora, '%d-%m-%Y') AS fecha,
                DATE_FORMAT(E.fecha_hora, '%H:%i') AS hora,
                R.fotografia AS foto
            FROM evento_limpieza AS E
            JOIN reporte_contaminacion AS R
                ON E.reporte_id = R._id
            WHERE E.titulo LIKE :titulo
                AND NOW() <= E.fecha_hora
            LIMIT 20
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["titulo" => "%".$titulo."%"]);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return array();
        }
    }

    public function insert($id_usuario, $reporte_id, $titulo, $fecha, $hora, $descripcion) {
        $fecha_hora = $fecha." ".$hora;

        $query = "
        INSERT INTO evento_limpieza(ambientalista_id, titulo,
                                    reporte_id, fecha_hora, descripcion)
        VALUES (:id_usuario, :titulo, :reporte_id,
                STR_TO_DATE(:fecha_hora, '%d/%m/%Y %H:%i'), :descripcion)
        ";

        try {
            $statement = $this->conexion->prepare($query);
            $valores = array(":id_usuario" => $id_usuario,
                ":titulo" => $titulo,
                ":reporte_id" => $reporte_id,
                ":fecha_hora" => $fecha_hora,
                ":descripcion" => $descripcion
            );
            if ($statement->execute($valores)) {
                return $this->conexion->lastInsertId();
            }
            
            return -1;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return -1;
        }
    }

    function findAllEventosUsuario($idUsuario) {
        $sql = "
            SELECT
                E._id AS id_evento,
                E.titulo AS titulo,
                E.descripcion AS descripcion,
                DATE_FORMAT(E.fecha_hora, '%d/%m/%Y') AS fecha,
                DATE_FORMAT(E.fecha_hora, '%H:%i') AS hora,
                R.fotografia AS foto
            FROM evento_limpieza AS E
            JOIN reporte_contaminacion AS R
                ON E.reporte_id = R._id
            WHERE E.ambientalista_id = :idUsuario
            ORDER BY E.fecha_hora;
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute([":idUsuario" => $idUsuario]);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }
    }

    // EL NOMBRE DE ESTA FUNCIÓN SE PUEDE MEJOARAR
    public function setEventoAdministrado($idEvento, $administrado) {
        $sql = "
            UPDATE evento_limpieza
            SET administrado = :administrado
            WHERE _id = :idEvento
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute([":administrado" => $administrado, "idEvento" => $idEvento]);
            return $statement->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function isEventoAdministrado($idEvento) {
        $sql = "
            SELECT administrado
            FROM evento_limpieza
            WHERE _id = :idEvento
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["idEvento" => $idEvento]);
            $result = $statement->fetch(PDO::FETCH_ASSOC);

            return $result["administrado"] == 1;
        } catch (\PDOException $e) {
            return true;
        }
    }

    function existeEventoConReporte($idReporte)
    {
        $sql = "SELECT COUNT(*) FROM evento_limpieza WHERE reporte_id = :idReporte";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["idReporte" => $idReporte]);
            return $statement->fetchColumn() > 0;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return false;
        }
    }
}

?>