<?php
/**
 * Script de soporte para presentación web (HTML) a través de "interface/s".
 *
 * @author John Mejía
 * @since Abril 2022
 */

$this->view->setParam('page-buttons', [ "projects/create" => miframe_text('Nuevo proyecto') ]);

?>

<p class="info">
	<?= $this->view->iif('listado:empty', "<i>No hay proyectos creados aun</i>", "<b>" . $this->view->param('listado:count') . "</b> proyectos encontrados") ?>
</p>

<?php

if ($this->view->param('listado:count') > 0) {
	// $salida .= '<ol>';
	foreach ($this->view->param('listado:e') as $name => $data) {
		// Busca información del proyecto listado
		$uname = urlencode($name);
		$name = htmlspecialchars($name);
		$tipo = strtoupper($data['type']);
		$md5 = miframe_mask($name, 'w');
		$enlace = "<span class='muted'>{$name}</span>";
		$enlace_url = $data['url'];
		if ($enlace_url != '') {
			$enlace = "<a href=\"$enlace_url\" target=\"$md5\">{$name}</a>";
		}
		$root = $this->view->documentRoot();
		$enlace_url = $data['url-detail'];
		$this->view->buffer(
			"<div class=\"box\"><h3>" .
			$enlace .
			"<span class=\"label-tipo\">{$tipo}</span>" .
			"<span class=\"label-tipo label-edit\"><a href=\"{$enlace_url}\">Detalles</a></span>" .
			"</h3>" .
			"<div class=\"box-info\"><b>{$data['project-title']}</b></div>" .
			"<div class=\"box-info\">{$data['project-desc-info']}</div>" .
			"<div class=\"box-data\"><b>Creado en:</b> {$data['since']}</div>" .
			"</div>"
		);
	}
}
