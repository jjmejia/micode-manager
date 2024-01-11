<?php
/**
 * Librería de funciones requeridas para gestión del sistema.
 *
 * @author John Mejia
 * @since Mayo 2022
 */

function micode_modules_sistema_ini() {

	// Carga listado de elementos esperados
	$sistema = micode_modules_load_cfgs('sistema', 'php');

	// Lee archivo de configuración de sistema (complementa miproyecto.ini)
	$filename = MIFRAME_LOCALCONFIG_PATH . '/sistema.ini';

	if (file_exists($filename)) {
		// Carga datos registrados en el archivo .ini
		$data = parse_ini_file($filename, false, INI_SCANNER_TYPED);
		if (is_array($data)) {
			foreach ($data as $key => $info) {
				$key = strtolower(trim($key));
				$sistema[$key] = trim($info);
			}
		}
	}

	return $sistema;
}

function micode_modules_load_cfgs(string $group = '', string $type = '') {

	$retornar = array();
	$group = strtolower(trim($group));
	$filenames = array();

	if ($group == '' || $group == 'miproyecto') {
		$filenames[] = micode_modules_dataconfig_path('miproyecto-cfg.ini');
	}
	if ($group == '' || $group == 'sistema') {
		$filenames[] =  micode_modules_dataconfig_path('sistema-cfg.ini');
	}
	if ($type != '') {
		$inifile = micode_modules_dataconfig_path("type-{$type}-cfg.ini");
		$filenames[] = $inifile;
	}

	foreach ($filenames as $k => $filename) {
		if (file_exists($filename)) {
			$data = parse_ini_file($filename, true, INI_SCANNER_TYPED);
			foreach ($data as $k => $info) {
				$valor = '';
				if (isset($info['default'])) { $valor = $info['default']; }
				$retornar[strtolower($k)] = $valor;
			}
		}
		// else { echo "PATH NO ENCONTRADO $filename<hr>"; }
	}

	return $retornar;
}

function micode_modules_repository_path(string $path = '') {

	return miframe_path(MIFRAME_SRC, 'repository', $path);
}

function micode_modules_dataconfig_path(string $path) {

	return miframe_path(MIFRAME_DATA, 'config', $path);
}

function micode_modules_enlace(string $cfg_name, mixed $data_repo = false) {

	$enlace = '';
	$path_modulos = micode_modules_path($cfg_name, false, $data_repo);
	$filename = miframe_path($path_modulos, '..', 'index.php');
	if (file_exists($filename)) {
		// Compara DOCUMENT_ROOT con el path del directorio de proyectos.
		// Los primeros items iguales corresponden al directorio "www" y los restantes
		// se usan para crear el URl de acceso directo al proyecto.
		$root = str_replace('\\', '/', strtolower($_SERVER['DOCUMENT_ROOT'])) . '/';
		$user = str_replace('\\', '/', strtolower(dirname($filename)));
		$len = strlen($root);
		if (substr($user, 0, $len) == $root) {
			$enlace = '/' . substr($user, $len);
		}
	}

	return $enlace;
}

function micode_modules_explore(string $enlace, string $path_base) {

	$explorer = new \miFrame\Utils\Explorer\ExplorerHTML();
	$explorer->useFavorites = false;

	// Función para realizar Parser
	$parser = new \Parsedown();
	// Escape HTML even in trusted input
	$parser->setMarkupEscaped(true);
	$explorer->setContentsFun('md', array($parser, 'text'));

	// Documentación
	// $doc = new \miFrame\Utils\DocSimple\DocSimpleHTML();
	// $doc->parserTextFunction = array($parser, 'text');
	// $doc->clickable = true;
	// $explorer->setContentsFun('php', array($doc, 'render'), 'filename'); // '/index.php?doc={file}&dir={dir}'

	$explorer->setRoot($path_base);

	return $explorer->render($enlace);
}

/**
 * Remueve DOCUMENT_ROOT del path indicado.
 * Si el path indicado no contiene DOCUMENT_ROOT, lo retorna tal cual.
 *
 * @param string $path Path a revisar.
 * @return string Path.
 */
function micode_modules_remove_root(string $path) {

	$path = str_replace("\\", '/', trim($path));
	if ($path !== '') {
		// Remueve el document-root
		$root = str_replace("\\", '/', $_SERVER['DOCUMENT_ROOT']) . '/';
		$len = strlen($root);
		$lpath = strtolower($path) . '/';
		if (substr($lpath, 0, $len) === strtolower($root)) {
			$path = substr($lpath, $len);
		}
	}

	return $path;
}
