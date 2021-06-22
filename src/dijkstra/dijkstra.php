<?php

/* ¡IMPORTANTE¡ ES NECESARIO EJECURTAR EL SIGUIENTE COMANDO PARA OBTENER LA COLA DE PRIORIDAD: 
	composer require php-ds/php-ds
*/

function dijkstra($grafo, $inicio) {
	$distancias = array();
	$padre = array();
	foreach (array_keys($grafo) as $key) {
  		$distancias[$key] = [INF, 0];
  		$padre[$key] = null;
	}

	$distancias[$inicio] = [0, 0];
	//print_r($distancias);

	$pq = new \Ds\PriorityQueue();

	$pq->push([$inicio, 0], 0);

	while (!$pq->isEmpty()) {
		$aux = $pq->pop();
		$u = $aux[0];

		foreach ($grafo[$u] as $pair) {
			$v = $pair[0];
			$peso = $pair[1];
			$puntos = $pair[2];
			$alt = $distancias[$u][0] + $peso;

			if ($alt < $distancias[$v][0]) {
				$distancias[$v][0] = $alt;
				$distancias[$v][1] = $distancias[$u][1] + $puntos;
				$padre[$v] = $u;
				$pq->push([$v, $alt], $alt);
			}
		}
	}

	return array($distancias, $padre);
}

?>