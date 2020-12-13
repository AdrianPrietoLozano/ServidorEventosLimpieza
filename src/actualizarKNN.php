<?PHP
require_once __DIR__ . "/tablesDB/ReporteDB.php";

/*
$a = array("Escombros", "Varios", "Cartón");
$usuarioId = 30;
$conexion = "conexion";

actualizarKnnDesdeIdEvento($conexion, $usuarioId, 45);
echo "<br>";
actualizarKnn($conexion, $usuarioId, $a);
*/
//$a = array("Escombros", "Varios", "Cartón");

//echo formarQuery(1, $a);

//$conexion = mysqli_connect(SERVER, USER, PASSWORD, DB);

function actualizarKnnDesdeIdEvento($conexion, $usuarioId, $eventoId) {

    $sql = "
        SELECT reporte_id FROM evento_limpieza WHERE _id = :eventoId
    ";

    try {
        $statement = $conexion->prepare($sql);
        $statement->execute(["eventoId" => $eventoId]);
        $idReporte = $statement->fetch(\PDO::FETCH_ASSOC)["reporte_id"];

        $reporteDB = new ReporteDB($conexion);
        $datosReporte = $reporteDB->find($idReporte);
        $residuos = $datosReporte["residuos"];
        $volumen = $datosReporte["volumen"];
        $query = formarQuery($usuarioId, $residuos, $volumen);

        $conexion->exec($query);
    } catch (\PDOException $e) {

    }
}


function actualizarKnn($conexion, $usuarioId, $residuos, $volumen) {
    $query = formarQuery($usuarioId, $residuos, $volumen);

    try {
        $conexion->exec($query);
    } catch (\PDOException $e) {

    }
}



function formarQuery($usuarioId, $residuos, $volumen) {

    $nombresResiduos = array("Escombros" => "escombro",
        "Envases" => "envases",
        "Cartón" => "carton",
        "Bolsas" => "bolsas",
        "Eléctricos y electrónicos" => "electricos",
        "Pilas y baterías" => "pilas",
        "Neumáticos" => "neumaticos",
        "Medicamentos" => "medicamentos",
        "Varios" => "varios"
    );

    $volumenesResiduos = array("Cabe en una mano" => "volumen_chico",
    	"Cabe en una mochila" => "volumen_chico",
    	"Cabe en un automóvil" => "volumen_mediano",
    	"Cabe en un contenedor" => "volumen_mediano",
    	"Cabe en un camión" => "volumen_grande",
    	"Más grande" => "volumen_grande"
	);


    /*
    INSERT INTO KNN(usuario_id, carton, escombro) VALUES(34, 1, 1)
        ON DUPLICATE KEY UPDATE carton=carton+1, escombro=escombro+1;
    */

    $insert = "INSERT INTO KNN(usuario_id";
    $values = " VALUES({$usuarioId}";
    $duplicate = " ON DUPLICATE KEY UPDATE ";

    foreach ($residuos as &$residuo) {
        $nombreColumna = $nombresResiduos[$residuo];

        $insert .= ", " . $nombreColumna;
        $values .= ", 1";
        $duplicate .= " {$nombreColumna} = {$nombreColumna} + 1,";
    }

    // actualizar volumen
    $columnaVolumen = $volumenesResiduos[$volumen];
    $insert .= ", " . $columnaVolumen;
    $values .= ", 1";
    $duplicate .= " {$columnaVolumen} = {$columnaVolumen} + 1";

    $insert .= ") ";
    $values .= ") ";
    //$duplicate[strlen($duplicate) - 1] = ";"; // quita el coma sobrante

    $queryFinal = $insert . $values . $duplicate;

    return $queryFinal;
}

?>