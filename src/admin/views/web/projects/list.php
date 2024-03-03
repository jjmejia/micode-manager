<?php
/**
 * Script de soporte para presentación web (HTML) a través de "interface/s".
 *
 * @author John Mejía
 * @since Abril 2022
 */

$this->params->set('page-buttons', [ "projects-create" => miframe_text('Nuevo proyecto') ]);

?>

<p class="info">
	<?= $this->params->iif('listado:empty', "<i>No hay proyectos creados aun.</i>", "<b>" . $this->params->get('listado:count') . "</b> proyectos encontrados") ?>
</p>

<?php

if ($this->params->get('listado:count') > 0) {
	// $salida .= '<ol>';
	foreach ($this->params->get('listado:e') as $name => $data) {
		// Busca información del proyecto listado
		$uname = urlencode($name);
		$name = htmlspecialchars($name);
		$md5 = miframe_mask($name, 'w');
		$enlace = "<span class='muted'>{$name}</span>";
		if (is_array($data)) {
			$tipo = strtoupper($data['type']);
			$enlace_url = $data['url'];
			if ($enlace_url != '') {
				$enlace = "<a href=\"$enlace_url\" target=\"$md5\">{$name}</a>";
			}
			$root = $this->router->documentRoot();
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
		else {
			$tipo = '?';
			$this->view->buffer(
				"<div class=\"box\"><h3>" .
				$enlace .
				"<span class=\"label-tipo\">{$tipo}</span>" .
				"</h3>" .
				"</div>"
			);

		}
	}
}
