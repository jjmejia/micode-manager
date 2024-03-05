<?php
/**
 * Menú principal.
 *
 * @author John Mejía
 * @since Diciembre 2022
 */

$cfg_type = miframe_app()->params->get('type');
$cfg_module = miframe_app()->params->get('module');

$repo_name = '';

$arreglo = array(
	'modules-detail' => miframe_text('Información'),
	'modules-edit' => miframe_text('Editar'),
	'modules-explore' => miframe_text('Explorar directorio')
	);

$tituloppal = '';
$enlaces = array();
$enlace_modulos = '';

if (!miframe_app()->params->get('nuevo:bool')) {
	$tituloppal = miframe_text('Módulo $1', htmlspecialchars($cfg_module));
	// Enlaces
	$enlaces = array();

	foreach ($arreglo as $alias => $titulo) {
		$enlace = miframe_app()->router->createRouteURL($alias, [ 'module' => $cfg_module ]);
		$enlaces[] = array('url' => $enlace, 'titulo' => $titulo, 'selecto' => (miframe_app()->router->selectedRoute() == $alias));
	}

	// Enlace a página de módulos
	$enlace = miframe_app()->router->createRouteURL('modules-list', [ 'type' => $cfg_type ]);
	$enlace_modulos = "<a href=\"{$enlace}\">" . miframe_text('Listado de Módulos') . "</a>";
}
else {
	$repo_name = miframe_app()->router->param('name');
	// $repo_name contiene el nombre del repositorio, lo toma del "Router"
	// (en este caso se invoca desde "modules/create/[repositorio]")
	$tituloppal = miframe_text('Nuevo Módulo para repositorio "$1"', $repo_name);

	$enlace_repo = miframe_app()->router->createRouteURL('repositories-detail', [ 'name' => $repo_name, 'type' => $cfg_type]);
	$enlace_modulos = "<a href=\"{$enlace_repo}\">" . miframe_text('Repositorio $1', $repo_name) . "</a>";
}

menuApps($tituloppal, $enlaces,	$enlace_modulos);
