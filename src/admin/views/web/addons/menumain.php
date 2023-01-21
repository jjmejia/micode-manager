<?php
/**
 * Menú principal.
 *
 * @author John Mejía
 * @since Diciembre 2022
 */

$arreglo = array(
	'' => miframe_text('Proyectos'),
	'repositories/list' => miframe_text('Repositorios'),
	'modules/list' => miframe_text('Módulos'),
	'localtests/list' => miframe_text('Tests'),
	'settings' => miframe_text('Configuración')
);

// Se asegura que haya configurado "sistema.ini"
if ($this->userEmail() == '') {
	$arreglo = array('settings' => 'Configuración');
}

$enlaces = array();
$cmd = cmdPost($this->post);
if ($cmd != '') {
	$p = explode('/', $cmd);
	// Reconstruye path eliminando el tercer elemento (si existe)
	if (isset($p[1])) {
		$cmd = $p[0] . '/' . $p[1];
	}
}
foreach ($arreglo as $llave => $titulo) {
	$enlace = $this->router->getFormAction($llave, true);
	$enlaces[] = array('url' => $enlace, 'titulo' => $titulo, 'selecto' => ($cmd == $llave));
}

menuApps($this->view, '', $enlaces);
