<?php
/**
 * Script de soporte para presentación web (HTML).
 *
 * @author John Mejía
 * @since Abril 2022
 */

miframe_app()->params->set('page-buttons', [
	'' => [ '_title' => miframe_text('Nuevo paquete'), 'new' => true ],
	// '?export' => miframe_text('Exportar proyecto')
]);

?>

<?php

if (miframe_app()->params->get('listado:count') <= 0) {
	miframe_app()->view->buffer("<p>No hay paquetes creados aun.</p>");
}
else {
	miframe_app()->view->buffer("<p class=\"info\">Hay <b>" . miframe_app()->params->get('listado:count') . "</b> paquetes encontrados.</p>");

	foreach (miframe_app()->params->get('listado:e') as $nombre => $data) {

		// Obtiene listado para cada uno
		$data['datetime'] = date('Y/m/d H:i:s', $data['datetime']);
		$data['size'] = miframe_bytes2text($data['size'], true);

		miframe_app()->view->buffer(
			"<div class=\"box\"><h3>" .
			"<a href=\"{$data['url']}\" target=\"_blank\">{$nombre}</a>" .
			"</h3>" .
			"<div class=\"box-data\"><b>Última modificación:</b> " .
			$data['datetime'] .
			" <b>Tamaño:</b> " .
			$data['size'] .
			"</div></div>"
		);
	}
}
