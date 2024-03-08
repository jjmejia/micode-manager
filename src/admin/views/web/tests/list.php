<?php
/**
 * Script de soporte para presentación web (HTML).
 *
 * @author John Mejía
 * @since Mayo 2022
 */

// miframe_app()->params->set('page-buttons', [ 'tests/create' => miframe_text('Nuevo test') ]);

?>

<?php

if (miframe_app()->params->get('listado:count') <= 0) {
	miframe_app()->view->buffer("<p>No hay tests creados aun.</p>");
}
else {
	miframe_app()->view->buffer("<p class=\"info\">Hay <b>" . miframe_app()->params->get('listado:count') . "</b> tests encontrados.</p>");
	$tipo = urlencode(strtolower(miframe_app()->params->get('type')));
	// $enlace_url = miframe_app()->router->createURL('tests/index.php');
	foreach (miframe_app()->params->get('listado:e') as $name => $data) {
		// Busca información del proyecto listado
		// $uname = urlencode($name);
		$uname = htmlspecialchars($name);
		$enlace_url = miframe_app()->router->createURL('../tests/' . $name);
		$pos = strpos($name, ':');
		if ($pos !== false) {
			$type = trim(substr($name, 0, $pos));
			$search = substr($name, $pos + 1);
			$enlace_url = miframe_app()->router->createURL('tests/index.php') . '?type=' . urlencode($type) . '&test=' . urlencode($search);
		}
		$enlace = "<a href=\"{$enlace_url}\" target=\"micode-tests\">{$uname}</a>";
		if ($tipo == '?') {
			$enlace = "<span class='muted'>{$uname}</span>";
		}
		miframe_app()->view->buffer(
			"<div class=\"box\"><h3>" .
			$enlace .
			"</h3>" .
			"<div class=\"box-info\">{$data['description']}</div>" .
			"<div class=\"box-data\"><b>Creado en:</b> {$data['since']}</div>" .
			"</div>"
		);
	}
}
