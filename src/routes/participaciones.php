<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

//require_once __DIR__ . "/../actualizarKNN.php";
require_once __DIR__ . "/../tablesDB/ParticipacionEventosDB.php";
require_once __DIR__ . "/../tablesDB/EventoDB.php";

return function(App $app) {

    // obtiene todos los eventos activos donde participa el usuario
    $app->get('/participaciones/usuario', function(Request $request, Response $response, array $args) {

        $idUsuario = $request->getAttribute("token")["data"]->id;
        $participacionesDB = new ParticipacionEventosDB($this->db);
        $resultado = $participacionesDB->findAllParticipacionesUsuario($idUsuario);

        return $response->withJson($resultado);
    });

    // inserta una participacion de un usuario a un evento
    $app->post("/participaciones/usuario", function(Request $request, Response $response, array $args) {
        $resultado = "0";
        $mensaje = "Ocurrió un error.";

        if (!comprobarBodyParams($request, ["idEvento"])) {
            return $response->withJson(["resultado" => "0", "mensaje" => "Datos incompletos"]);
        }

        $idUsuario = $request->getAttribute("token")["data"]->id;
        $idEvento = $request->getParsedBodyParam("idEvento", $default = -1);

        $participacionesDB = new ParticipacionEventosDB($this->db);

        if($participacionesDB->participaEnEvento($idEvento, $idUsuario)) {
            $resultado = "2"; // el usuario ya participa en el evento
            $mensaje = "Error. Ya participas en este evento.";
        } else {
            if ($participacionesDB->insert($idUsuario, $idEvento) != -1) {
                $resultado = "1";
                $mensaje = "Operación exitosa";
                //actualizarKnnDesdeIdEvento($this->db, $idUsuario, $idEvento);
            } else {
                $resultado = "0";
                $mensaje = "Ocurrió un error.";
            }  
        }

        return $response->withJson(["resultado" => $resultado, "mensaje" => $mensaje]);

    });

    // elimina una participacion a un evento de un usuario
    $app->delete("/participaciones/usuario/{idEvento:[0-9]+}", function(Request $request, Response $response, array $args) {

        $json = array();
        $resultado = "0";
        $mensaje = "Ocurrió un error";

        $participacionesDB = new ParticipacionEventosDB($this->db);
        $idUsuario = $request->getAttribute("token")["data"]->id;
        if ($participacionesDB->delete($idUsuario, $args["idEvento"])) {
            $resultado = "1";
            $mensaje = "Operación exitosa";            
        }

        $json["resultado"] = $resultado;
        $json["mensaje"] = $mensaje;

        return $response->withJson($json);
    });

    // obtiene todos los usuarios que participan en el evento
    $app->get("/participaciones/evento/{idEvento:[0-9]+}", function(Request $request, Response $response, array $args) {

    	$participacionesDB = new ParticipacionEventosDB($this->db);
        $resultado = $participacionesDB->findAllParticipantesEnEvento($args["idEvento"]);

        return $response->withJson($resultado);

    });

    // inserta los puntos de los usuario que participaron en el evento. Se recibe un array de ids de usuarios.
    $app->post("/participaciones/evento/{idEvento:[0-9]+}", function(Request $request, Response $response, array $args) {

    	if (!comprobarBodyParams($request, ["idsParticipantes"])) {
            return $response->withJson(["resultado" => "0", "mensaje" => "Datos incompletos"]);
        }

        $idEvento = $args["idEvento"];
        $idUsuario = $request->getAttribute("token")["data"]->id;
        $idsParticipantes = $request->getParsedBodyParam("idsParticipantes");
        $puntos = 5;


        $eventoDB = new EventoDB($this->db);
    	if ($eventoDB->isEventoAdministrado($idEvento)) {
    		return $response->withJson(["resultado" => "0", "mensaje" => "Error. Este evento ya ha sido administrado"]);
    	}

        $participacionesDB = new ParticipacionEventosDB($this->db);
        if (!$participacionesDB->updatePuntosUsuarios($idsParticipantes, $puntos)) {
        	return $response->withJson(["resultado" => "0", "mensaje" => "Ocurrió un error"]);
        }

        
        $eventoDB->setEventoAdministrado($idEvento, true);

        $puntosUsuario = in_array($idUsuario, $idsParticipantes) ? 5 : 0;

        return $response->withJson(["resultado" => "1",
        	"mensaje" => "Operación éxitosa",
        	"puntos" => $puntosUsuario]);

    });
}



?>

