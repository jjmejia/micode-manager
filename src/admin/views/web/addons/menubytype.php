<?php
/**
 * Menú principal.
 *
 * @author John Mejía
 * @since Diciembre 2022
 */

$title = '';
$pre = '';
if ($this->router->requestStartWith('modules')) {
	$title = miframe_text('Listado de Módulos');
	$pre = 'modules-list';
}
elseif ($this->router->requestStartWith('localtests')) {
	$title = miframe_text('Listado de Tests');
	$pre = 'localtests-list';
}

// Recupera tipos validos
$validos = micode_modules_types();
$enlaces = array();
$tiposvalidos = $this->params->get('tiposvalidos');

foreach ($validos as $tipo => $titulo) {
	// Valida si definió $tiposvalidos correctamente o si ignora
	if (is_array($tiposvalidos)) {
		if (!isset($tiposvalidos[$tipo])) { continue; }
		$titulo .= ' (' . count($tiposvalidos[$tipo]) . ')';
	}
	$enlace = $this->router->createRouteURL($pre, [ 'type' => $tipo ]);

	$enlaces[] = array('url' => $enlace, 'titulo' => $titulo, 'selecto' => ($this->params->get('type') == $tipo));
}

menuApps($this->router, $title, $enlaces);