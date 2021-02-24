<?php

class LimpiezaDB {
	private $conexion = null;

	public function __construct($conexion) {
		$this->conexion = $conexion;
	}

	public function insert($idReporte, $idUsuario, $descripcion, $foto) {
		$insert = "
            INSERT INTO limpiezas(reporte_id, ambientalista_id, descripcion, foto)
            VALUES(:idReporte, :idUsuario, :descripcion, :foto)
            ";
        try {
            $statement = $this->conexion->prepare($insert);
            $valores = array(
                ":idReporte" => $idReporte,
                ":idUsuario" => $idUsuario,
                ":descripcion" => $descripcion,
                ":foto" => $foto
            );
            if ($statement->execute($valores)) {
                return $this->conexion->lastInsertId();
            }
            
            return -1;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return -1;
        }
	}

	public function delete($idLimpieza) {
		$deleteQuery = "DELETE FROM limpiezas WHERE _id = :idLimpieza";

		try {
			$statement = $this->conexion->prepare($deleteQuery);
            $statement->execute([":idLimpieza" => $idLimpieza]);
            return $statement->rowCount() > 0;
		} catch (\PDOException $e) {
			return false;
		}
	}
}

?>