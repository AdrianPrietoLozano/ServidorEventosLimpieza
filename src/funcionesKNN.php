<?PHP
require __DIR__ . "/knn/KNN.php";

function &obtenerRecomendaciones($idUsuario, $conexion) {
	$datos = array();
	$etiquetas = array();
	$datosUsuario = array();

	if (existeUsuarioEnTablaKNN($conexion, $idUsuario)) {
		getDatosBD($conexion, $datos, $datosUsuario, $etiquetas, $idUsuario);

        //echo var_dump($datosUsuario);
		
		$knn = new KNN($datos, $etiquetas);
		$predicciones = $knn->getPredicciones($datosUsuario);

		if (count($predicciones) == 0) { // no hay vecinos cercanos
			// recomendar los eventos mas populares
			//echo "<br>sin vecinos<br>";
            return obtenerEventosMasPopulares($conexion, $idUsuario);
		} else { // el algoritmo si encontró vecinos cercanos
            $eventosRecomendados = generarRecomendacionesEventos($conexion, $predicciones, $idUsuario);
			
			if (count($eventosRecomendados) == 0) { // no hay recomendaciones
                //echo "<br>cero recomendaciones<br>";
                return obtenerEventosMasPopulares($conexion, $idUsuario);
            } else {
				//echo "<br>generados con el argoritmo<br>";
				return $eventosRecomendados;
            }
        }

    } else {
		// recomendar los eventos mas populares
		//echo "<br>el usuario no existe en la tabla<br>";
		return obtenerEventosMasPopulares($conexion, $idUsuario);
	}
        
	//mysqli_close($conexion);	
}


function existeUsuarioEnTablaKNN($conexion, $id_usuario)
{
    $select = "SELECT * FROM KNN WHERE usuario_id = {$id_usuario}";

    try {
		$statement = $conexion->query($select);
		return $statement->fetchColumn() > 0;
	} catch (\PDOException $e) {
            //exit($e->getMessage());
		return false;
    }
}



/* funcion personalizada */
function obtenerQueryJsonEvento($conexion, $query)
{
    try {
		$statement = $conexion->query($query);
		return $statement->fetchAll(\PDO::FETCH_ASSOC);
	} catch (\PDOException $e) {
		//exit($e->getMessage());
		return array();
	}
}

function &obtenerEventosMasPopulares($conexion, $id_ambientalista)
{
    /*
    $select = "SELECT evento._id AS id_evento, evento.titulo AS titulo, " .
        "DATE_FORMAT(evento.fecha_hora, '%d-%m-%Y') AS fecha, DATE_FORMAT(evento.fecha_hora, '%H:%i') AS hora, " .
        "reporte.fotografia AS foto " .
        "FROM (evento_limpieza AS evento JOIN reporte_contaminacion AS reporte ON evento.reporte_id = reporte._id) " .
            "WHERE evento._id IN " .
                "(SELECT evento_id FROM participa_evento " .
                    "WHERE NOW() <= fecha_hora_fin GROUP BY evento_id ORDER BY COUNT(evento_id) DESC) " .
                    "AND evento._id NOT IN " .
                        "(SELECT evento_id FROM participa_evento WHERE ambientalista_id = {$id_ambientalista}) LIMIT 20";
    */

    // eventos más populares donde no participa el usuario
    $select = "
    SELECT
        evento._id AS id_evento,
        evento.titulo AS titulo,
        DATE_FORMAT(evento.fecha_hora, '%d-%m-%Y') AS fecha,
        DATE_FORMAT(evento.fecha_hora, '%H:%i') AS hora,
        reporte.fotografia AS foto
    FROM participa_evento
    JOIN evento_limpieza AS evento
        ON participa_evento.evento_id = evento._id
    JOIN reporte_contaminacion AS reporte
        ON evento.reporte_id = reporte._id
    WHERE NOW() <= evento.fecha_hora
        AND evento._id NOT IN
            (SELECT evento_id
            FROM participa_evento
            WHERE ambientalista_id = {$id_ambientalista})
    GROUP BY id_evento
    ORDER BY COUNT(*) DESC
    LIMIT 20;
    ";

    $json = obtenerQueryJsonEvento($conexion, $select);

    if(count($json) == 0)
    {
        // selecciona los primeros 20 eventos en la BD donde el usuario aún no participa
        $select2 = "
        SELECT
            evento._id AS id_evento,
            evento.titulo AS titulo,
            DATE_FORMAT(evento.fecha_hora, '%d-%m-%Y') AS fecha,
            DATE_FORMAT(evento.fecha_hora, '%H:%i') AS hora,
            reporte.fotografia AS foto
        FROM evento_limpieza AS evento
        JOIN reporte_contaminacion AS reporte
            ON evento.reporte_id = reporte._id
        WHERE NOW() <= evento.fecha_hora
            AND evento._id
                NOT IN (SELECT evento_id FROM participa_evento
                        WHERE ambientalista_id = {$id_ambientalista})
        LIMIT 20;
        ";

        $json = obtenerQueryJsonEvento($conexion, $select2);
    }

    return $json;
}


