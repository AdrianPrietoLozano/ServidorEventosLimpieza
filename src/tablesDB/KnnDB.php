<?php
    
class KnnDB {    
    private $conexion = null;
        
    public function __construct($conexion){
        $this->conexion = $conexion;
    }

    // retorna los datos de los eventos en los que el 
    // usuario participa o participó
    public function findAllEventosParticipaUsuario($idUsuario) {
        $query = "
            SELECT K.*
            FROM KNN AS K
            JOIN (SELECT DISTINCT evento_id, created_at
                    FROM participa_evento
                    WHERE ambientalista_id = :idUsuario) AS t2
                ON K.evento_id = t2.evento_id
            ORDER BY t2.created_at DESC
        ";

        try {
            $statement = $this->conexion->prepare($query);
            $statement->execute(["idUsuario" => $idUsuario]);
            return $statement->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }
    }

    // retorna los eventos activos en lo que
    // el usuario no participa de la tabla KNN
    public function findAllDatosEventos($idUsuario) {
        $query = "
            SELECT K.*
            FROM KNN AS K
            JOIN evento_limpieza AS E
                ON (K.evento_id = E._id AND NOW() <= E.fecha_hora)
            LEFT JOIN participa_evento AS P
                ON (P.evento_id = K.evento_id AND P.ambientalista_id = :idUsuario)
            WHERE P.evento_id IS NULL
        ";

        try {
            $statement = $this->conexion->prepare($query);
            $statement->execute(["idUsuario" => $idUsuario]);
            return $statement->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }
    }

    // retorna los datos de un evento de la tabla KNN
    public function find($idEvento) {
        $query = "SELECT * FROM KNN WHERE evento_id = :idEvento";

        try {
            $statement = $this->conexion->prepare($query);
            $statement->execute(["idEvento" => $idEvento]);
            $datos = $statement->fetch(\PDO::FETCH_ASSOC);
            unset($datos["evento_id"]);
            return $datos;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }
    }

    // retorna los valores min y max de cada atributo de la tabla KNN
    public function findAllMinMaxValues() {

    }

}

?>