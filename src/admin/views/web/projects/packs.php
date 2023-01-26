<?php
/**
 * Script de soporte para presentación web (HTML).
 *
 * @author John Mejía
 * @since Abril 2022
 */

$this->view->setParam('page-buttons', [
	'?new' => miframe_text('Nuevo paquete'),
	// '?export' => miframe_text('Exportar proyecto')
]);

?>

<?php

if ($this->view->param('listado:count') <= 0) {
	// $this->view->buffer("<p>No hay módulos " . htmlspecialchars($tipo) . " creados aun</p>");
	$this->view->buffer("<p>No hay paquetes creados aun.</p>");
}
else {
	$this->view->buffer("<p class=\"info\">Hay <b>" . $this->view->param('listado:count') . "</b> paquetes encontrados.</p>");

	foreach ($this->view->param('listado:e') as $nombre => $data) {

		// Obtiene listado para cada uno
		$data['datetime'] = date('Y/m/d H:i:s', $data['datetime']);
		$data['size'] = miframe_bytes2text($data['size'], true);

		$this->view->buffer(
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
