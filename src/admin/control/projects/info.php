<?php
/**
 * Administrador de miFrame - Listar modulos.
 *
 * @author John Mejia (C) Abril 2022.
 */

$app_name = strtolower(miframe_app()->router->param('app'));
if ($app_name == '') {
	miframe_app()->router->abort(
		miframe_text('Parámetros incompletos'),
		miframe_text('No se ha definido nombre del Proyecto a actualizar')
		);
}

$data_proyecto = micode_modules_project_data($app_name);

$type = $data_proyecto['mirepo']['type'];

// Valida si existe archivo README.md
$filename = $data_proyecto['readme-path'];
if (file_exists($filename)) {
	$data_proyecto['readme'] = file_get_contents($filename);
	// Función para realizar Parser
	$parser = miframe_class_load('\Parsedown');
	if ($parser !== false) {
		// Escape HTML even in trusted input
		$parser->setMarkupEscaped(true);
		$data_proyecto['readme'] = $parser->text($data_proyecto['readme']);
	}

}

$data = miframe_app()->router->getDataReloaded(true);
if (is_array($data) && isset($data['msg'])) {
	$data_proyecto['mensajes'] = $data['msg'];
}

miframe_app()->startView('projects/info.php', $data_proyecto);