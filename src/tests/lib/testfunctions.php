<?php
/**
 * Librería de soporte para montaje de scripts para test de módulos.
 *
 * @author John Mejia
 * @since Mayo 2022
 */

function miframe_test_start(string $title) {

	$tipo = 'php';
	$estilos = dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/admin/public/css/tests.css';

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

	// Carga archivos requeridos
	foreach ($files as $k => $filename) {
		if (!file_exists($filename)) {
			unset($files[$k]);
		}
		else {
			include_once $filename;
		}
	}
}