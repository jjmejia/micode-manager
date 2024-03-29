<?php
/**
 * Administrador de miFrame - Explorar proyectos.
 *
 * @author John Mejia (C) Diciembre 2022.
 */

$app_name = strtolower(miframe_app()->router->param('app'));
if ($app_name == '') {
	miframe_app()->router->abort(
		miframe_text('Parámetros incompletos'),
		miframe_text('No se ha definido nombre del Proyecto a visualizar')
		);
}

$data_proyecto = micode_modules_project_data($app_name);

$path = micode_modules_path($app_name, false, $data_proyecto['mirepo']);
$enlace = miframe_app()->router->createRouteURL('projects-explore', [ 'app' => $app_name ]);
$data_proyecto['path-base'] = miframe_path($path, '..');

$data_proyecto['html'] = micode_modules_explore($enlace, $data_proyecto['path-base']);

miframe_app()->startView('projects\explore.php', $data_proyecto);