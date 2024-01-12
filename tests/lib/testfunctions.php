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
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<link rel="stylesheet" href="<?= $estilos ?>">
</head>
<body>
	<h1>
		<?= htmlentities($title) ?>
		<small>miCode-Manager Demo</small>
	</h1>
	<div class="content-test">

<?php

}

function miframe_test_pre(string $text) {

	echo '<pre class="code">' . $text . '</pre>' . PHP_EOL;
}

function miframe_test_end() {

	echo "</div></body></html>";
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

function miframe_test_datalink(string $info, array $data) {

	$enlace_base = basename(miframe_server_get('SCRIPT_FILENAME'));
	if (count($data) > 0) {
		$enlace_base .= '?' . http_build_query($data);
	}
	$enlace_base = '<a href="' . $enlace_base . '">' . $info . '</a>';

	return $enlace_base;
}

function miframe_test_option(string $option, string $text_ok, string $text_nok, string &$link) {

	$retornar = false;

	$data = $_REQUEST;
	$info = $text_ok;
	if (array_key_exists($option, $_REQUEST)) {
		$retornar = true;
		unset($data[$option]);
		$info = $text_nok;
	}
	else {
		$data[$option] = '';
	}

	if ($link != '') { $link .= ' | '; }
	$link .= miframe_test_datalink($info, $data);

	return $retornar;
}