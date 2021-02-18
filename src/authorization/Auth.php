<?php
use Firebase\JWT\JWT;

class Auth {
	private static $key = 'djl34hQaSd@';
    private static $algorithm = ['HS256'];

	public static function SignIn($data) {
		$time = time();

		// hace falta exp time
        $token = array(
        	'iat' => $time,
            'data' => $data
        );

        try {
        	return JWT::encode($token, self::$key);
    	} catch (Exception $e) {
    		return null;
    	}
	}

	public static function GetData($token) {
		try {
			$aux = JWT::decode($token, self::$key, self::$algorithm);
			return $aux->data;
		} catch (Exception $e) {
			return null;
		}
	}

}

?>