<?php
/**
 * Script de soporte para presentación web (HTML).
 *
 * @author John Mejía
 * @since Mayo 2022
 */

$this->view->setParam('page-buttons', [ 'tests/create' => miframe_text('Nuevo test') ]);

?>

<?php

if ($this->view->param('listado:count') <= 0) {
	$this->view->buffer("<p>No hay tests creados aun.</p>");
}
else {
	$this->view->buffer("<p class=\"info\">Hay <b>" . $this->view->param('listado:count') . "</b> tests encontrados.</p>");
	$tipo = urlencode(strtolower($this->view->param('type')));
	// $enlace_url = $this->view->createURL('tests/index.php');
	foreach ($this->view->param('listado:e') as $name => $data) {
		// Busca información del proyecto listado
		// $uname = urlencode($name);
		$uname = htmlspecialchars($name);
		$enlace_url = $this->view->createURL('tests/' . $name);
		$pos = strpos($name, ':');
		if ($pos !== false) {
			$type = trim(substr($name, 0, $pos));
			$search = substr($name, $pos + 1);
			$enlace_url = $this->view->createURL('tests/index.php') . '?type=' . urlencode($type) . '&test=' . urlencode($search);
		}
		$enlace = "<a href=\"{$enlace_url}\" target=\"micode-tests\">{$uname}</a>";
		if ($tipo == '?') {
			$enlace = "<span class='muted'>{$uname}</span>";
		}
		$this->view->buffer(
			"<div class=\"box\"><h3>" .
			$enlace .
			"</h3>" .
			"<div class=\"box-info\">{$data['description']}</div>" .
			"<div class=\"box-data\"><b>Creado en:</b> {$data['since']}</div>" .
			"</div>"
		);
	}
}
