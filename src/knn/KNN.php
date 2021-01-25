<?php

//require_once("RegistroKNN.php");

class KNN
{
    private $datos = array();
    
    function __construct(&$datos)
    {
        $this->datos = $datos;

        /*
        DATOS
        [[ID_ITEM1] => [...], [ID_ITEM2] => [...], ...]
        */
    }
    
    private function determinarK()
    {
        $numElementos = (float)count($this->datos);
        $root = sqrt($numElementos);
        $rawK = $root / 2;
        
        $num = round($rawK);
        if($num % 2 == 0)
        {
            $num--;
        }
        
        return $num;
        
    }
    
    // Se le pasan los datos para los cuales
    // se desean las recomendaciones.
    // Devuelve un arreglo con las etiquetas predecidas
    public function getPredicciones(&$datosUsuario)
    {
        $distancias = array();

        //$numElementos = count($this->datos);
        foreach ($this->datos as $key => $value) {
            $distancia = $this->distanciaEuclidiana($datosUsuario, $value);
            $distancias[$key] = $distancia;
        }

        /*
        DISTANCIAS
        [[ID_ITEM] => distancia, ...]
        */

        // ordenar de menor a mayor manteniendo asociación de índices
        asort($distancias, SORT_NUMERIC);

        /*
        echo "<br><br>";
        print_r($distancias);
        echo "<br><br>";
        */

        $k = $this->determinarK();

        return array_keys(array_slice($distancias, 0, $k, true));
    }
    
    // devuelve la distancia entre dos usuarios
    private function distanciaEuclidiana(&$datos1, &$datos2)
    {
        $numElementos = count($datos1);
        $sumaCuadrados = 0.0;

        foreach(array_keys($datos1) as &$key)
        {
            $a = pow(abs($datos1[$key] - $datos2[$key]), 2);
            
            $sumaCuadrados += $a;
        }
        
        return sqrt($sumaCuadrados);
    }
    
}


/*
Datos knn eventos usuario participa
SELECT K.*
FROM KNN as K
JOIN participa_evento as P
    ON P.ambientalista_id = :idUsuario AND K.evento_id = P.evento_id

Ó

====
SELECT K.*
FROM PRUEBA AS K
JOIN (SELECT DISTINCT evento_id, created_at
      FROM participa_evento
      WHERE ambientalista_id = 1022) AS t2
    ON K.evento_id = t2.evento_id
ORDER BY t2.created_at DESC
====

------

Datos knn eventos usuario NO participa
SELECT DISTINCT K.*
FROM KNN as K
JOIN participa_evento as P
    ON P.ambientalista_id != :idUsuario AND K.evento_id = P.evento_id


SELECT K.*
FROM PRUEBA as K
WHERE K.evento_id NOT IN (SELECT DISTINCT evento_id FROM participa_evento WHERE ambientalista_id = 1022)

Ó

====
Eventos que NO participa el usuario y que aún están activos
SELECT K.*
FROM PRUEBA AS K
JOIN evento_limpieza AS E
    ON (K.evento_id = E._id AND NOW() <= E.fecha_hora)
LEFT JOIN participa_evento AS P
    ON (P.evento_id = K.evento_id AND P.ambientalista_id = 1022)
WHERE P.evento_id IS NULL
====

*/

?>