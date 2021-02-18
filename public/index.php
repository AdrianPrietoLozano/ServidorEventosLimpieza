<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';

require_once __DIR__ . "/../src/utilidades.php";
require_once __DIR__ . "/../src/config/config.php";

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$config['db']['host']   = SERVER;
$config['db']['user']   = USER;
$config['db']['pass']   = PASSWORD;
$config['db']['dbname'] = DBNAME;

// # create new Slim instance
$app = new \Slim\App(['settings' => $config]);


//------- CORS ----
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});
//-------


//------------JWT-------------
$app->add(new Tuupola\Middleware\JwtAuthentication([
    "secure" => false,
    "secret" => "djl34hQaSd@",
    "ignore" => ["/usuario", "/usuario/login", "/usuario/google/login", "/usuario/registrar"],
    "algorithm" => ["HS256"],
    "error" => function ($response, $arguments) {
        $data["resultado"] = "0";
        $data["mensaje"] = $arguments["message"];
        return $response
            ->withHeader("Content-Type", "application/json")
            ->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
]));



//-----------

$contaniner = $app->getContainer();
$contaniner["db"] = function($c) {
  $db = $c['settings']['db'];
  try {
    $conexion = new \PDO("mysql:host=localhost;dbname=eventos_limpieza",
      $db["user"], $db["pass"], array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));
    return $conexion;
  } catch (\PDOException $e) {
    exit($e->getMessage());
  }
};

//$contaniner["images_directory"] = __DIR__ . "/imagenes";
$contaniner["images_directory"] = "imagenes/";

$routesEventos = require __DIR__ . '/../src/routes/eventos.php';
$routesReportes = require __DIR__ . '/../src/routes/reportes.php';
$routesParticipaciones = require __DIR__ . '/../src/routes/participaciones.php';
$routesRecomendaciones = require __DIR__ . '/../src/routes/recomendaciones.php';
$routesUsuario = require __DIR__ . '/../src/routes/usuario.php';
$routesBusquedas = require __DIR__ . '/../src/routes/busquedas.php';

$routesEventos($app);
$routesReportes($app);
$routesParticipaciones($app);
$routesRecomendaciones($app);
$routesUsuario($app);
$routesBusquedas($app);



//cors
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
    throw new HttpNotFoundException($request);
});

$app->run();

?>
