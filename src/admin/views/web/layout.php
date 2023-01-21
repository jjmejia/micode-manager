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

$micode_logo = $this->view->createURL('favicon.png');

?>
<html>
<head>
	<title>miCode - <?= $this->view->param('author:e') ?></title>
	<link rel="icon" type="image/x-icon" href="<?= $micode_logo ?>">
	<link rel="stylesheet" href="<?= str_replace('/tests/', '/', $this->view->createURL('/admin/public/css/micode.css')) ?>">
</head>


<div class="header">
	<b><!-- img src="<?= $micode_logo ?>" width="16" --><a href="<?= $this->view->documentRoot() ?>"><span class="micode-main"><?= $this->view->param('page-title:e') ?></span> - <?= strtoupper($this->view->param('author:e')) ?></a></b>
	<div class="session-info"><small><?= $this->view->param('author-email:e') ?></small></div>
</div>

<nav class="nav-main-app">
<?= $this->view->get('menu') ?>
</nav>

<div class="container">

	<?php

	// Botones de acción en cada página
	if ($this->view->param('page-buttons:count') > 0) {

	?>

	<div class="btn-container">

	<?php

		$primero = true;
		foreach ($this->view->param('page-buttons') as $enlace => $titulo) {
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