<?php
/**
 * Administrador de miFrame - Explorar proyectos.
 *
 * @author John Mejia (C) Diciembre 2022.
 */

// Modulo selecto
$modulo = $this->post->getString('module');

// Tipo selecto
$type = $this->router->param('type', 'php');
if (!micode_modules_eval_type($type)) {
	$this->router->abort(
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
	$this->router->abort(
		miframe_text('Parámetros incompletos'),
		miframe_text('No se ha definido nombre del módulo a visualizar')
		);
}

$enlace = $this->router->getFormAction('modules/explore/' . $type . '?module=' . $modulo, true);
$data_proyecto['html'] = micode_modules_explore($enlace, $data_proyecto['dirbase']);

// miframe_debug_box($data_proyecto);

$this->startView('projects\explore.php', $data_proyecto);