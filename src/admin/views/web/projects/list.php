<?php
/**
 * Script de soporte para presentación web (HTML) a través de "interface/s".
 *
 * @author John Mejía
 * @since Abril 2022
 */

miframe_app()->params->set('page-buttons', [ "projects-create" => miframe_text('Nuevo proyecto') ]);

?>

<p class="info">
	<?= miframe_app()->params->iif('listado:empty', "<i>No hay proyectos creados aun.</i>", "<b>" . miframe_app()->params->get('listado:count') . "</b> proyectos encontrados") ?>
</p>

<?php

if (miframe_app()->params->get('listado:count') > 0) {
	// $salida .= '<ol>';
	foreach (miframe_app()->params->get('listado:e') as $name => $data) {
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
			$root = miframe_app()->router->documentRoot();
			$enlace_url = $data['url-detail'];
			miframe_app()->view->buffer(
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
			miframe_app()->view->buffer(
				"<div class=\"box\"><h3>" .
				$enlace .
				"<span class=\"label-tipo\">{$tipo}</span>" .
				"</h3>" .
				"</div>"
			);

		}
	}
}
