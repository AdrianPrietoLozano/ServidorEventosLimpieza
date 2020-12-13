<?php
    
class ParticipacionEventosDB {    
    private $conexion = null;
        
    public function __construct($conexion){
        $this->conexion = $conexion;
    }

    public function findAllParticipacionesUsuario($idUsuario) {
        $sql = "
            SELECT
                E._id AS id_evento,
                E.titulo AS titulo,
                A.nombre_usuario AS nombre_creador,
                E.descripcion AS descripcion,
                DATE_FORMAT(E.fecha_hora, '%d/%m/%Y') AS fecha,
                DATE_FORMAT(E.fecha_hora, '%H:%i') AS hora,
                R.fotografia AS foto
            FROM evento_limpieza AS E
            JOIN participa_evento AS P
                ON P.evento_id = E._id
            JOIN reporte_contaminacion AS R
                ON E.reporte_id = R._id
            JOIN ambientalista AS A
                ON E.ambientalista_id = A._id
            WHERE P.ambientalista_id = :idUsuario
                AND NOW() <= E.fecha_hora 
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["idUsuario" => $idUsuario]);

            $json = array();
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $row["creador"]["nombre"] = $row["nombre_creador"];
                unset($row["nombre_creador"]);
                $json[] = $row;
            }
            return $json;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }
    }

    public function insert($idUsuario, $idEvento) {
        $sql = "
            INSERT INTO participa_evento(ambientalista_id, evento_id)
            VALUES (:idUsuario, :idEvento)
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $params = ["idUsuario" => $idUsuario, "idEvento" => $idEvento];
            if ($statement->execute($params)) {
                return $this->conexion->lastInsertId();
            }

            return -1;
        } catch (\PDOException $e) {
            return -1;
        }
    }

    public function delete($idUsuario, $idEvento) {
        $sql = "
            DELETE
                FROM participa_evento
            WHERE ambientalista_id = :idUsuario
                AND evento_id = :idEvento
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["idUsuario" => $idUsuario, "idEvento" => $idEvento]);
            return $statement->rowCount() > 0;
        } catch (\PDOException $e) {
            echo $e->getMessage();

            return false;
        }
    }

    public function numPersonasParticipando($idEvento) {
        $sql = "
            SELECT COUNT(*) AS personas_unidas
            FROM participa_evento
            JOIN evento_limpieza AS evento
                ON participa_evento.evento_id = evento._id
            WHERE evento_id = :idEvento AND NOW() <= evento.fecha_hora;
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["idEvento" => $idEvento]);
            return $statement->fetch(\PDO::FETCH_ASSOC)["personas_unidas"];
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return 0;
        }
    }

    public function participaEnEvento($idEvento, $idUsuario) {
        $sql = "
            SELECT *
            FROM participa_evento
            WHERE evento_id = :idEvento
                AND ambientalista_id = :idUsuario
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["idUsuario" => $idUsuario, "idEvento" => $idEvento]);
            return $statement->fetchColumn() > 0;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return false;
        }
    }      
    
}

?>