<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Http\UploadedFile;

require_once __DIR__ . "/config/config.php";

function comprobarBodyParams(Request $request, array $valores) {

	foreach ($valores as $val) {
		if (!$request->getParsedBodyParam($val, $default = null)) {
			return false;
		}
	}

	return true;
}

function ejecutarFetchQuery($conexion, $query, $params, $returnError) {
    try {
        $statement = $conexion->prepare($query);
        $statement->execute($params);
        return $statement->fetch(\PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        return $returnError;
    }
}

function normalizar(&$datos, $min_max) {
    array_walk($datos, function (&$value, $key) use ($min_max) {
        array_walk($value, function (&$val, $clave) use ($min_max) {
                if ($min_max[$clave]["max"] === $min_max[$clave]["min"]) return; // validar division por zero
                $val = ($val - $min_max[$clave]["min"]) / ($min_max[$clave]["max"] - $min_max[$clave]["min"]);
            });
        });
}

function obtenerRecomendaciones($entrenamiento, $prueba, $k, $limite = 20) {
    $knn = new KNN($entrenamiento);
    $recomendaciones = array();
    foreach ($prueba as $key => $value) {
        if (count($recomendaciones) > $limite) break;
        $nuevasRecomendaciones = $knn->getPredicciones($value, $k);
        $recomendaciones = array_unique(array_merge($recomendaciones, $nuevasRecomendaciones), SORT_NUMERIC);
    }

    return $recomendaciones;
}

// SOLO PARA PRUEBAS
function distancia($lat1, $lon1, $lat2, $lon2, $unit) {
      if (($lat1 == $lat2) && ($lon1 == $lon2)) {
        return 0;
      }
      else {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit = strtoupper($unit);

        if ($unit == "K") {
          return ($miles * 1.609344);
        } else if ($unit == "N") {
          return ($miles * 0.8684);
        } else {
          return $miles;
        }
      }
    }

function sendNotification($targets, $titulo, $mensaje){
    //API URL of FCM
    $url = 'https://fcm.googleapis.com/fcm/send';
	
	$msg = array(
		"title" => $titulo,
		"message" => $mensaje, 
		"sound" => "default"
	);

	$fields = array();
	$fields["data"] = $msg;
	if (is_array($targets)){
	    $fields['registration_ids'] = $targets;
	} else{
	    $fields['to'] = $targets;
	}
 
    //header includes Content type and api key
    $headers = array(
        'Content-Type:application/json',
        'Authorization:key='.FCM_KEY
    );
                
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    $result = curl_exec($ch);
    if ($result === FALSE) {
        die('FCM Send Error: ' . curl_error($ch));
    }
    curl_close($ch);
    return $result;
}

?>