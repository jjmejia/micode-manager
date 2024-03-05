<!DOCTYPE html>
<?php
/**
 * Layout principal para el Administrador de miCode.
 * Usado para presentación web (HTML) a través de "interface/views".
 *
 * @author John Mejía
 * @since Abril 2022
 */

include_once __DIR__ . '/subs.php';

// /public/favicon.png
$micode_logo = miframe_app()->router->createURL('favicon.png');

?>
<html>
<head>
	<title>miCode - <?= miframe_app()->params->get('author:e') ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<link rel="icon" type="image/x-icon" href="<?= $micode_logo ?>">
	<link rel="stylesheet" href="<?= str_replace('/tests/', '/', miframe_app()->router->createURL('resources/css/micode.css')) ?>">
</head>


<div class="header">
	<div class="header-logo"><img src="<?= $micode_logo ?>" width="18"></div>
	<b><a href="<?= miframe_app()->router->documentRoot() ?>"><span class="micode-main"><?= miframe_app()->params->get('page-title:e') ?></span> - <?= strtoupper(miframe_app()->params->get('author:e')) ?></a></b>
	<div class="session-info"><small><?= miframe_app()->params->get('author-email:e') ?></small></div>
</div>

<nav class="nav-main-app">
<?= miframe_app()->view->get('menu') ?>
</nav>

<div class="container">

	<?php

	// Botones de acción en cada página
	if (miframe_app()->params->get('page-buttons:count') > 0) {

	?>

	<div class="btn-container">

	<?php

		$primero = true;
		foreach (miframe_app()->params->get('page-buttons') as $enlace => $titulo) {
			// Valida enlace
			$params = array();
			if (is_array($titulo)) {
				$params = $titulo;
				$titulo = '';
				// En este caso, espera recibir 'titulo' bajo "_title" y los demas elementos son parametros
				if (isset($params['_title'])) {
					$titulo = $params['_title'];
					unset($params['_title']);
				}
			}
			$enlace = miframe_app()->router->createRouteURL($enlace, $params);
			// Procede a la presentación
			$clase = 'btn';
			if ($primero) {
				$clase .= ' btn-ppal';
				$primero = false;
			}
			echo "<a href=\"$enlace\" class=\"$clase\">$titulo</a>";
		};
	?>

	</div>

	<?php

	}

	?>

	<?= miframe_app()->view->get('contenido'); ?>

	<div class="foot">
		<b>miFrame</b> &copy; <?= date('Y') ?>
	</div>
</div>
</body>
</html>