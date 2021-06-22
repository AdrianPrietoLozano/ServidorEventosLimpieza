<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

require_once  __DIR__ . "/../tablesDB/EventoDB.php";

return function(App $app) {

    $app->post('/dijkstra', function(Request $request, Response $response, array $args) {
        
        if (!comprobarBodyParams($request, ["latitud", "longitud", "puntos"])) {
            return $response->withJson(["estatus" => ["resultado" => "0", "mensaje" => "Datos incompletos"]]);
        }

        $R = 6371;  // earth's mean radius, km
        $radio = 1;
        $lat = $request->getParsedBodyParam("latitud");
        $lon = $request->getParsedBodyParam("longitud");
        $puntosMax = $request->getParsedBodyParam("puntos");

        $maxLat = $lat + rad2deg($radio/$R);
        $minLat = $lat - rad2deg($radio/$R);
        $maxLon = $lon + rad2deg(asin($radio/$R) / cos(deg2rad($lat)));
        $minLon = $lon - rad2deg(asin($radio/$R) / cos(deg2rad($lat)));

        $sql = "
        SELECT *
        FROM (
            SELECT E._id, REPORTE.latitud, REPORTE.longitud, E.puntos
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
            
            $resultados = $statement->fetchAll(\PDO::FETCH_UNIQUE|\PDO::FETCH_ASSOC);
            
            $total = count($resultados);

            $vertices = array();
            $eventoOrigen = 0;
            $disMenor = PHP_INT_MAX;

            foreach ($resultados as $clave => $elemento)
            {
                $vertices[$clave] = $elemento["puntos"];
                if(distance($lat, $lon, $elemento["latitud"], $elemento["longitud"]) < $disMenor)
                {
                    $eventoOrigen = $clave;
                    $disMenor = distance($lat, $lon, $elemento["latitud"], $elemento["longitud"]);
                    $puntosIniciales = $elemento["puntos"];
                }
            }

            $distEvento = 500;
                
            $grafo = array();

        foreach ($resultados as $clave => &$e)
        {
            $grafo[$clave] = array();
            foreach ($resultados as $clave2 => &$e2)
            {

                if ($clave == $clave2) continue;
                
                $dis = distance($e["latitud"], $e["longitud"],
                        $e2["latitud"], $e2["longitud"]);

                if ($dis <= $distEvento)
                {
                    array_push($grafo[$clave], ["id" => $clave2,"Metros" => $dis, "Puntos" => (int)$e2["puntos"]]);
                }
            }
        }
        
        //return $response->withJson($grafo);

            //print_r(count($grafo[343]));
            $rutaAux = dijkstra2($total, $grafo, $eventoOrigen, $puntosMax, $vertices);
            $ruta = array();
            for($i = 0; $i < count($rutaAux); $i++)
            {
                $ruta[$rutaAux[$i]] = array();
                array_push($ruta[$rutaAux[$i]], ["latitud" => $resultados[$rutaAux[$i]]["latitud"],
                                                        "longitud" => $resultados[$rutaAux[$i]]["longitud"],
                                                        "puntos" => $resultados[$rutaAux[$i]]["puntos"]]);
            }

            echo "<br><br>";
            print_r($rutaAux);
            /*return $response->withJson(["ruta" => $ruta,
                "estatus" => ["resultado" => "1", "mensaje" => "Todo correcto"]]);*/
        } 
        
        catch (\PDOException $e) {
            echo $e->getMessage();
            return $response->withJson($ruta);
        }
        
    });

    function distance($lat1, $lon1, $lat2, $lon2) {
     
      $theta = $lon1 - $lon2;
      $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
      $dist = acos($dist);
      $dist = rad2deg($dist);
      $miles = $dist * 60 * 1.1515;
          
      return ($miles / 0.00062137);
      
    }


    function dijkstra2($total, $grafo, $src, $puntosMax, $vertices)
    {
        $distancias = array();
        $padre = array();
        foreach (array_keys($grafo) as $key) {
            $distancias[$key] = [INF, 0];
            $padre[$key] = null;
        }

        $distancias[$src] = [0, 0];
        //print_r($distancias);

        $pq = new \Ds\PriorityQueue();

        $pq->push([$src, 0], 0);

        while (!$pq->isEmpty()) {
            $aux = $pq->pop();
            $u = $aux[0];

            $ruta[$u] = array();
            foreach ($grafo[$u] as $pair) {
                $v = $pair["id"];
                $peso = $pair["Metros"];
                $puntos = $pair["Puntos"];
                $alt = $distancias[$u][0] + $peso;

                if ($alt < $distancias[$v][0]) {
                    $distancias[$v][0] = $alt;
                    $distancias[$v][1] = $distancias[$u][1] + $puntos;
                    $padre[$v] = $u;
                    $pq->push([$v, $alt], $alt);
                }
            }
        }
        $padre[$src] = 0;
        $total = 0;
        $idMayor = 0;
        print_r($distancias);
        foreach ($distancias as $clave => $elemento)
        {
            if($elemento[1] > $total  && $elemento[1] <= $puntosMax)
            {
                $total = $elemento[1];
                echo "<br><br>";
                print_r($total);
                $idMayor = $clave;
            }
        }
        $aux = $idMayor;
        $cola = array();

        //print_r($padre);
        array_push($cola, $idMayor);

        doForEach($padre, $idMayor,$cola);

        $totalRuta = count($cola);
        $rutaAux = array();

        for($i = $totalRuta - 1; $i >= 0; $i--)
        {
            array_push($rutaAux, $cola[$i]);
        }

        return $rutaAux;
    }

    function doForEach($padre, $idMayor, &$cola)
    {
        foreach ($padre as $clave => $elemento)
        {
            if($clave == $idMayor && $elemento != 0)
            {
                array_push($cola, $elemento);

                doForEach($padre, $elemento, $cola);
            }
        }
    }
}
?>
