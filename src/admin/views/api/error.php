<?php
/**
 * Vista de error para salidas API
 *
 * @author John Mejia
 * @since Diciembre 2022
 */

$detalles = strip_tags(miframe_app()->params->get('abort_data:footnote'));
if ($detalles != '' && strpos($detalles, "\n") !== false) {
	$detalles = explode("\n", $detalles);
}

echo json_encode([
	'error' => true,
	'title' => miframe_app()->params->get('abort_title:text'),
	'message' => miframe_app()->params->get('abort_message:text'),
	'details' => $detalles
	]);