<?php
/**
 * Salida a pantalla servicio modulos.
 *
 * @author John Mejia
 * @since Diciembre 2022
 */

$listado = $this->params->get('listado');
$nuevos = array('total' => count($listado), 'modules' => array());

foreach ($listado as $menu => $info) {
	foreach ($info as $k => $v) {
		if ($k == 'php-namespaces') {
			$info['phpNamespaces'] = array();
			foreach ($v as $class => $path) {
				$info['phpNamespaces'][] = array('class' => $class, 'path' => $path);
			}
			unset($info[$k]);
		}
		else {
			$k2 = str_replace(array('-', '#'), '_', $k);
			if ($k2 != $k) {
				$info[$k2] = $v;
				unset($info[$k]);
			}
		}
	}
	$nuevos['modules'][] = array('moduleName' => $menu) + $info;
}

echo json_encode($nuevos);