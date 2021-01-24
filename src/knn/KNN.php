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

?>
