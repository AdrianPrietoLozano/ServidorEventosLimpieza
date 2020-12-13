<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

require_once __DIR__ . "/../actualizarKNN.php";

return function(App $app) {

    // obtiene todos los eventos activos donde participa el usuario
    $app->get('/participaciones/usuario/{idUsuario:[0-9]+}', function(Request $request, Response $response, array $args) {

        $participacionesDB = new participacionEventosDB($this->db);
        $resultado = $participacionesDB->findAllParticipacionesUsuario($args["idUsuario"]);

        return $response->withJson($resultado);
    });

    // inserta una participacion de un usuario a un evento
    $app->post("/participaciones/usuario", function(Request $request, Response $response, array $args) {
        $resultado = "0";
        $mensaje = "Ocurrió un error.";

        if (!comprobarBodyParams($request, ["idUsuario", "idEvento"])) {
            return $response->withJson(["resultado" => "0", "mensaje" => "Datos incompletos"]);
        }

        $idUsuario = $request->getParsedBodyParam("idUsuario", $default = -1);
        $idEvento = $request->getParsedBodyParam("idEvento", $default = -1);

        $participacionesDB = new participacionEventosDB($this->db);

        if($participacionesDB->participaEnEvento($idEvento, $idUsuario)) {
            $resultado = "2"; // el usuario ya participa en el evento
            $mensaje = "Error. Ya participas en este evento.";
        } else {
            if ($participacionesDB->insert($idUsuario, $idEvento) != -1) {
                $resultado = "1";
                $mensaje = "Operación exitosa";
                actualizarKnnDesdeIdEvento($this->db, $idUsuario, $idEvento);
            } else {
                $resultado = "0";
                $mensaje = "Ocurrió un error.";
            }  
        }

        return $response->withJson(["resultado" => $resultado, "mensaje" => $mensaje]);

    });

    // elimina una participacion a un evento de un usuario
    $app->delete("/participaciones/usuario/{idUsuario:[0-9]+}/{idEvento:[0-9]+}", function(Request $request, Response $response, array $args) {

        $json = array();
        $resultado = "0";
        $mensaje = "Ocurrió un error";

        $participacionesDB = new participacionEventosDB($this->db);
        if ($participacionesDB->delete($args["idUsuario"], $args["idEvento"])) {
            $resultado = "1";
            $mensaje = "Operación exitosa";            
        }

        $json["resultado"] = $resultado;
        $json["mensaje"] = $mensaje;

        return $response->withJson($json);
    });

    // obtiene todos los usuarios que participan en el evento
    $app->get("/participaciones/evento/{idEvento:[0-9]+}", function(Request $request, Response $response, array $args) {

    });

    // inserta los puntos de los usuario que participaron en el evento. Se recibe un array de ids de usuarios.
    $app->post("/participaciones/evento/{idEvento:[0-9]+}", function(Request $request, Response $response, array $args) {

    });
}



?>

