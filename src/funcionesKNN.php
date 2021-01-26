<?PHP
/*
require __DIR__ . "/knn/KNN.php";


function obtenerRecomendacionesUsuario($conexion, $idUsuario) {
    $NUM_PREDICCIONES = 25;
    $datos = array();
    $participacionesUsuario = array();

    $participacionesUsuario = obtenerParticipacionesUsuario($conexion, $idUsuario);
    $datos = obtenerDatosEventos($conexion, $idUsuario);
    if (empty($participacionesUsuario) || empty($datos)) {
        return obtenerEventosMasPopulares($conexion, $idUsuario);
    }
    
    $knn = new KNN($datos);
    $predicciones = array();
    foreach ($participacionesUsuario as $key => $value) {
        if (count($predicciones) > $NUM_PREDICCIONES)
            break;

        $newPredicciones = $knn->getPredicciones($value);
        $predicciones = array_merge($predicciones, $newPredicciones);
    }

    return generarRecomendacionesEventos($conexion, $predicciones);
}

function obtenerRecomendacionesEvento($conexion, $idEvento, $idUsuario) {
    $datos = obtenerDatosEventos($conexion, $idUsuario); // eventos donde el usuario no participa
    $datosEvento = obtenerEvento($conexion, $idEvento);

    if (empty($datos) || empty($datosEvento)) {
        return obtenerEventosMasPopulares($conexion, $idUsuario);
    }

    $knn = new KNN($datos);
    $predicciones = $knn->getPredicciones($datosEvento);
    $recomendaciones = generarRecomendacionesEventos($conexion, $predicciones);
    if (!empty($recomendaciones) && reset($recomendaciones)["id_evento"] == $idEvento)
        array_shift($recomendaciones); // eliminar el primer elemento porque es el mismo que idEvento
    return $recomendaciones;
}


function obtenerParticipacionesUsuario($conexion, $idUsuario) {
    $query = "
        SELECT K.*
        FROM KNN AS K
        JOIN (SELECT DISTINCT evento_id, created_at
                FROM participa_evento
                WHERE ambientalista_id = :idUsuario) AS t2
            ON K.evento_id = t2.evento_id
        ORDER BY t2.created_at DESC
    ";

    try {
        $statement = $conexion->prepare($query);
        $statement->execute(["idUsuario" => $idUsuario]);
        return $statement->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        //exit($e->getMessage());
        return array();
    }
}

// obtiene los datos de los eventos que aún estan activos y en los
// que no participa el usuario
function obtenerDatosEventos($conexion, $idUsuario) {
    $query = "
        SELECT K.*
        FROM KNN AS K
        JOIN evento_limpieza AS E
            ON (K.evento_id = E._id AND NOW() <= E.fecha_hora)
        LEFT JOIN participa_evento AS P
            ON (P.evento_id = K.evento_id AND P.ambientalista_id = :idUsuario)
        WHERE P.evento_id IS NULL
    ";

    try {
        $statement = $conexion->prepare($query);
        $statement->execute(["idUsuario" => $idUsuario]);
        return $statement->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        //exit($e->getMessage());
        return array();
    }
}

// obtiene los datos del evento de la tabla KNN
function obtenerEvento($conexion, $idEvento) {
    $query = "SELECT * FROM KNN WHERE evento_id = :idEvento";

    try {
        $statement = $conexion->prepare($query);
        $statement->execute(["idEvento" => $idEvento]);
        $datos = $statement->fetch(\PDO::FETCH_ASSOC);
        unset($datos["evento_id"]);
        return $datos;
    } catch (\PDOException $e) {
        //exit($e->getMessage());
        return array();
    }
}

function generarRecomendacionesEventos($conexion, &$idsEventos)
{

    $json = array();
    $ids = join(', ', $idsEventos);
    
    $select = "
        SELECT
            evento._id AS id_evento,
            evento.titulo AS titulo,
            DATE_FORMAT(evento.fecha_hora, '%d-%m-%Y') AS fecha,
            DATE_FORMAT(evento.fecha_hora, '%H:%i') AS hora,
            reporte.fotografia AS foto
        FROM evento_limpieza AS evento
        JOIN reporte_contaminacion AS reporte
            ON evento.reporte_id = reporte._id AND evento._id IN ($ids)
    ";

    try {
        $statement = $conexion->query($select);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        //exit($e->getMessage());
        return array();
    }
}


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
*/

?>