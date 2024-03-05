<?php
/**
 * Detalle de repositorio.
 *
 * @author John Mejia
 * @since Enero 2023
 */

// Tipo selecto
$clase = miframe_app()->router->param('name');
$type = miframe_app()->router->param('type');

$m = new \miFrame\Local\AdminModules(true);

$listado = $m->getAllRepos($clase);

if ($clase == '' || !isset($listado[$clase])) {
	miframe_app()->router->abort(
		miframe_text('Parámetros incompletos'),
		miframe_text('No se ha definido un nombre de repositorio válido ($1)', $clase)
		);
}

// Captura todos los modulos asociados a $clase
$modulos = $m->getAllModules();

// Filtra modulos y lista por tipo de proyecto
$tiposvalidos = array();

foreach ($modulos as $modulo => $data) {
	$tiposvalidos[$data['type']][] = $modulo;
	$modulos[$modulo]['url'] = miframe_app()->router->createRouteURL('modules-detail', [ 'module' => $modulo ]);
	$modulos[$modulo]['dirbase'] = $m->getDirBase($modulo);
}

// Recupera tipos validos
$validos = micode_modules_types();

if ($type == '') {
	// Detecta el primero de los tipos validos
	foreach ($validos as $tipo => $titulo) {
		if (isset($tiposvalidos[$tipo])) {
			$type = $tipo;
			break;
		}
	}
}

if ($type != '' && !isset($validos[$type])) {
	// No hay  módulos validos?
	// Si $type != '', es un tipo erradp. Si está en blanco, puede deberse a que el repositorio está en blanco
	miframe_app()->router->abort(
		miframe_text('Parámetros incorrectos'),
		miframe_text('El tipo indicado ($2) no es uno de los tipos validos: *$1*', implode(', ', array_keys($validos)), $type)
		);
}

// Remueve modulos de tipo diferente al actual
if (count($tiposvalidos) > 1) {
	foreach ($modulos as $modulo => $data) {
		if ($data['type'] != $type) { unset($modulos[$modulo]); }
	}
}

$data_proyecto = array(
	'listado' => $modulos,
	'reponame' => $clase,
	'repodata' => $listado[$clase],
	'tiposvalidos' => $tiposvalidos,
	'type' => $type
	);

miframe_app()->startView('modules/list.php', $data_proyecto);
