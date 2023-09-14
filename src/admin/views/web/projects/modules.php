<?php
/**
 * Script de soporte para presentación web (HTML).
 *
 * @author John Mejía
 * @since Abril 2022
 */

/**
 * Visualiza listados de modulos en la página de edición de proyectos.
 */
function checkModules(&$view, string $param_name, string $default = '') {

	echo '<ul class="form-list">';

	$total_modulos = $view->param('modules->' . $param_name . ':count');
	if ($total_modulos <= 0) {
		echo '<li style="color:#777">No hay elementos para mostrar</li>';
	}
	else {
		foreach ($view->param('modules->' . $param_name) as $name => $info) {
			$checked = $default;
			$modificado = '';
			$descripcion = '';
			if (isset($info['description'])) { $descripcion = $info['description']; }
			if (isset($info['require-total']) && $info['require-total'] > 1) {
				$modificado .= '<div class="box-data" style="color:#333"><b>Este módulo agrupa ' . ($info['require-total']) . ' archivos.</b></div>';
			}
			if (isset($info['uses']) && count($info['uses']) > 0) {
				$modificado .= '<div class="box-data"><b>Requiere:</b> ' . implode(', ', $info['uses']) . '</div>';
			}
			/*if (isset($info['used-by']) && count($info['used-by']) > 0) {
				$modificado .= '<div class="box-data"><b>Usado por:</b> ' . implode(', ', $info['used-by']) . '</div>';
			}*/
			if (isset($info['datetime']) && $info['datetime'] > 0) {
				$modificado .= '<div class="box-data"><b>Fecha instalación/modificación:</b> ' . date('Y/m/d H:i:s', $info['datetime']) . '</div>';
			}
			// Ojo que $name contiene "/" y eso afecta la lectura de $this->view->param()
			if (isset($info['changed']) && $info['changed'] !== false) {
				$modificado .= '<div class="box-data box-alert"><b>Repositorio actualizado</b> en ' .
					date('Y/m/d H:i:s', $info['sysdata']['datetime']) .
					'</div>';
			}
	?>

	<li>
		<label class="form-checkbox">
			<input type="checkbox" name="<?= $param_name ?>[]" value="<?= $name ?>" <?= $checked ?>>
			<div class="title-item"><?= $name ?></div>
			<?= $descripcion ?>
			<?= $modificado ?>
		</label>
	</li>

	<?php

		}
	}

	echo '</ul>';
}

?>

<link rel="stylesheet" href="<?= $this->view->createURL('/public/resources/css/forms.css') ?>">

<?= $this->view->param('mensajes:implode', '', '<div class="info"><ul>{{ <li>$1</li> }}</ul></div>') ?>


<form action="<?= $this->view->param('form-action') ?>" method="POST">

	<h3>Módulos instalados (<?= $this->view->param('modules->pre:count') ?>)</h3>

	<?= $this->view->iif(
			'ini_datetime:!empty',
			'<p class="separator">Última modificación realizada en {{ ini_datetime:date }}</p>'
			) ?>
	<?php

	if ($this->view->param('modules->pre:!empty')) {
		$reconstruir_parcial = '';
		if ($this->view->param('modules->changes:!empty')) {
			echo miframe_box('Aviso importante',
					'Hay ' . $this->view->param('modules->changes') . ' modulos instalados que presentan cambios respecto al Repositorio. Verifique.',
					'warning'
					);
			$reconstruir_parcial = '<option value="changed">Reconstruir los módulos que reportan cambios respecto al Repositorio</option>' . PHP_EOL;
		}
		echo '<p><select name="rebuild" class="form-control">' . PHP_EOL .
		'<option value="">Reconstruir módulos instalados?</option>' . PHP_EOL .
		$reconstruir_parcial .
		'<option value="all">Reconstruir todos los módulos instalados</option>' . PHP_EOL .
		'</select>' . PHP_EOL .
		'<input type="submit" name="modulok" value="Actualizar" class="btn btn-ppal">' . PHP_EOL .
		'</p>';
	}

	?>

	<?= checkModules($this->view, 'pre', 'checked'); ?>

	<?php
	/*
	<h3>Módulos adicionales sugeridos (<?= $this->view->param('modules->add:count') ?>)</h3>

	<?= $this->view->iif(
			'modules->add:!empty',
			'<p class="separator">Los módulos instalados pueden requerir de algunos de los siguientes módulos:</p>'
			) ?>

	<?= $this->view->checkModules('add') ?>
	*/ ?>

	<h3>Módulos removidos del repositorio principal (<?= $this->view->param('modules->del:count') ?>)</h3>

	<?= $this->view->iif(
			'modules->del:!empty',
			'<p class="separator">Estos módulos fueron instalados pero ya no se encuentran disponibles en el repositorio. ' .
			'Deseleccione para removerlos del proyecto.</p>'
			) ?>

	<?= checkModules($this->view, 'del', 'checked') ?>


	<h3>Módulos disponibles (<?= $this->view->param('modules->new:count') ?>)</h3>

	<?= checkModules($this->view, 'new') ?>

	<div style="margin-top:30px">
		<input type="submit" name="modulok" value="Guardar cambios" class="btn btn-ppal">
		<!--input type="button" value="Cancelar"  class="btn" onclick="javascript:document.load='index.php"-->
	</div>
</form>
