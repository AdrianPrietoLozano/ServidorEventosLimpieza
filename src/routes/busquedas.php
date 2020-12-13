<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

require_once  __DIR__ . "/../tablesDB/EventoDB.php";

return function(App $app) {

	$app->get("/busqueda/eventos/{query}", function(Request $request, Response $response, array $args) {
		$json = array();

		if ($args["query"] != "") {
			$eventoDB = new EventoDB($this->db);
			$json = $eventoDB->findByTitulo($args["query"]);
		}

        return $response->withJson($json);
    });
}



?>
