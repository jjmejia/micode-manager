<?php
/**
 * Script de soporte para presentación web (HTML) a través de "interface/s".
 *
 * @author John Mejía
 * @since Abril 2022
 */

miframe_app()->params->set('page-buttons', [
	"repositories-create" => miframe_text('Adicionar repositorio'),
	// 'modules/newrepo' => miframe_text('Nuevo repositorio'),
	// 'repositories/import' => miframe_text('Importar repositorio')
]);

?>

<p class="info">
	<?= miframe_app()->params->iif('listado:empty', "<i>No hay repositorios creados aun.</i>", "<b>" . miframe_app()->params->get('listado:count') . "</b> repositorios encontrados") ?>
</p>

<?= miframe_app()->params->implode('mensajes', '<div class="info"><ul>{{ <li>$1</li> }}</ul></div>') ?>

<?php

if (miframe_app()->params->get('listado:count') > 0) {
	foreach (miframe_app()->params->get('listado:e') as $name => $data) {
		// Busca información del proyecto listado
		$uname = urlencode($name);
		$name = htmlspecialchars($name);
		$total = '';
		if (isset($data['modules-count']) && $data['modules-count'] > 0) {
			$total = '(' . $data['modules-count'] . ')';
		}
		$enlace = "<span class='muted'>{$name} {$total}</span>";
		$enlace_url = $data['url'];
		if ($enlace_url != '') {
			$enlace = "<a href=\"$enlace_url\">{$name} {$total}</a>";
		}
		$enlace_editar = miframe_app()->router->createRouteURL('repositories-edit', [ 'name' => $name ]);

		miframe_app()->view->buffer(
			"<div class=\"box\"><h3>" .
			$enlace .
			"<span class=\"label-tipo label-edit\"><a href=\"{$enlace_editar}\">Editar</a></span>" .
			"</h3>" .
			"<div class=\"box-info\">{$data['description']}</div>" .
			"<div class=\"box-data\" style=\"color:#333\"><b>Ubicación:</b> {$data['dirbase']}</div>" .
			// "<div class=\"box-data\"><b>Creado en:</b> {$data['since']}</div>" .
			"</div>"
		);
	}
}
