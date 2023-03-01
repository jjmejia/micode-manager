<?php
/**
 * Script de soporte para presentación web (HTML)
 *
 * @author John Mejía
 * @since Abril 2022
 */

$tipo = $this->view->param('type:url');
$modulo_padre = $this->view->param('module:url');
$enlace_ppal = '';

if ($this->view->param('url-back-file') != '') {
	$enlace_modulo = $this->view->param('url-back-file');
	$enlace_ppal = "<a href=\"{$enlace_modulo}\">Regresar a {$modulo_padre}</a> | ";
}

// Marca la página para no usar menu
// $this->view->setParam('page-nomenu', true);

$salida = '';

if ($this->view->param('documento:len') <= 0) {
	// Muestra información general del módulo
	// $salida .= '<div class="docinfo">';
	// $require .= '<h3>Información general</h3>';
	$salida .= $this->view->iif('info->description', '<p>{{ info->description:e }}</p><hr>');
	// $salida .= '<p><b>Directorio origen:</b> ' . $this->view->param('dirbase:e') . '</p>';
	$salida .= $this->view->enumParams('<ul>',
		array(
			'dirbase:e' => '<li><b>Directorio origen:</b> $1</li>',
			'title:e' => '<li><b>Tipo:</b> $1</li>',
			'info->author:e' => '<li><b>Autor:</b> $1</li>',
			'info->since:e' => '<li><b>Desde:</b> $1</li>',
			'info->datetime:date' => '<li><b>Última modificación:</b> $1</li>',
			'info->size:bytes' => '<li><b>Tamaño:</b> $1</li>'
			),
		'</ul>');

	// Otros módulos usados por esta librería
	$salida .= $this->view->param('info->uses:implode',
		'',
		'<h3>' . miframe_text('Módulos requeridos por esta librería') . '</h3><ol>{{ <li><a href="$1">$2</a></li> }}</ol>'
		);

	// PHP Namespaces encontrados
	$salida .= $this->view->param('info->php-namespaces:implode',
		'',
		'<h3>PHP Namespaces</h3><ol>{{ <li>$2 --> $1</li> }}</ol>'
		);

	// Muestra la información del módulo
	// $salida .= '</div>';
}

$total_requeridos = $this->view->param('require-files:count');
if ($total_requeridos > 0 && !isset($_REQUEST['docfunction'])) {
	// $salida .= '<div class="docinfo">';
	$salida .= '<h3>' . miframe_text('Archivos incluidos con este módulo') . ' (' . $total_requeridos . ')</h3>';
	$salida .= '<ol>';
	foreach ($this->view->param('require-files') as $modulo_req => $info_req) {
		if (isset($info_req['url']) && $info_req['url'] !== false) {
			$salida .= '<li><a href="' . $info_req['url'] . '">' . $modulo_req . '</a></li>';
		}
		else {
			// No tiene documentador asociado
			$salida .= '<li>' . $modulo_req . '</li>';
		}
	}
	$salida .= '</ol>';
	// $salida .= '</div>';
}

?>

<link rel="stylesheet" href="<?= $this->view->createURL('/admin/public/css/docblock.css') ?>">

<?= $this->view->param('mensajes:implode', '', '<div class="info"><ul>{{ <li>$1</li> }}</ul></div>') ?>

<?= $this->view->param('documento') ?>

<div class="docblock"><div class="docinfo"><?= $salida ?></div></div>
