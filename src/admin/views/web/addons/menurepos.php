<?php
/**
 * Menú principal.
 *
 * @author John Mejía
 * @since Diciembre 2022
 */

$cfg_module = miframe_app()->params->get('reponame');
$tituloppal = '';
if (miframe_app()->params->get('nuevo', false)) {
	$tituloppal = miframe_text('Nuevo Repositorio');
}
else {
	$tituloppal = miframe_text('Repositorio "$1"', htmlspecialchars($cfg_module));
}

// Enlaces
$enlaces = array();

// Recupera tipos validos
$validos = micode_modules_types();
$tiposvalidos = miframe_app()->params->get('tiposvalidos');
foreach ($validos as $tipo => $titulo) {
	if (is_array($tiposvalidos) && isset($tiposvalidos[$tipo])) {
		$titulo .= ' (' . count($tiposvalidos[$tipo]) . ')';
		$enlace = miframe_app()->router->createRouteURL('repositories-detail', [ 'name' => $cfg_module, 'type' => $tipo ]);
		$enlaces[] = array('url' => $enlace, 'titulo' => $titulo, 'selecto' => (miframe_app()->params->get('type') == $tipo));
	}
}


$enlace = miframe_app()->router->createRouteURL('repositories-list');

menuApps($tituloppal, $enlaces, "<a href=\"{$enlace}\">" . miframe_text('Listado de Repositorios') . "</a>");
