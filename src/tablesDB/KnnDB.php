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
            $statement->execute([":idUsuario" => $idUsuario]);
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
            LEFT JOIN (SELECT DISTINCT evento_id FROM participa_evento AS PA WHERE PA.ambientalista_id = :idUsuario) AS P ON K.evento_id = P.evento_id
            WHERE P.evento_id IS NULL
        ";

        try {
            $statement = $this->conexion->prepare($query);
            $statement->execute([":idUsuario" => $idUsuario]);
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
            $statement->execute([":idEvento" => $idEvento]);
            $datos = $statement->fetch(\PDO::FETCH_ASSOC);
            unset($datos["evento_id"]);
            return $datos;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }
    }

    public function insert($idEvento, $residuos, $volumen, $fecha, $hora) {
        
        $insert = "
            INSERT INTO KNN(evento_id, escombro, envases, carton, bolsas,
                        electricos, pilas, neumaticos, medicamentos, varios,
                        volumen, dia_semana, mes_anio, hora_dia)
            VALUES (:idEvento, :escombro, :envases, :carton, :bolsas,
                    :electricos, :pilas, :neumaticos, :medicamentos, :varios,
                    :volumen, :dia_semana, :mes_anio, :hora_dia)
            ";

        $nombresResiduos = array("Escombros" => "escombro",
            "Envases" => "envases",
            "Cartón" => "carton",
            "Bolsas" => "bolsas",
            "Eléctricos y electrónicos" => "electricos",
            "Pilas y baterías" => "pilas",
            "Neumáticos" => "neumaticos",
            "Medicamentos" => "medicamentos",
            "Varios" => "varios"
        );

        $volumenesResiduos = array("Cabe en una mano" => 1,
            "Cabe en una mochila" => 2,
            "Cabe en un contenedor" => 3,
            "Cabe en un automóvil" => 4,
            "Cabe en un camión" => 5,
            "Más grande" => 6
        );

        $values = [":idEvento" => $idEvento, ":volumen" => $volumenesResiduos[$volumen]];

        foreach ($nombresResiduos as $key => $residuo) {
            if (in_array($key, $residuos)) {
                $values[":$residuo"] = 1;
            } else {
                $values[":$residuo"] = 0;
            }
        }

        $date = strtotime("$fecha $hora");
        $values[":dia_semana"] = date("w", $date);
        $values[":mes_anio"] = date("n", $date);
        $values[":hora_dia"] = date("H", $date);

        try {
            $statement = $this->conexion->prepare($insert);
            if ($statement->execute($values))
                return $this->conexion->lastInsertId();

            return -1;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            echo "MAL 2";
            return -1;
        }
        

    }

    // retorna los valores min y max de cada atributo de la tabla KNN
    public function findAllMinMaxValues() {
        $query = "
            SELECT  MAX(volumen)    AS max_volumen,       MIN(volumen)    AS min_volumen,
                    MAX(dia_semana) AS max_dia_semana,    MIN(dia_semana) AS min_dia_semana,
                    MAX(mes_anio)   AS max_mes_anio,      MIN(mes_anio)   AS min_mes_anio,
                    MAX(hora_dia)   AS max_hora_dia,      MIN(hora_dia)   AS min_hora_dia,
                    MAX(latitud)    AS max_latitud,       MIN(latitud)    AS min_latitud,
                    MAX(longitud)   AS max_longitud,      MIN(longitud)   AS min_longitud
            FROM KNN
        ";

        try {
            $statement = $this->conexion->query($query);
            $results = $statement->fetch(\PDO::FETCH_ASSOC);
            
            $min_max = [
                "escombro" => ["min" => 0, "max" => 1],
                "envases" => ["min" => 0, "max" => 1],
                "carton" => ["min" => 0, "max" => 1],
                "bolsas" => ["min" => 0, "max" => 1],
                "electricos" => ["min" => 0, "max" => 1],
                "pilas" => ["min" => 0, "max" => 1],
                "neumaticos" => ["min" => 0, "max" => 1],
                "medicamentos" => ["min" => 0, "max" => 1],
                "varios" => ["min" => 0, "max" => 1],
                "volumen" => ["min" => $results["min_volumen"], "max" => $results["max_volumen"]],
                "dia_semana" => ["min" => $results["min_dia_semana"], "max" => $results["max_dia_semana"]],
                "mes_anio" => ["min" => $results["min_mes_anio"], "max" => $results["max_mes_anio"]],
                "hora_dia" => ["min" => $results["min_hora_dia"], "max" => $results["max_hora_dia"]],
                "latitud" => ["min" => $results["min_latitud"], "max" => $results["max_latitud"]],
                "longitud" => ["min" => $results["min_longitud"], "max" => $results["max_longitud"]]
            ];

            return $min_max;

        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }
    }

}

?>