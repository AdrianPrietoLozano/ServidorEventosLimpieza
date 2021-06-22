<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

require_once  __DIR__ . "/../tablesDB/EventoDB.php";
require_once  __DIR__ . "/../tablesDB/ReporteDB.php";
require_once  __DIR__ . "/../tablesDB/KnnDB.php";
require_once  __DIR__ . "/../tablesDB/ParticipacionEventosDB.php";
require_once __DIR__ . "/../utilidades.php";
require_once __DIR__ . "/../dijkstra/dijkstra.php";


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

            //$idUsuario = $request->getQueryParam("ambientalista_id", $default = null);
            $idUsuario = $request->getAttribute("token")["data"]->id;
            if ($idUsuario) {
                $json["evento"]["usuario_participa"] = $participacionDB->participaEnEvento($args["id"], $idUsuario);
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

        $queryParams = array("reporte_id", "titulo", "fecha", "hora", "descripcion");

        if (comprobarBodyParams($request, $queryParams)) {
            $eventoDB = new EventoDB($this->db);

            $ambientalista_id = $request->getAttribute("token")["data"]->id;
            $reporte_id = $request->getParsedBodyParam($queryParams[0]);
            $titulo = $request->getParsedBodyParam($queryParams[1]);
            $fecha = $request->getParsedBodyParam($queryParams[2]);
            $hora = $request->getParsedBodyParam($queryParams[3]);
            $descripcion = $request->getParsedBodyParam($queryParams[4]);
            $puntos = rand(5, 20);

            if ($eventoDB->existeEventoConReporte($reporte_id)) {
                $resultado = "2";
                $mensaje = "Ya existe un evento para ese reporte.";

            } else {

                $id_evento = $eventoDB->insert($ambientalista_id, $reporte_id, $titulo,
                    $fecha, $hora, $descripcion, $puntos);

                if ($id_evento != -1) {
                    $resultado = "1";
                    $mensaje = "Evento creado exitosamente.";
                    $json["id_evento"] = $id_evento;

                    $reporteDB = new ReporteDB($this->db);
                    $knnDB = new KnnDB($this->db);

                    try { // insertar en tabla KNN
                        $datosReporte = $reporteDB->find($reporte_id);
                        $insertado = $knnDB->insert($id_evento, $datosReporte["residuos"],
                            $datosReporte["volumen"], str_replace("/", "-", $fecha), $hora,
                            $datosReporte["latitud"], $datosReporte["longitud"]);
                        $idUsuario = $request->getAttribute("token")["data"]->id;

                        // hacer que el ambientalista participe en el evento
                        $participacionesDB = new ParticipacionEventosDB($this->db);
                        $participacionesDB->insert($ambientalista_id, $id_evento);
                    } catch (Exception $e) {}
                }
            }
        }

        $json["estatus"]["resultado"] = $resultado;
        $json["estatus"]["mensaje"] = $mensaje;

        return $response->withJson($json); 

    });

    $app->get("/eventos/usuario", function(Request $request, Response $response, array $args) {
        $idUsuario = $request->getAttribute("token")["data"]->id;
        $eventoDB = new EventoDB($this->db);
        $resultado = $eventoDB->findAllEventosUsuario($idUsuario);

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
        } catch (PDOException $e) {
            //echo $statement->errorInfo()[2];
            echo "error";
        }

        //sendNotification()
    });

    $app->post("/eventos/dijkstra", function(Request $request, Response $response, array $args) {
        // 1. Obtener los eventos que esten a una distancia de $km/2 de la ubicación del usuario
        // 2. Conectar los eventos que esten a $0.5mk de distancia y su peso será los puntos. De esta forma se tiene el grafo
        // 3. Aplicar Dijkstra
        // 4. Retornar resultado

        /*
        if (!comprobarBodyParams($request, ["latitud", "longitud", "puntos"])) {
            return $response->withJson(["estatus" => ["resultado" => "0", "mensaje" => "Datos incompletos"]]);
        }*/

        // ----- 1 ------
        $lat = $request->getParsedBodyParam("latitud");
        $lon = $request->getParsedBodyParam("longitud");
        $puntos = $request->getParsedBodyParam("puntos");
        $km = 5;

        $eventoDB = new EventoDB($this->db);
        $eventos = $eventoDB->getEventosCercanos($lat, $lon, $km / 2);
        $eventos["S"] = ["latitud" => $lat, "longitud" => $lon];
        //print_r($eventos);
        //exit();
        

        // ----- 2 ------
        $grafo = array();

        foreach ($eventos as $clave => &$e) {
            $grafo[$clave] = array();
            foreach ($eventos as $clave2 => &$e2) {
                if ($clave == $clave2) continue;
                
                $dis = distancia($e["latitud"], $e["longitud"],
                        $e2["latitud"], $e2["longitud"], "k");

                if ($dis <= 0.7) {
                    if ($clave2 == "S") {
                        array_push($grafo[$clave], [$clave2, $dis, (int)$e["puntos"]]);
                    } else {
                        array_push($grafo[$clave], [$clave2, $dis, (int)$e2["puntos"]]);
                    }
                }
            }
        }

        //print_r($grafo);

        /*
        if (count($grafo["S"]) == 0) {
            return $response->withJson(["estatus" => ["resultado" => "0", "mensaje" => "No se econtró una ruta"]]);
        }*/

        //-------- 3 ---------
        list($distancias, $prev) = dijkstra($grafo, "S");

        $mejorEvento = ["idEvento" => null, "distancia" => INF];
        $mayorPuntos = ["idEvento" => null, "puntos" => -INF];
        foreach ($distancias as $clave => $dis) {
            if ($dis[1] >= $puntos && $dis[0] < $mejorEvento["distancia"]) {
                $mejorEvento = ["idEvento" => $clave, "distancia" => $dis[0]];
            }

            if ($dis[1] > $mayorPuntos["puntos"])
                $mayorPuntos = ["idEvento" => $clave, "puntos" => $dis[1]];
        }

        // TODO: esto está mal
        if ($mejorEvento["idEvento"] == null) {
            $mejorEvento = $mayorPuntos;
            //$max_key = array_search(max($distancias), $distancias);
            //$mejorEvento = ["idEvento" => $max_key,
                            //"distancia" => $distancias[$max_key][1]];
        }

        //print_r($mejorEvento);

        // ----- construir ruta -----
        $ruta = array();
        $idEvento = $mejorEvento["idEvento"];
        while ($idEvento != "S" && $idEvento != null) {
            array_unshift($ruta, $idEvento);
            $idEvento = $prev[$idEvento];
        }

        //print_r($ruta);

        $rutaEventos = array();
        foreach ($ruta as $evento) {
            $rutaEventos[] = ["id" => $evento] + $eventos[$evento];
        }

        return $response->withJson(["ruta" => $rutaEventos,
                                    "estatus" => ["resultado" => "1", "mensaje" => "Todo correcto"]]);


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

