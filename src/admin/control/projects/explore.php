<?php
/**
 * Administrador de miFrame - Explorar proyectos.
 *
 * @author John Mejia (C) Diciembre 2022.
 */

$app_name = strtolower($this->router->param('app'));
if ($app_name == '') {
	$this->router->abort(
		miframe_text('Parámetros incompletos'),
		miframe_text('No se ha definido nombre del Proyecto a visualizar')
		);
}

$data_proyecto = micode_modules_project_data($app_name);

$path = micode_modules_path($app_name, false, $data_proyecto['mirepo']);
$enlace = $this->router->getFormAction('projects/explore/' . $app_name, true);
$data_proyecto['path-base'] = miframe_path($path, '..');

$data_proyecto['html'] = micode_modules_explore($enlace, $data_proyecto['path-base']);

$this->startView('projects\explore.php', $data_proyecto);