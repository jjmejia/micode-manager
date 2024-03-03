<?php
/**
 * Script de soporte para presentación web (HTML)
 *
 * @author John Mejía
 * @since Abril 2022
 */

$tipo = $this->params->get('type:e');
$modulo_padre = $this->params->get('module:e');
$enlace_ppal = '';

if ($this->params->get('url-back-file') != '') {
	$enlace_modulo = $this->params->get('url-back-file');
	$enlace_ppal = "<a href=\"{$enlace_modulo}\">Regresar a {$modulo_padre}</a> | ";
}

// Marca la página para no usar menu
// $this->params->set('page-nomenu', true);

$salida = '';

if ($this->params->get('documento:len') <= 0) {
	// Muestra información general del módulo
	// $salida .= '<div class="docinfo">';
	// $require .= '<h3>Información general</h3>';
	$salida .= $this->params->iif('info->description', '<p>{{ info->description:e }}</p><hr>');
	// $salida .= '<p><b>Directorio origen:</b> ' . $this->params->get('dirbase:e') . '</p>';
	$salida .= $this->params->extract(
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
	$salida .= $this->params->implode('info->uses',
		'<h3>' . miframe_text('Módulos requeridos por esta librería') . '</h3><ol>{{ <li><a href="$1">$2</a></li> }}</ol>'
		);

	// PHP Namespaces encontrados
	$salida .= $this->params->implode('info->php-namespaces',
		'<h3>PHP Namespaces</h3><ol>{{ <li>$2 --> $1</li> }}</ol>'
		);

	// Muestra la información del módulo
	// $salida .= '</div>';
}

$total_requeridos = $this->params->get('require-files:count');
if ($total_requeridos > 0 && !isset($_REQUEST['docfunction'])) {
	// $salida .= '<div class="docinfo">';
	$salida .= '<h3>' . miframe_text('Archivos incluidos con este módulo') . ' (' . $total_requeridos . ')</h3>';
	$salida .= '<ol>';
	foreach ($this->params->get('require-files') as $modulo_req => $info_req) {
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

<link rel="stylesheet" href="<?= $this->router->createURL('resources/css/docblock.css') ?>">

<?= $this->params->implode('mensajes', '<div class="info"><ul>{{ <li>$1</li> }}</ul></div>') ?>

<?= $this->params->get('documento') ?>

<div class="docblock"><div class="docinfo"><?= $salida ?></div></div>
