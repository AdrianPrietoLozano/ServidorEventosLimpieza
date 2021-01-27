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
}



?>

