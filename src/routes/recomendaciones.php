<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

require __DIR__ . "/../funcionesKNN.php";

return function(App $app) {

    $app->get('/recomendaciones/usuario/{idUsuario:[0-9]+}', function(Request $request, Response $response, array $args) {

        $recomendaciones = obtenerRecomendaciones($args["idUsuario"], $this->db);

        return $response->withJson($recomendaciones);
    });

    $app->get("/recomendaciones/evento/{idEvento:[0-9]+}", function(Request $request, Response $response, array $args) {

        return $response->write("falta por hacer");
    });
}



?>

