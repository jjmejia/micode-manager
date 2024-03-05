<?php
/**
 * Información del sistema.
 *
 * @author John Mejía
 * @since Marzo 2024.
 */

$data_proyecto = array('readme' => '');

// Valida si existe archivo README.md
$filename = MIFRAME_ROOT  . '/README.md';
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

miframe_app()->startView('about.php', $data_proyecto);