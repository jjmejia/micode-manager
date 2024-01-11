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

$micode_logo = $this->router->createURL('public/favicon.png');

?>
<html>
<head>
	<title>miCode - <?= $this->params->get('author:e') ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<link rel="icon" type="image/x-icon" href="<?= $micode_logo ?>">
	<link rel="stylesheet" href="<?= str_replace('/tests/', '/', $this->router->createURL('/public/resources/css/micode.css')) ?>">
</head>


<div class="header">
	<div class="header-logo"><img src="<?= $micode_logo ?>" width="18"></div>
	<b><a href="<?= $this->router->documentRoot() ?>"><span class="micode-main"><?= $this->params->get('page-title:e') ?></span> - <?= strtoupper($this->params->get('author:e')) ?></a></b>
	<div class="session-info"><small><?= $this->params->get('author-email:e') ?></small></div>
</div>

<nav class="nav-main-app">
<?= $this->view->get('menu') ?>
</nav>

<div class="container">

	<?php

	// Botones de acción en cada página
	if ($this->params->get('page-buttons:count') > 0) {

	?>

	<div class="btn-container">

	<?php

		$primero = true;
		foreach ($this->params->get('page-buttons') as $enlace => $titulo) {
			// Valida enlace
			$llave = '';
			$params = '';
			if (substr($enlace, 0, 1) !== '?') { $llave = $enlace; }
			else { $params = substr($enlace, 1); $llave = null; }
			$enlace = $this->router->getFormAction($llave, true, $params);
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

	<?= $this->view->get('contenido'); ?>

	<div class="foot">
		<b>miFrame</b> &copy; <?= date('Y') ?>
	</div>
</div>
</body>
</html>