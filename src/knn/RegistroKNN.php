<?php

class RegistroKNN
{
    private $id_usuario;
    private $distancia;
    
    function __construct($id_usuario, $distancia)
    {
        $this->id_usuario = $id_usuario;
        $this->distancia = $distancia;
    }
    
    public function getIdUsuario()
    {
        return $this->id_usuario;
    }
    
    public function getDistancia()
    {
        return $this->distancia;
    }
}

?>