<?php
/**
 * Funciones de soporte para manejo de Tests.
 *
 * @author John Mejia
 * @since Mayo 2022
 */

function micode_modules_tests(string $type) {

	// Obtiene directorios (los modulos se definen (directorio)/(nombre archivo sin extensión))
	// Luego, requiere dos busquedas: una para obtener los directorios y otra para los archivos en cada uno.
	$listado = array();

	$clase_manejador = micode_modules_class($type, false);

	if ($clase_manejador === false) { return $listado; }

	// $extension = $clase_manejador->Extension();

	// AL ejecutar test, no se han declarado constantes
	$repositorio = MIFRAME_SRC . '/repository';

	// Modulos existentes en admin por defecto (Sistema)
	// $path = miframe_path($repositorio, $type, 'tests', '*' . $extension);
	$path = MIFRAME_ROOT . "/tests/*." . $type;

	$fileList = glob($path, GLOB_NOSORT);

	if (count($fileList) > 0) {
		foreach ($fileList as $k => $filename) {
			if (!is_dir($filename)) {
				$llave_listado = strtolower(basename($filename));
				if ($llave_listado == 'index.php') {
					// No incluye archivo index
					continue;
				}
				$listado[$llave_listado] = micode_modules_tests_info($filename, $clase_manejador);
			}
		}
	}

	/* AGO/2022 - Retira manejo de externals
	// Busca en externals.ini
	$data = micode_modules_composer('externals.ini');
	foreach ($data as $modulo => $info) {
		if (isset($info['test']) && $info['test'] != '') {
			// $filename = $info['file'];
			$llave_listado = 'test:' . strtolower($modulo);
			// $llave_listado = strtolower(basename($info['test']));
			$listado[$llave_listado] = micode_modules_tests_info($info['test'], $clase_manejador);
		}
	}
	*/

	ksort($listado);

	return $listado;
}

function micode_modules_tests_info(string $filename, object &$clase_manejador) {

	$arreglo = array(
		'path' => $filename,
		'description' => '',
		'author' => '',
		'since' => '',
		'datetime' => filemtime($filename),
		'size' => filesize($filename),
		);

	$documento = $clase_manejador->getSummary($filename);

	$arreglo['description'] = $documento['description'];
	$arreglo['author'] = $documento['author'];
	$arreglo['since'] = $documento['since'];

	return $arreglo;
}
