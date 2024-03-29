<?php
/**
 * Script de soporte para presentación web (HTML).
 *
 * @author John Mejía
 * @since Abril 2022
 */

?>

<link rel="stylesheet" href="<?= miframe_app()->router->createURL('resources/css/forms.css') ?>">

<?php

	// Imprime mensajes
	if (miframe_app()->config->existsMessages()) {
		echo '<div class="info"><ul><li>' . implode('</li><li>', miframe_app()->config->getMessages()) . '</li></ul></div>';
	}

?>

<form action="<?= miframe_app()->params->get('form-action') ?>" method="POST">

	<?php

	// foreach (miframe_app()->params->get('form', array()) as $name => $info) {
	foreach (miframe_app()->config->getFormData() as $name => $info) {

		// Ignora cualquier elemento al que no se haya configurado titulo
		// if (!isset($info['title'])) { continue; }
		// $valor_sistema = miframe_app()->params->get('system->' . $name);

		if (isset($info['group']) && $info['group'] != '') {
			echo '<h4>' . htmlspecialchars($info['group']) . '</h4>' . PHP_EOL;
		}

		if ($info['title'] != '') {
			$info_opcional = htmlspecialchars($info['title']);
			if ($info['optional']) { $info_opcional .= ' <i class="muted">(' . htmlspecialchars(miframe_text('opcional')) . ')</i>'; }
			echo '<p>' . $info_opcional . '</p>' . PHP_EOL;
		}
		if (isset($info['html'])) {
			echo '<div>' . $info['html'] . '</div>' . PHP_EOL;
		}
		elseif (isset($info['type'])) {
			// ...
		}

		/*if ($valor_sistema != '') {
			// Si existe ayuda, la adiciona
			$pre = '';
			if (isset($info['help'])) { $pre = $info['help'] . '<br />'; }
			$info['help'] = $pre . miframe_text('Valor por defecto: $1', $valor_sistema);
		}*/
		if (isset($info['help'])) {
			echo '<div class="muted-block"><small>' . $info['help'] . '</small></div>' . PHP_EOL;
		}

	}

	$archivos = miframe_app()->params->get('update-files', array());

	if (count($archivos) > 0) {
		echo '<h4>' . miframe_text('Archivos de proyecto') . '</h4>' . PHP_EOL;
		foreach ($archivos as $name => $info) {
			echo '<p><label><input type="checkbox" name="' . $name . '" value="1"> ' . $info . '</label></p>';
		}
	}

	$ocultos = miframe_app()->params->get('form-hidden');
	if (is_array($ocultos)) {
		foreach ($ocultos as $param => $value) {
			echo '<input type="hidden" name="' . $param . '" value="' . htmlspecialchars($value) . '">';
		}
	}

	?>

	<div style="margin-top:30px">
		<input type="submit" name="configok" value="Guardar cambios" class="btn btn-ppal">
		<!--input type="button" value="Cancelar"  class="btn" onclick="javascript:document.load='index.php"-->
	</div>
</form>
