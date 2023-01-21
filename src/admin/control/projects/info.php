<?php
/**
 * Administrador de miFrame - Listar modulos.
 *
 * @author John Mejia (C) Abril 2022.
 */

$app_name = strtolower($this->router->param('app'));
if ($app_name == '') {
	$this->router->abort('Parámetros incompletos', 'No se ha definido nombre del Proyecto a actualizar');
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
		// $parser = new \Parsedown();
		// Escape HTML even in trusted input
		$parser->setMarkupEscaped(true);
		$data_proyecto['readme'] = $parser->text($data_proyecto['readme']);
	}

}

// Valida valores fijados por ej. al crear proyecto
// if (isset($mensajes)) { $data_proyecto['mensajes'] = $mensajes; }
$data = $this->router->getDataReloaded(true);
if ($data !== false && is_array($data) && isset($data['msg'])) {
	$data_proyecto['mensajes'] = $data['msg'];
}

$this->startView('projects/info.php', $data_proyecto);