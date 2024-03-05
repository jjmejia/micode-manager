<?php
/**
 * Administrador de miFrame - Explorar proyectos.
 *
 * @author John Mejia (C) Diciembre 2022.
 */

// Modulo selecto
$modulo = miframe_app()->post->getString('module');

// Tipo selecto
$type = miframe_app()->router->param('type', 'php');
if (!micode_modules_eval_type($type)) {
	miframe_app()->router->abort(
			miframe_text('Parámetros incorrectos'),
			miframe_text('Tipo de módulos a recuperar no es valido ($1).', $type)
			);
}

$data_proyecto = array('module' => $modulo);

if ($modulo != '') {
	$clase_manejador = micode_modules_class($type);

	$m = new \miFrame\Local\AdminModules(true);
	$listado = $m->getAllModules('', $modulo);
	$data_proyecto['info'] = $listado[$modulo];
	$data_proyecto['dirbase'] = $m->getDirBase($modulo);
}

if ($modulo == '' || !isset($listado[$modulo])) {
	miframe_app()->router->abort(
		miframe_text('Parámetros incompletos'),
		miframe_text('No se ha definido nombre del módulo a visualizar')
		);
}

$enlace = miframe_app()->router->createRouteURL('modules-explore', [ 'module' => $modulo ]);
$data_proyecto['html'] = micode_modules_explore($enlace, $data_proyecto['dirbase']);

miframe_app()->startView('projects\explore.php', $data_proyecto);