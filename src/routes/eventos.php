<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

require_once  __DIR__ . "/../tablesDB/EventoDB.php";
require_once  __DIR__ . "/../tablesDB/ReporteDB.php";
require_once  __DIR__ . "/../tablesDB/KnnDB.php";
require_once  __DIR__ . "/../tablesDB/ParticipacionEventosDB.php";

return function(App $app) {


    $app->get('/eventos', function( Request $request, Response $response, array $args) {
        $eventoDB = new EventoDB($this->db);
        $resultado = $eventoDB->findAll();

        return $response->withJson($resultado);
    });


    $app->get("/eventos/{id:[0-9]+}", function(Request $request, Response $response, array $args) {
        $json = array();
        $resultado = "0";
        $mensaje = "Ocurrió un error";

        $eventoDB = new EventoDB($this->db);
        $evento = $eventoDB->find($args["id"]);

        if ($evento) {
            $json["evento"] = $evento;

            $reporteDB = new ReporteDB($this->db);
            $json["evento"]["residuos"] = $reporteDB->obtenerResiduosReporte($json["evento"]["id_reporte"]);

            $participacionDB = new ParticipacionEventosDB($this->db);
            $json["evento"]["personas_unidas"] = $participacionDB->numPersonasParticipando($args["id"]);

            $id_usuario = $request->getQueryParam("ambientalista_id", $default = null);
            if ($id_usuario) {
                $json["evento"]["usuario_participa"] = $participacionDB->participaEnEvento($args["id"], $id_usuario);
            }

            $resultado = "1";
            $mensaje = "Operación éxitosa";
        }

        $json["estatus"]["resultado"] = $resultado;
        $json["estatus"]["mensaje"] = $mensaje;

        return $response->withJson($json); 
    });


    $app->post("/eventos", function(Request $request, Response $response, array $args) {
        $json = array();
        $resultado = "0";
        $mensaje = "Ocurrió un error";

        $queryParams = array("ambientalista_id", "reporte_id", "titulo", "fecha", "hora", "descripcion");

        if (comprobarBodyParams($request, $queryParams)) {
            $eventoDB = new EventoDB($this->db);

            if ($eventoDB->existeEventoConReporte($request->getParsedBodyParam($queryParams[1]))) {
                $resultado = "2";
                $mensaje = "Ya existe un evento para ese reporte.";

            } else {
                $ambientalista_id = $request->getParsedBodyParam($queryParams[0]);
                $reporte_id = $request->getParsedBodyParam($queryParams[1]);
                $titulo = $request->getParsedBodyParam($queryParams[2]);
                $fecha = $request->getParsedBodyParam($queryParams[3]);
                $hora = $request->getParsedBodyParam($queryParams[4]);
                $descripcion = $request->getParsedBodyParam($queryParams[5]);

                $id_evento = $eventoDB->insert($ambientalista_id, $reporte_id, $titulo,
                    $fecha, $hora, $descripcion);

                if ($id_evento != -1) {
                    $resultado = "1";
                    $mensaje = "Evento creado exitosamente.";
                    $json["id_evento"] = $id_evento;

                    $reporteDB = new ReporteDB($this->db);
                    $knnDB = new KnnDB($this->db);

                    try { // insertar en tabla KNN
                        $datosReporte = $reporteDB->find($request->getParsedBodyParam($queryParams[1]));
                        $insertado = $knnDB->insert($id_evento, $datosReporte["residuos"],
                            $datosReporte["volumen"], str_replace("/", "-", $fecha), $hora);
                    } catch (Exception $e) {}
                }
            }
        }

        $json["estatus"]["resultado"] = $resultado;
        $json["estatus"]["mensaje"] = $mensaje;

        return $response->withJson($json); 

    });

    $app->get("/eventos/usuario/{idUsuario:[0-9]+}", function(Request $request, Response $response, array $args) {
        $eventoDB = new EventoDB($this->db);
        $resultado = $eventoDB->findAllEventosUsuario($args["idUsuario"]);

        return $response->withJson($resultado);
    });

    $app->get("/eventos/notificacion", function(Request $request, Response $response, array $args) {
        $sql = "
            SELECT DISTINCT fcm_token
            FROM ambientalista
            WHERE fcm_token IS NOT NULL
        ";

        try {
            $statement = $this->db->query($sql);
            $targets = $statement->fetchAll(PDO::FETCH_COLUMN);

            sendNotification($targets, "Prueba", "prueba");
        } catch (\PDOException $e) {
            //echo $statement->errorInfo()[2];
            echo "error";
        }

        //sendNotification()
    });

    $app->post("/eventos/prueba/hector", function(Request $request, Response $response, array $args) {

        if (!comprobarBodyParams($request, ["latitud", "longitud", "radio"])) {
            return $response->withJson(["respuesta" => "0", "mensaje" => "Datos incompletos"]);
        }

        $R = 6371;  // earth's mean radius, km

        $lat = $request->getParsedBodyParam("latitud");
        $lon = $request->getParsedBodyParam("longitud");
        $radio = $request->getParsedBodyParam("radio");
        $maxLat = $lat + rad2deg($radio/$R);
        $minLat = $lat - rad2deg($radio/$R);
        $maxLon = $lon + rad2deg(asin($radio/$R) / cos(deg2rad($lat)));
        $minLon = $lon - rad2deg(asin($radio/$R) / cos(deg2rad($lat)));

        $sql = "
        SELECT *
        FROM (
            SELECT E._id, REPORTE.latitud, REPORTE.longitud
            FROM evento_limpieza AS E
            JOIN reporte_contaminacion AS REPORTE
                ON E.reporte_id = REPORTE._id
            WHERE latitud BETWEEN :minLat AND :maxLat
                AND longitud BETWEEN :minLon AND :maxLon
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
            'radio'    => $radio,
            'R'      => $R,
        ];

        try {
            $statement = $this->db->prepare($sql);
            $statement->execute($params);
            $resultados = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return $response->withJson($resultados);
        } catch (\PDOException $e) {
            echo $e->getMessage();
            return $response->withJson(array());
        }

        //sendNotification()
    });
}

/*
DELETE FROM participa_evento;
DELETE FROM KNN;
DELETE FROM evento_limpieza;
ALTER TABLE participa_evento AUTO_INCREMENT = 1;
ALTER TABLE KNN AUTO_INCREMENT = 1;
ALTER TABLE evento_limpieza AUTO_INCREMENT = 1;

*/


?>

