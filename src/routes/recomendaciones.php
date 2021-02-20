<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

//require __DIR__ . "/../funcionesKNN.php";
require_once __DIR__ . "/../tablesDB/EventoDB.php";
require_once __DIR__ . "/../tablesDB/KnnDB.php";
require_once __DIR__ . "/../knn/KNN.php";
require_once __DIR__ . "/../utilidades.php";

return function(App $app) {

    $app->get('/recomendaciones/usuario', function(Request $request, Response $response, array $args) {

        $eventoDB = new EventoDB($this->db);
        $knnDB = new KnnDB($this->db);

        $idUsuario = $request->getAttribute("token")["data"]->id;
        $participacionesUsuario = $knnDB->findAllEventosParticipaUsuario($idUsuario); // test
        $participacionesUsuario = array_slice($participacionesUsuario, 0, 8, true);
        if (empty($participacionesUsuario)) {
            return $response->withJson($eventoDB->findAllEventosPopulares($idUsuario));
        }

        $datos = $knnDB->findAllDatosEventos($idUsuario); // train
        if (empty($datos)) {
            return $response->withJson($eventoDB->findAllEventosPopulares($idUsuario));
        }
        
        // normalizar datos
        $min_max = $knnDB->findAllMinMaxValues();
        if (!empty($min_max)) {
            normalizar($datos, $min_max);
            normalizar($participacionesUsuario, $min_max);
        }
        
        $k = count($participacionesUsuario) > 2 ? 5 : 10;
        $predicciones = obtenerRecomendaciones($datos, $participacionesUsuario, $k, 25);
        return $response->withJson($eventoDB->findAllEventosIn($predicciones));
    });

    $app->get("/recomendaciones/evento/{idEvento:[0-9]+}", function(Request $request, Response $response, array $args) {

        $idUsuario = $request->getAttribute("token")["data"]->id;
        $idEvento = $args["idEvento"];
        $eventoDB = new EventoDB($this->db);
        $knnDB = new KnnDB($this->db);

        $datosEvento[0] = $knnDB->find($idEvento); // test
        if (empty($datosEvento[0])) {
            return $response->withJson($eventoDB->findAllEventosPopulares($idUsuario));
        }

        $datos = $knnDB->findAllDatosEventos($idUsuario); // train
        if (empty($datos)) {
            return $response->withJson($eventoDB->findAllEventosPopulares($idUsuario));
        }

        // normalizar datos
        $min_max = $knnDB->findAllMinMaxValues();
        if (!empty($min_max)) {
            normalizar($datos, $min_max);
            normalizar($datosEvento, $min_max);
        }
        
        $predicciones = obtenerRecomendaciones($datos, $datosEvento, 6, 6);
        if (!empty($predicciones) && reset($predicciones) == $idEvento)
            array_shift($predicciones); // eliminar el primer elemento porque es el mismo que idEvento

        
        return $response->withJson($eventoDB->findAllEventosIn($predicciones));
    });

    // SOLO PARA PRUEBAS
    $app->get("/recomendaciones", function(Request $request, Response $response, array $args) {
        //echo date("n", $date);
        //echo "<br>";
        //echo date("H", $date);
        /*
        $query = "
            SELECT * from KNN
        ";

        try {

        	$min_max = ["usuario_id" => ["min"=>6136, "max"=>6137],
        	"escombro" => ["min" => 1, "max" => 34],
        	"envases" => ["min" => 1, "max" => 34],
        	"carton" => ["min" => 1, "max" => 34],
        	"bolsas" => ["min" => 1, "max" => 34],
        	"electricos" => ["min" => 1, "max" => 34],
        	"pilas" => ["min" => 1, "max" => 34],
        	"neumaticos" => ["min" => 1, "max" => 34],
        	"medicamentos" => ["min" => 1, "max" => 34],
        	"varios" => ["min" => 1, "max" => 34],
        	"volumen_chico" => ["min" => 1, "max" => 34],
        	"volumen_mediano" => ["min" => 1, "max" => 34],
        	"volumen_grande" => ["min" => 1, "max" => 34]];

        	print_r($min_max);
        	echo "-------";

            $statement = $this->db->query($query);
            $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
            echo "<br>--------------------------------------------------------<br><br>";

            array_walk($results, function (&$value, $key) use ($min_max) {
            	array_walk($value, function (&$val, $clave) use ($min_max) {
            		if ($min_max[$clave]["max"] === $min_max[$clave]["min"]) return; // validar division por zero
            		$val = ($val - $min_max[$clave]["min"]) / ($min_max[$clave]["max"] - $min_max[$clave]["min"]);
            	});
            });

            print_r($results);

        } catch (\PDOException $e) {
            //exit($e->getMessage());
            echo "mal";
            return array();
        }
        */
    });

    // SOLO PARA PRUEBAS
    $app->get("/recomendaciones/prueba/{idUsuario:[0-9]+}", function(Request $request, Response $response, array $args) {

        $idUsuario = $args["idUsuario"];
        $eventoDB = new EventoDB($this->db);
        $knnDB = new KnnDB($this->db);

        $participacionesUsuario = $knnDB->findAllEventosParticipaUsuario($idUsuario); // test
        //$participacionesUsuario = array_slice($participacionesUsuario, 0, 8, true);
        if (empty($participacionesUsuario)) {
            return $response->withJson(["cerca"=>[], "medio"=>[], "lejos"=>[]]);
        }

        $datos = $knnDB->findAllDatosEventos($idUsuario); // train
        if (empty($datos)) {
            return $response->withJson(["cerca"=>[], "medio"=>[], "lejos"=>[]]);
        }

        
        // normalizar datos
        $min_max = $knnDB->findAllMinMaxValues();
        if (!empty($min_max)) {
            normalizar($datos, $min_max);
            normalizar($participacionesUsuario, $min_max);
        }

        $knn = new KNN($datos);
        $cerca = array();
        $medio = array();
        $lejos = array();

        foreach ($participacionesUsuario as $key => $value) {
            $nuevasRecomendaciones = $knn->getPredicciones($value, count($datos));
            $cerca = array_merge($cerca, array_slice($nuevasRecomendaciones, 0, 10));
            $medio = array_merge($medio, array_slice($nuevasRecomendaciones, 10, 10));
            $lejos = array_merge($lejos, array_slice($nuevasRecomendaciones, 20));
        }


        $cerca = array_unique($cerca, SORT_NUMERIC);
        $medio = array_unique($medio, SORT_NUMERIC);
        $lejos = array_unique($lejos, SORT_NUMERIC);
        
        $medio = array_diff($medio, $cerca);
        $lejos = array_diff($lejos, array_merge($cerca, $medio));

        /*
        foreach ($datos as $key => &$value) {
            if (in_array($key, $cerca)) {
                $value["class"] = "cerca";
            } else if (in_array($key, $medio)) {
                $value["class"] = "medio";
            } else {
                $value["class"] = "lejos";
            }
        }

        
        $fp = fopen("cerca_lejos.csv", "w");
        fputcsv($fp, array_keys($datos[1]));

        foreach ($datos as $key => $value) {
            fputcsv($fp, $value);
        }

        fclose($fp);
        //exit();
        */

        return $response->withJson([
            "cerca" => $eventoDB->prueba($cerca),
            "medio" => $eventoDB->prueba($medio),
            "lejos" => $eventoDB->prueba($lejos) 
        ]);

    });

}



?>

