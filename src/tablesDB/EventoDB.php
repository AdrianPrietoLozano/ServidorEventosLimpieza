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
            $statement->execute([":idEvento" => $id]);
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
            $statement->execute([":titulo" => "%".$titulo."%"]);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return array();
        }
    }

    public function insert($id_usuario, $reporte_id, $titulo, $fecha, $hora, $descripcion, $puntos) {
        $fecha_hora = $fecha." ".$hora;

        $query = "
        INSERT INTO evento_limpieza(ambientalista_id, titulo,
                                    reporte_id, fecha_hora, descripcion, puntos)
        VALUES (:id_usuario, :titulo, :reporte_id,
                STR_TO_DATE(:fecha_hora, '%d/%m/%Y %H:%i'), :descripcion, :puntos)
        ";

        try {
            $statement = $this->conexion->prepare($query);
            $valores = array(":id_usuario" => $id_usuario,
                ":titulo" => $titulo,
                ":reporte_id" => $reporte_id,
                ":fecha_hora" => $fecha_hora,
                ":descripcion" => $descripcion,
                ":puntos" => $puntos
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

    public function findAllEventosUsuario($idUsuario) {
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

    // retorna los eventos más populares en donde no participa el usuario
    public function findAllEventosPopulares($idUsuario) {
        $query = "
            SELECT
                evento._id AS id_evento,
                evento.titulo AS titulo,
                DATE_FORMAT(evento.fecha_hora, '%d-%m-%Y') AS fecha,
                DATE_FORMAT(evento.fecha_hora, '%H:%i') AS hora,
                reporte.fotografia AS foto
            FROM participa_evento
            JOIN evento_limpieza AS evento
                ON participa_evento.evento_id = evento._id
            JOIN reporte_contaminacion AS reporte
                ON evento.reporte_id = reporte._id
            WHERE NOW() <= evento.fecha_hora
                AND evento._id NOT IN
                    (SELECT evento_id
                    FROM participa_evento
                    WHERE ambientalista_id = {$idUsuario})
            GROUP BY id_evento
            ORDER BY COUNT(*) DESC
            LIMIT 20;
        ";

        try {
            $statement = $this->conexion->query($query);
            $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
            if (count($results) === 0) {
                return $this->findFirstEventos($idUsuario, 20);
            }

            return $results;
        } catch (\PDOException $e) {
            //echo $e->getMessage();
            return $this->findFirstEventos($idUsuario, 20);
        }
    }

    // retorna los primeros 20 eventos en donde no participa el usuario
    private function findFirstEventos($idUsuario) {
        $query = "
            SELECT
                evento._id AS id_evento,
                evento.titulo AS titulo,
                DATE_FORMAT(evento.fecha_hora, '%d-%m-%Y') AS fecha,
                DATE_FORMAT(evento.fecha_hora, '%H:%i') AS hora,
                reporte.fotografia AS foto
            FROM evento_limpieza AS evento
            JOIN reporte_contaminacion AS reporte
                ON evento.reporte_id = reporte._id
            WHERE NOW() <= evento.fecha_hora
                AND evento._id
                    NOT IN (SELECT evento_id
                            FROM participa_evento
                            WHERE ambientalista_id = :idUsuario)
            LIMIT 20
        ";

        try {
            $statement = $this->conexion->prepare($query);
            $statement->execute([":idUsuario" => $idUsuario]);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            echo $e->getMessage();
            return array();
        }
    }

    // retorna los eventos en los que el id esta en idsEventos
    public function findAllEventosIn($idsEventos) {
        $json = array();
        $ids = join(", ", $idsEventos);
        
        $select = "
            SELECT
                evento._id AS id_evento,
                evento.titulo AS titulo,
                DATE_FORMAT(evento.fecha_hora, '%d-%m-%Y') AS fecha,
                DATE_FORMAT(evento.fecha_hora, '%H:%i') AS hora,
                reporte.fotografia AS foto
            FROM evento_limpieza AS evento
            JOIN reporte_contaminacion AS reporte
                ON evento.reporte_id = reporte._id AND evento._id IN ($ids)
        ";

        try {
            $statement = $this->conexion->query($select);
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
            $statement->execute([":administrado" => $administrado, ":idEvento" => $idEvento]);
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
            $statement->execute([":idEvento" => $idEvento]);
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
            $statement->execute([":idReporte" => $idReporte]);
            return $statement->fetchColumn() > 0;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return false;
        }
    }

    // SOLO PARA PRUEBAS
    public function prueba($idsEventos) {
    	$json = array();
        $ids = join(", ", $idsEventos);
        
        $select = "
            SELECT
                evento._id AS id_evento,
                reporte.latitud AS latitud,
                reporte.longitud AS longitud
            FROM evento_limpieza AS evento
            JOIN reporte_contaminacion AS reporte
                ON evento.reporte_id = reporte._id AND evento._id IN ($ids)
        ";

        try {
            $statement = $this->conexion->query($select);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }
    }

    public function getEventosCercanos($lat, $lon, $radio) {
        $R = 6371;  // earth's mean radius, km

        $maxLat = $lat + rad2deg($radio/$R);
        $minLat = $lat - rad2deg($radio/$R);
        $maxLon = $lon + rad2deg(asin($radio/$R) / cos(deg2rad($lat)));
        $minLon = $lon - rad2deg(asin($radio/$R) / cos(deg2rad($lat)));

        $sql = "
        SELECT *
        FROM (
            SELECT E._id AS id, REPORTE.latitud, REPORTE.longitud, E.puntos
            FROM evento_limpieza AS E
            JOIN reporte_contaminacion AS REPORTE
                ON E.reporte_id = REPORTE._id
            WHERE latitud BETWEEN :minLat AND :maxLat
                AND longitud BETWEEN :minLon AND :maxLon
                AND NOW() <= E.fecha_hora
            ) AS FirstCut
        WHERE acos(sin(:lat)*sin(radians(latitud)) + cos(:lat)*cos(radians(latitud))*cos(radians(longitud)-:lon)) * :R < :radio
        ";

        $params = [
            'lat'    => deg2rad($lat),
            'lon'    => deg2rad($lon),
            'minLat' => $minLat,
            'minLon' => $minLon,
            'maxLat' => $maxLat,
            'maxLon' => $maxLon,
            'radio'  => $radio,
            'R'      => $R,
        ];

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute($params);
            $resultados = $statement->fetchAll(\PDO::FETCH_UNIQUE|\PDO::FETCH_ASSOC);
            return $resultados;
        } catch (\PDOException $e) {
            //echo $e->getMessage();
            return array();
        }
    }
}

?>