<?php
/**
 * Menú principal.
 *
 * @author John Mejía
 * @since Diciembre 2022
 */

$cfg_type = $this->params->get('type');
$cfg_module = $this->params->get('module');
$paths = explode('/', $cfg_module);

$arreglo = array(
	'modules-detail' => miframe_text('Información'),
	'modules-edit' => miframe_text('Editar'),
	'modules-explore' => miframe_text('Explorar directorio')
	);

$tituloppal = '';
$enlaces = array();
$enlace_modulos = '';

if (!$this->params->get('nuevo:bool')) {
	$tituloppal = miframe_text('Módulo $1', htmlspecialchars($cfg_module));
	// Enlaces
	$enlaces = array();

	foreach ($arreglo as $alias => $titulo) {
		$enlace = $this->router->createRouteURL($alias, [ 'module' => $cfg_module ]);
		// $comparar = explode('?', $llave);
		$enlaces[] = array('url' => $enlace, 'titulo' => $titulo, 'selecto' => ($this->router->selectedRoute() == $alias));
		// echo "$cmd -- {$comparar[0]}<hr>";
	}

	// Enlace a página de módulos
	$enlace = $this->router->createRouteURL('modules-list', [ 'type' => $cfg_type ]);
	$enlace_modulos = "<a href=\"{$enlace}\">" . miframe_text('Listado de Módulos') . "</a> | ";
}
else {
	$repo_name = $this->router->param('name');
	// $paths[0] contiene el nombre del repositorio, lo toma del "Router"
	// (en este caso se invoca desde "modules/create/[repositorio]")
	$paths = array($repo_name);
	$tituloppal = miframe_text('Nuevo Módulo para repositorio $1', $repo_name);
}

$enlace_repo = $this->router->createRouteURL('repositories-detail', [ 'name' => urlencode($paths[0]), 'type' => $cfg_type]);

menuApps($this->router, $tituloppal, $enlaces,
	$enlace_modulos .
	"<a href=\"{$enlace_repo}\">" . miframe_text('Repositorio $1', $paths[0]) . "</a>"
);
