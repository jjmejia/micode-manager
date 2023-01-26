<?php
/**
 * Script de soporte para presentación web (HTML)
 *
 * @author John Mejía
 * @since Abril 2022
 */

// Lista modulos encontrados

$tipo = $this->view->param('type');
$reponame = $this->view->param('reponame');
$show_infotipo = ($tipo == '');

if ($reponame != '') {
	$this->view->setParam('page-buttons', array(
		'modules/create/' . urlencode($reponame) => miframe_text('Nuevo módulo'),
		));
}

if ($this->view->param('listado:count') <= 0) {
	// $this->view->buffer("<p>No hay módulos " . htmlspecialchars($tipo) . " creados aun</p>");
	$this->view->buffer("<p>No hay módulos creados aun.</p>");
}
// else {
// 	$this->view->buffer("<p class=\"info\">Hay <b>" . $this->view->param('listado:count') . "</b> módulos/archivos encontrados.</p>");
// }

$this->view->buffer($this->view->param('mensajes:implode', '', '<div class="info"><ul>{{ <li>$1</li> }}</ul></div>'));

// $this->view->buffer($this->view->iif('repodata->path', '<p><b>Ubicación:</b> /{{ repodata->path:e }}</p>'));

if ($this->view->param('listado:count') > 0) {

	foreach ($this->view->param('listado:e') as $modulo => $data) {

		// Obtiene listado para cada uno

		$data['datetime'] = date('Y/m/d H:i:s', $data['datetime']);
		$data['size'] = miframe_bytes2text($data['size'], true);

		if (!isset($data['type'])) {
			// Archivo sin documentación asociada
			$this->view->buffer(
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
		$utype = $this->view->param('type:url');
		$basename = $modulo;
		// if (isset($data['islocal']) && $data['islocal'] != '') { $basename = $data['islocal']; }
		$enlace = $data['url'];
		$this->view->buffer(
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
