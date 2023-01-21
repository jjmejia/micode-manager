<?php
/**
 * Script de soporte para presentación web (HTML).
 *
 * @author John Mejía
 * @since Abril 2022
 */

/* Solo para proyectos en PHP!
$sistema = $this->view->param('system');
$sizelog = $this->view->param('config->php-errorlog-size'];
if ($sizelog == '') {
	$sizelog = $this->view->iif(miframe_text2bytes($sistema['php-errorlog-size']) <= 0, '10MB (Limitado por precaución)', $sistema['php-errorlog-size'] . ' (Configuración del sistema)');
}

<p>Tamaño de PHP Error log: <?= $sizelog ?></p>

*/

$readme = $this->view->param('readme');

if ($readme != '') {
	$readme = '<div class="docblock">' . PHP_EOL .
		'<div class="docfile">README.md</div><div>' .
		$readme .
		'</div></div>';
}

$type = $this->view->param('mirepo->type');

$opciones = miframe_debug_config_options();
$valor = $this->view->param('config->debug');

$startup = $this->view->param('startup->title');

$infotipo = micode_modules_types($type);

?>

<link rel="stylesheet" href="<?= $this->view->createURL('/admin/public/css/docblock.css') ?>">

<div class="docblock">
<div class="docfile"><?= $this->view->param('config->project-title:e') ?></div>
<div>

<?= $this->view->param('mensajes:implode', '', '<div class="info"><ul>{{ <li>$1</li> }}</ul></div>') ?>

<!-- <div class="app-desc"><?= $this->view->param('config->project-desc-info') ?></div> -->

<p><b>Path:</b> <?= $this->view->param('mirepo->path') ?></p>

<p><b>Path módulos miCode:</b> <?= $this->view->param('mirepo->app-modules') ?>
	(<?= $this->view->param('modules->pre:count') ?> módulos instalados)
</p>

<p><b>Tipo (Lenguaje de programación usado):</b> <?= htmlspecialchars($infotipo) ?></p>

<?= $this->view->iif(($startup != ''), '<p><b>Modelo inicial:</b> ' . htmlspecialchars($startup) . '</p>') ?>

<p><b>Creado en:</b> <?= $this->view->param('mirepo->since:date') ?></p>

</div>
</div>

<?= $readme ?>
