<?php
/**
 * Script de soporte para presentación web (HTML).
 *
 * @author John Mejía
 * @since Abril 2022
 */

/* Solo para proyectos en PHP!
$sistema = $this->params->get('system');
$sizelog = $this->params->get('config->php-errorlog-size'];
if ($sizelog == '') {
	$sizelog = $this->params->iif(miframe_text2bytes($sistema['php-errorlog-size']) <= 0, '10MB (Limitado por precaución)', $sistema['php-errorlog-size'] . ' (Configuración del sistema)');
}

<p>Tamaño de PHP Error log: <?= $sizelog ?></p>

*/

$readme = miframe_app()->params->get('readme');

if ($readme != '') {
	$readme = '<div class="docblock">' . PHP_EOL .
		'<div class="docfile">README.md</div><div>' .
		$readme .
		'</div></div>';
}

$type = miframe_app()->params->get('mirepo->type');

$opciones = miframe_debug_config_options();
$valor = miframe_app()->params->get('config->debug');

$startup = miframe_app()->params->get('startup->title');

$infotipo = micode_modules_types($type);

?>

<link rel="stylesheet" href="<?= miframe_app()->router->createURL('resources/css/docblock.css') ?>">

<div class="docblock">
<div class="docfile"><?= miframe_app()->params->get('config->project-title:e') ?></div>
<div>

<?= miframe_app()->params->implode('mensajes', '<div class="info"><ul>{{ <li>$1</li> }}</ul></div>') ?>

<!-- <div class="app-desc"><?= miframe_app()->params->get('config->project-desc-info') ?></div> -->

<p><b>Path:</b> <?= miframe_app()->params->get('mirepo->path') ?></p>

<p><b>Path módulos miCode:</b> <?= miframe_app()->params->get('mirepo->app-modules') ?>
	(<?= miframe_app()->params->get('modules->pre:count') ?> módulos instalados)
</p>

<p><b>Tipo (Lenguaje de programación usado):</b> <?= htmlspecialchars($infotipo) ?></p>

<?= miframe_app()->params->iif(($startup != ''), '<p><b>Modelo inicial:</b> ' . htmlspecialchars($startup) . '</p>') ?>

<p><b>Creado en:</b> <?= miframe_app()->params->get('mirepo->since:date') ?></p>

</div>
</div>

<?= $readme ?>