/*NOTA: HACE FALTA HACER QUE CUANDO UN USUARIO NO ESTE EN LA TABLA KNN SE LE RECOMIENDE
OTRA COSA*/
function getDatosBD($conexion, &$datos, &$datosUsuario, &$etiquetas, $idUsuarioActual)
{
    $encontrado = false;
    $sql = "SELECT * FROM KNN WHERE usuario_id != {$idUsuarioActual}";
    $sqlDatosUsuario = "SELECT * FROM KNN WHERE usuario_id = {$idUsuarioActual}";

    $auxDatosUsuario = obtenerQueryJsonEvento($conexion, $sqlDatosUsuario)[0];
    if (count($auxDatosUsuario) == 0) {
    	$datosUsuario = array_fill(0, 12, 0);
    } else {
    	unset($auxDatosUsuario["usuario_id"]);
    	$datosUsuario = $auxDatosUsuario;
    }

    $datos = obtenerQueryJsonEvento($conexion, $sql);
    $etiquetas = array_column($datos, "usuario_id");

    array_walk($datos, function(&$elemento, $clave) {
    	unset($elemento["usuario_id"]);
    });

    /*
    if (count($datos) <= 0) {
    	return false;
    }

    foreach ($datos as &$registro) {
		if ($registro["usuario_id"] == $idUsuarioActual) {
			$datosUsuario = $registro;
			$encontrado = true;
			break;
		} else {
			$etiquetas[] = $registro["usuario_id"];
			$datos[] = $registro;
		} 
    }

    if (!$encontrado) {
    	$datosUsuario = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
    }
    */
}

function generarRecomendacionesEventos($conexion, &$idsUsuarios, $id_ambientalista)
{

    $json = array();

    /*
    $select2 = "SELECT evento._id AS id_evento, evento.titulo AS titulo, ".
        "DATE_FORMAT(evento.fecha_hora, '%d-%m-%Y') AS fecha, DATE_FORMAT(evento.fecha_hora, '%H:%i') AS hora, " .
        "reporte.fotografia AS foto " .
        "FROM (evento_limpieza AS evento JOIN reporte_contaminacion AS reporte ON evento.reporte_id = reporte._id) ".
        "WHERE evento._id IN (SELECT evento_id FROM participa_evento WHERE NOW() <= fecha_hora_fin AND ";

    */

    $ids = join(', ', $idsUsuarios);
    $select2 = "
    SELECT
        evento._id AS id_evento,
        evento.titulo AS titulo,
        DATE_FORMAT(evento.fecha_hora, '%d-%m-%Y') AS fecha,
        DATE_FORMAT(evento.fecha_hora, '%H:%i') AS hora,
        reporte.fotografia AS foto
    FROM participa_evento
    JOIN evento_limpieza AS evento
        ON participa_evento.evento_id = evento._id
    JOIN reporte_contaminacion AS reporte
        ON evento.reporte_id = reporte._id
    WHERE participa_evento.ambientalista_id IN ($ids)
        AND NOW() <= evento.fecha_hora
        AND evento._id
            NOT IN (SELECT evento_id FROM participa_evento WHERE ambientalista_id = {$id_ambientalista})
    LIMIT 20;
    ";

    /*
    $num_elementos = count($idsUsuarios);
    for($i = 0; $i < $num_elementos; $i++) {
        $select2 .= " ambientalista_id=".$idsUsuarios[$i];

        if($i + 1 < $num_elementos){
            $select2 .= " OR ";
        }
    }

    $select2 .= ") LIMIT 20";
    */

    try {
		$statement = $conexion->query($select2);
		return $statement->fetchAll(\PDO::FETCH_ASSOC);
	} catch (\PDOException $e) {
		//exit($e->getMessage());
		return array();
	}
}

?>