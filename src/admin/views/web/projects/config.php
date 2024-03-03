<?php
/**
 * Script de soporte para presentación web (HTML).
 *
 * @author John Mejía
 * @since Abril 2022
 */

?>

<link rel="stylesheet" href="<?= $this->router->createURL('resources/css/forms.css') ?>">

<?= $this->params->implode('mensajes', '<div class="info"><ul>{{ <li>$1</li> }}</ul></div>') ?>

<form action="<?= $this->params->get('form-action') ?>" method="POST">

<?php

/* $thisViewData() recibido en uno de los params!

	<p class="separator">Título:

	<div>
		<input type="text" name="cfgtitle" value="<?= htmlentities($config['project-title']) ?>" class="form-control">
	</div>

	</p>
	<p>Descripción: </p>

	<div>
		<textarea name="cfgdesc" class="form-control" rows="5"><?= htmlentities($config['description']) ?></textarea>
	</div>

	<?php

	$select = '';
	// "tiposvalidos" solo se define si es viable la edición del tipo
	if (count($this->view->params['tiposvalidos']) > 0) {
		// Habilita edición del tipo
		foreach ($this->view->params['tiposvalidos'] as $tipo => $nombre) {
			$select .= '<option value="' . htmlspecialchars($tipo) . '">' . htmlspecialchars($nombre) . '</option>';
		}

	?>

<p>Tipo (Lenguaje de programación a usar): </p>

<div>
	<select name="cfgtipo" class="form-control"><?= $select ?></select>
</div>

	<?php

	}
	else{
		echo '<p>Tipo (Lenguaje de programación usado): <b>' . strtoupper($config['type']) . '</b></p>';
	}

	?>
*/

?>

	<div style="margin-top:30px">
		<input type="submit" name="configok" value="Guardar cambios" class="btn btn-ppal">
		<!--input type="button" value="Cancelar"  class="btn" onclick="javascript:document.load='index.php"-->
	</div>
</form>
