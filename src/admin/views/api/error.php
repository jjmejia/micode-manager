<?php
/**
 * Vista de error para salidas API
 *
 * @author John Mejia
 * @since Diciembre 2022
 */

// echo $this->view->showParams();

$detalles = strip_tags($this->view->param('footnote'));
if ($detalles != '' && strpos($detalles, "\n") !== false) {
	$detalles = explode("\n", $detalles);
}

echo json_encode([
	'error' => true,
	'title' => $this->view->param('title:text'),
	'message' => $this->view->param('message:text'),
	'details' => $detalles
	]);