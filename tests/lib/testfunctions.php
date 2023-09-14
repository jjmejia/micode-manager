<?php
/**
 * Librería de soporte para montaje de scripts para test de módulos.
 *
 * @author John Mejia
 * @since Mayo 2022
 */

if (!defined('MIFRAME_LOCALMODULES_PATH')) {
	define('MIFRAME_LOCALMODULES_PATH', realpath(__DIR__ . '/../../src/repository'));
}

function miframe_test_start(string $title) {

	$tipo = 'php';
	$estilos = dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/public/resources/css/tests.css';
?>
<!DOCTYPE html>
<html>
<head>
	<title><?= htmlentities($title) ?></title>
	<link rel="stylesheet" href="<?= $estilos ?>">
</head>
<body>
	<h1>
		<?= htmlentities($title) ?>
	</h1>

<?php

}

function miframe_test_pre(string $text) {

	echo '<pre class="code">' . $text . '</pre>' . PHP_EOL;
}

function miframe_test_end() {

	echo "</body></html>";
}

function miframe_test_include(array &$files) {

	// Carga archivos requeridos.
	foreach ($files as $k => $filename) {
		$path = '';
		if (file_exists(__DIR__ . '/../' . $filename)) {
			// Copias en el repositorio local del test
			$path = __DIR__ . '/../' . $filename;
		}
		elseif (file_exists(MIFRAME_LOCALMODULES_PATH . $filename)) {
			// Repositorio del sistema
			$path = MIFRAME_LOCALMODULES_PATH . $filename;
		}
		if ($path == '') {
			unset($files[$k]);
		}
		else {
			$files[$k] = $path;
			include_once $path;
		}
	}
}