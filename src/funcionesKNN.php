<?PHP
require __DIR__ . "/knn/KNN.php";

function &obtenerRecomendaciones($idUsuario, $conexion) {
	$datos = array();
	$datosUsuario = array();

	if (existeUsuarioEnTablaKNN($conexion, $idUsuario)) {
		getDatosBD($conexion, $datos, $datosUsuario, $idUsuario);

        //echo var_dump($datosUsuario);
		
		$knn = new KNN($datos);
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


function getDatosBD($conexion, &$datos, &$datosUsuario, $idUsuarioActual)
{
    $sql = "SELECT * FROM KNN";

    try {
        $statement = $conexion->query($sql);
        $datos = $statement->fetchAll(\PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        //exit($e->getMessage());
        $datos = array();
    }

    if (array_key_exists($idUsuarioActual, $datos)) {
        $datosUsuario = $datos[$idUsuarioActual];
        unset($datos[$idUsuarioActual]);
    } else {
        $datosUsuario = array_fill(0, 12, 0);
    }

    /*
    print_r($datos);
    echo "<br>-------------------------------<br>";
    print_r($datosUsuario);
    */
}

function generarRecomendacionesEventos($conexion, &$idsUsuarios, $id_ambientalista)
{

    $json = array();

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

    try {
		$statement = $conexion->query($select2);
		return $statement->fetchAll(\PDO::FETCH_ASSOC);
	} catch (\PDOException $e) {
		//exit($e->getMessage());
		return array();
	}
}

?>