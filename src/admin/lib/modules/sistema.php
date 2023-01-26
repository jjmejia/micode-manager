<?php
/**
 * Librería de funciones requeridas para gestión del sistema.
 *
 * @author John Mejia
 * @since Mayo 2022
 */

function micode_modules_sistema_ini(bool $process = false) {

	// Adiciona datos del sistema
	$sistema = array();
	if (defined('MIFRAME_ROOT')) {
		// Lee archivo de configuración de sistema (complementa miproyecto.ini)
		// El archivo "sistema.ini" es un archivo propio del "admin", por tanto puede
		// guardarse en src/config para efectos de que sea usado un unico directorio para los
		// .ini. Sin embargo, no significa que todos los .ini de ese directorio pertenezcan a
		// los modulos del repositorio, pero si que son de alguna forma administrados desde módulos
		// de "micode" (editor de inis).
		// En este caso, un proyecto puede tener los siguientes directorios config:
		// * src/micode/config : Parámetros de los módulos de micode y del proyecto.
		// * config: miproyecto.ini, lista de modulos, etc - Al mismo nivel de "src". Adiministrado
		//   por micode/admin.
		$filename = MIFRAME_LOCALCONFIG_PATH . '/sistema.ini';

		if (file_exists($filename)) {
			$datasys = miframe_define_config('sistema', 'php');
			$sistema = parse_ini_file($filename, false, INI_SCANNER_TYPED) + $datasys;
		}
	}

	if ($process) {
		foreach ($sistema as $k => $v) {
			$k = strtolower($k);
			if ($k == 'debug') {
				miframe_debug_enable($v != false);
			}
			elseif (!isset($data[$k]) && $v != '' && !is_array($v)) {
				miframe_data_put($k, trim($v), false);
			}
		}
	}

	return $sistema;
}

function miframe_define_config(string $group = '', string $type = '') {

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

	return miframe_path(MIFRAME_ROOT, 'repository', $path);
}

function micode_modules_dataconfig_path(string $path) {

	return miframe_path(MIFRAME_BASEDIR, 'dataconfig', $path);
}

function micode_modules_enlace(string $cfg_name, mixed $data_repo = false) {

	$enlace = '';
	$path_modulos = micode_modules_path($cfg_name, false, $data_repo);
	$filename = miframe_path($path_modulos, '..', 'index.php');
	if (file_exists($filename)) {
		// Compara MIFRAME_ROOT con el path del directorio de proyectos.
		// Los primeros items iguales corresponden al directorio "www" y los restantes
		// se usan para crear el URl de acceso directo al proyecto.
		$root = explode('/' , str_replace('\\', '/', strtolower(dirname(MIFRAME_ROOT))));
		$user = explode('/' , str_replace('\\', '/', strtolower(dirname($filename))));
		for ($i = 0; $i < count($root); $i++) {
			if ($root[$i] != $user[$i]) {
				break;
			}
			unset($user[$i]);
		}
		$enlace = '/' . implode('/', $user);
	}

	return $enlace;
}

function micode_modules_explore(string $enlace, string $path_base) {

	$explorer = new \miFrame\Utils\Explorer();
	$doc = new \miFrame\Utils\DocSimple();
	// Función para realizar Parser
	$parser = new \Parsedown();
	// Escape HTML even in trusted input
	$parser->setMarkupEscaped(true);
	$doc->parserTextFunction = array($parser, 'text');
	$doc->clickable = true;

	$explorer->setContentsFun('md', array($parser, 'text'));
	$explorer->setContentsFun('php', array($doc, 'getDocumentationHTML'), 'filename'); // '/index.php?doc={file}&dir={dir}'

	$explorer->setRoot($path_base);

	return $explorer->exploreHTML($enlace);
}