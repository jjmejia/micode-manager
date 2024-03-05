<?php
/**
 * Script de soporte para presentación web (HTML)
 *
 * @author John Mejía
 * @since Abril 2022
 */

// Lista modulos encontrados

$tipo = miframe_app()->params->get('type');
$reponame = miframe_app()->params->get('reponame');
$show_infotipo = ($tipo == '');

if ($reponame != '') {
	miframe_app()->params->set('page-buttons', array(
		'modules-create' => [ '_title' => miframe_text('Nuevo módulo'), 'name' => $reponame ],
		));
}

if (miframe_app()->params->get('listado:count') <= 0) {
	miframe_app()->view->buffer("<p>No hay módulos creados aun.</p>");
}


miframe_app()->view->buffer(miframe_app()->params->implode('mensajes', '<div class="info"><ul>{{ <li>$1</li> }}</ul></div>'));

if (miframe_app()->params->get('listado:count') > 0) {

	foreach (miframe_app()->params->get('listado:e') as $modulo => $data) {

		// Obtiene listado para cada uno

		$data['datetime'] = date('Y/m/d H:i:s', $data['datetime']);
		$data['size'] = miframe_bytes2text($data['size'], true);

		if (!isset($data['type'])) {
			// Archivo sin documentación asociada
			miframe_app()->view->buffer(
				"<div class=\"box\"><h3>" .
				$modulo .
				$tipo_encode .
				"</h3>" .
				"<div class=\"box-data\"><b>Última modificación:</b> " .
				$data['datetime'] .
				" <b>Tamaño:</b> " .
				$data['size'] .
				"</div></div>"
			);
			continue;
		}

		if ($data['author'] == '') { $data['author'] = 'N/A'; }

		$descripcion = $data['description'];

		if (isset($data['require-total']) && $data['require-total'] > 1) {
			$descripcion .= '<div class="box-data" style="color:#333"><b>Este módulo agrupa ' . ($data['require-total']) . ' archivos.</b></div>';
		}
		$descripcion .= '<div class="box-data" style="color:#333"><b>Directorio origen:</b> ' . $data['dirbase']. '</div>';
		// $descripcion .= '<div class="box-data" style="color:#333"><b>Directorio destino:</b> ' . $data['dirdest']. '</div>';
		if (isset($data['uses']) && count($data['uses']) > 0) {
			$descripcion .= '<div class="box-data"><b>Requiere:</b> ' . implode(', ', $data['uses']) . '</div>';
		}
		if (isset($data['repo']) && $data['repo'] != '') {
			$descripcion .= '<div class="box-data"><b>Repositorio externo:</b> <a href="' . $data['repo'] . '" target="_blank">' . $data['repo'] . '</a></div>';
		}

		$infotipo = '';
		if ($show_infotipo) {
			$data['type'] = strtoupper($data['type']);
			$infotipo = "<span class=\"label-tipo\">{$data['type']}</span>";
		}
		// $umodulo = urlencode($modulo);
		$utype = miframe_app()->params->get('type:e');
		$basename = $modulo;
		$enlace = $data['url'];
		miframe_app()->view->buffer(
			"<div class=\"box\"><h3>" .
			"<a href=\"{$enlace}\">{$modulo}</a>" .
			$infotipo .
			// "<span class=\"label-tipo\">{$data['type']}</span></h3>" .
			"</h3><div class=\"box-info\">" .
			$descripcion .
			"</div>" .
			"<div class=\"box-data\">" .
			// "<b>Autor:</b> " .
			// $data['author'] .
			// " <b>Creado en:</b> " .
			// $data['since'] .
			" <b>Última modificación:</b> " .
			$data['datetime'] .
			" <b>Tamaño:</b> " .
			$data['size'] .
			"</div></div>"
		);
	}
}
