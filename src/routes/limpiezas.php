<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

require_once __DIR__ . "/../tablesDB/LimpiezaDB.php";
require_once __DIR__ . "/../tablesDB/EventoDB.php";
require_once __DIR__ . "/../utilidades.php";

return function(App $app) {

    $app->post('/limpiezas', function(Request $request, Response $response, array $args) {
    	$res = "resultado";
    	$msg = "mensaje";
    	$json = array();

    	// comprobar que esten los datos necesarios
    	if (!comprobarBodyParams($request, ["reporte_id", "descripcion"])) {
    		return $response->withJson(["estatus" => [$res => "0", $msg => "Datos incompletos"]]);
    	}

    	// comprobar la imagen
    	$uploadedFile = $request->getUploadedFiles()["file"] ?? null;
    	if ($uploadedFile === null || empty($uploadedFile) || $uploadedFile->getError() != UPLOAD_ERR_OK) {
    		return $response->withJson(["estatus" => [$res => "0", $msg => "Error en la imagen"]]);
    	}

    	$idUsuario = $request->getAttribute("token")["data"]->id;
    	$idReporte = $request->getParsedBodyParam("reporte_id");
        $descripcion = $request->getParsedBodyParam("descripcion");
    	
    	// comprobar que el reporte no tenga evento o limpieza asociado
    	$reporteDB = new ReporteDB($this->db);
    	$eventoDB = new EventoDB($this->db);
    	if ($reporteDB->reporteTieneLimpieza($idReporte) || $eventoDB->existeEventoConReporte($idReporte)) {
    		return $response->withJson(["estatus" => [$res => "0", $msg => "Error. Ya existe un evento o limpieza asociado con el reporte"]]);
    	}

    	// crear nombre para la imagen
    	$extension = strtolower(pathinfo($uploadedFile->getClientFileName(), PATHINFO_EXTENSION));
        $nombreBase = bin2hex(random_bytes(8));
        $directory = $this->get("images_directory") . "limpiezas";
        $fileName = $directory . DIRECTORY_SEPARATOR . $nombreBase . "." . $extension;

        // insertar limpieza en la DB
        $limpiezaDB = new LimpiezaDB($this->db);
        $idLimpieza = $limpiezaDB->insert($idReporte, $idUsuario, $descripcion, $fileName);
        if ($idLimpieza === -1) {
        	return $response->withJson(["estatus" => [$res => "0", $msg => "Error al insertar la limpieza."]]);
        }

        // mover la imagen
        try {
        	$uploadedFile->moveTo(__DIR__ . "/../../public/" . $fileName);
        } catch (Exception $e) {
        	//echo $e->getMessage();
        	$limpiezaDB->delete($idLimpieza);
        	return $response->withJson(["estatus" => [$res => "0", $msg => "Error al subir imagen"]]);
        }

        // retornar mensaje de éxito
        return $response->withJson([
            "estatus" => [
                $res => "1",
                $msg => "Limpieza insertada correctamente"
            ],
            "id_limpieza" => $idLimpieza
        ]);

    });
}


?>
