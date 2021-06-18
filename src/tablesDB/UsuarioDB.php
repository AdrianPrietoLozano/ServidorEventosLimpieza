<?php
    
require_once __DIR__ . "/../utilidades.php";

class UsuarioDB {    
    private $conexion = null;

    private $camposUsuario = "
                    _id AS id,
                    correo AS email,
                    nombre_usuario AS nombre,
                    puntos
                    ";
        
    public function __construct($conexion){
        $this->conexion = $conexion;
    }

    // busca usuario por id
    public function find($id) {
        $sql = "
            SELECT {$this->camposUsuario}
            FROM ambientalista
            WHERE _id = :idUsuario
        ";

        /*
        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["idUsuario" => $id]);
            return $statement->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return array();
        }*/

        return ejecutarFetchQuery($this->conexion, $sql, [":idUsuario" => $id], array());

    }

    public function findByEmail($email) {
        $sql = "
            SELECT {$this->camposUsuario}, contrasenia
            FROM ambientalista
            WHERE correo = :email
        ";

        /*
        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["email" => $email]);
            return $statement->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return array();
        }*/

        

        return ejecutarFetchQuery($this->conexion, $sql, [":email" => $email], array());
    }

    // busca usuario por email y contraseña
    public function findByCredenciales($email, $contrasenia) {
        $sql = "
            SELECT {$this->camposUsuario}
            FROM ambientalista
            WHERE correo = :email
                AND contrasenia = :contrasenia
        ";

        /*
        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["email" => $email, "contrasenia" => $contrasenia]);
            return $statement->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return array();
        }*/

        return ejecutarFetchQuery($this->conexion, $sql, [":email" => $email, ":contrasenia" => $contrasenia], array());
    }

    public function findByGoogleID($googleID) {
        $sql = "
            SELECT {$this->camposUsuario}
            FROM ambientalista
            WHERE google_id = :googleID
        ";

        /*
        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute(["googleID" => $googleID]);
            return $statement->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return array();
        }*/

        return ejecutarFetchQuery($this->conexion, $sql, [":googleID" => $googleID], array());
    }

    public function insert($email, $nombre, $contrasenia, $googleID, $fcmToken) {
        $sql = "
            INSERT INTO ambientalista(correo, nombre_usuario, foto, contrasenia, google_id, fcm_token)
            VALUES (:email, :nombre, 'no hay foto', :contrasenia, :googleID, :fcmToken)
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $params = [":email" => $email, ":nombre" => $nombre, ":contrasenia" => $contrasenia, ":googleID" => $googleID, ":fcmToken" => $fcmToken];
            if ($statement->execute($params)) {
                return $this->conexion->lastInsertId();
            }

            //echo $statement->errorInfo()[2];
            return -1;
        } catch (\PDOException $e) {
            echo $statement->errorInfo()[2];
            return -1;
        }
    }

    public function actualizarFCMToken($email, $token) {
        $sql = "
            UPDATE ambientalista SET fcm_token = :token WHERE correo = :correo
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute([":token" => $token, ":correo" => $email]);
            return $statement->rowCount() > 0;
        } catch (\PDOException $e) {
            //echo $statement->errorInfo()[2];
            return false;
        }


    }

    public function existeEmail($email) {            
        $sql = "
            SELECT COUNT(*)
            FROM ambientalista
            WHERE correo=:email
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute([":email" => $email]);
            return $statement->fetchColumn() > 0;
        } catch (\PDOException $e) {
            //echo $statement->errorInfo()[2];
            return false;
        }
    }

    public function existeUsuario($id) {            
        $sql = "
            SELECT COUNT(*)
            FROM ambientalista
            WHERE _id = :idUsuario
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute([":idUsuario" => $id]);
            return $statement->fetchColumn() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function existeUsuarioGoogle($googleID) {
        $sql = "
            SELECT COUNT(*)
            FROM ambientalista
                WHERE google_id = :googleID
        ";

        try {
            $statement = $this->conexion->prepare($sql);
            $statement->execute([":googleID" => $googleID]);
            return $statement->fetchColumn() > 0;
        } catch (\PDOException $e) {
            //echo $statement->errorInfo()[2];
            return false;
        }
    }

    public function getRanking($numResultados) {
        $sql = "
            SELECT _id AS id, nombre_usuario AS nombre, puntos, FIND_IN_SET( puntos, (
                SELECT GROUP_CONCAT( puntos
                ORDER BY puntos DESC ) 
                FROM ambientalista )
                ) AS rank
            FROM ambientalista
            ORDER BY rank
            LIMIT 15
        ";

        try {
            $statement = $this->conexion->query($sql);
            $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return $results;
        } catch (\PDOException $e) {
            //exit($e->getMessage());
            return array();
        }
    }

    public function getRankingUsuario($usuarioId) {
        $sql = "
            SELECT _id AS id, nombre_usuario AS nombre, puntos, FIND_IN_SET( puntos, (
                SELECT GROUP_CONCAT( puntos
                ORDER BY puntos DESC ) 
                FROM ambientalista )
                ) AS rank
            FROM ambientalista
            WHERE _id = :usuarioId
        ";

        return ejecutarFetchQuery($this->conexion, $sql, [":usuarioId" => $usuarioId], array());
    }
}

?>