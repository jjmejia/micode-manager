<?php
/**
 * Script de soporte para presentación web (HTML)
 *
 * @author John Mejía
 * @since Abril 2022
 */

$tipo = miframe_app()->params->get('type:e');
$modulo_padre = miframe_app()->params->get('module:e');
$enlace_ppal = '';

if (miframe_app()->params->get('url-back-file') != '') {
	$enlace_modulo = miframe_app()->params->get('url-back-file');
	$enlace_ppal = "<a href=\"{$enlace_modulo}\">Regresar a {$modulo_padre}</a> | ";
}

$salida = '';

if (miframe_app()->params->get('documento:len') <= 0) {
	// Muestra información general del módulo
	// $salida .= '<div class="docinfo">';
	// $require .= '<h3>Información general</h3>';
	$salida .= miframe_app()->params->iif('info->description', '<p>{{ info->description:e }}</p><hr>');
	// $salida .= '<p><b>Directorio origen:</b> ' . miframe_app()->params->get('dirbase:e') . '</p>';
	$salida .= miframe_app()->params->extract(
		array(
			'dirbase:e' => '<li><b>Directorio origen:</b> $1</li>',
			'title:e' => '<li><b>Tipo:</b> $1</li>',
			'info->author:e' => '<li><b>Autor:</b> $1</li>',
			'info->since:e' => '<li><b>Desde:</b> $1</li>',
			'info->datetime:date' => '<li><b>Última modificación:</b> $1</li>',
			'info->size:bytes' => '<li><b>Tamaño:</b> $1</li>'
			),
		'<ul>$1</ul>'
		);

	// Otros módulos usados por esta librería
	$salida .= miframe_app()->params->implode('info->uses',
		'<h3>' . miframe_text('Módulos requeridos por esta librería') . '</h3><ol>{{ <li><a href="$1">$2</a></li> }}</ol>'
		);

	// PHP Namespaces encontrados
	$salida .= miframe_app()->params->implode('info->php-namespaces',
		'<h3>PHP Namespaces</h3><ol>{{ <li>$2 --> $1</li> }}</ol>'
		);

	// Muestra la información del módulo
	// $salida .= '</div>';
}

$total_requeridos = miframe_app()->params->get('require-files:count');
if ($total_requeridos > 0 && !isset($_REQUEST['docfunction'])) {
	$salida .= '<h3>' . miframe_text('Archivos incluidos con este módulo') . ' (' . $total_requeridos . ')</h3>';
	$salida .= '<ol>';
	foreach (miframe_app()->params->get('require-files') as $modulo_req => $info_req) {
		if (isset($info_req['url']) && $info_req['url'] !== false) {
			$salida .= '<li><a href="' . $info_req['url'] . '">' . $modulo_req . '</a></li>';
		}
		else {
			// No tiene documentador asociado
			$salida .= '<li>' . $modulo_req . '</li>';
		}
	}
	$salida .= '</ol>';
}

?>

<link rel="stylesheet" href="<?= miframe_app()->router->createURL('resources/css/docblock.css') ?>">

<?= miframe_app()->params->implode('mensajes', '<div class="info"><ul>{{ <li>$1</li> }}</ul></div>') ?>

<?= miframe_app()->params->get('documento') ?>

<div class="docblock"><div class="docinfo"><?= $salida ?></div></div>
