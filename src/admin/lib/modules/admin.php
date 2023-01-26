<?php
/**
 * Librería de funciones requeridas para gestión del sistema.
 *
 * @author John Mejia
 * @since Abril 2022
 */

// Patrón para obtener modulos en directorio de proyectos
define('MIFRAME_APPMODULES_SUB', 'src/micode/modules');
// El contenedor de este directorio
define('MIFRAME_LIBADMIN_PATH', dirname(__DIR__));
// Repositorio (solo para Desarrollo)
// define('MIFRAME_REPOSITORY', MIFRAME_ROOT . '/repository');
// Librerías de soporte
include_once MIFRAME_LIBADMIN_PATH . '/modules/sistema.php';
include_once MIFRAME_LOCALMODULES_PATH . '/miframe/file/inifiles.php';

/**
 * Determina la clase que administra por tipo de proyecto.
 */
function micode_modules_class(string $type, bool $exit_on_error = true) {

	// Revisa configuración de dato global "php-namespaces" usado para buscar el path de clases.
	$namespaces = miframe_data_get('php-namespaces');
	if (!is_array($namespaces) || !isset($namespaces['local']['miframe\\manager\\*'])) {
		// Actualiza "php-namespaces" (usado al buscar clases)
		if (!is_array($namespaces)) { $namespaces = array(); }
		$namespaces['local']['miframe\\manager\\*'] = 'repository\\managers\\*.php';
		miframe_data_put('php-namespaces', $namespaces);
	}

	$nombre_clase = '\\miFrame\\Manager\\' . $type . 'Manager';
	$clase_manejador = miframe_class_load($nombre_clase);
	if ($clase_manejador === false && $exit_on_error) {
		miframe_error('No se encuentra archivo manejador de tipo de proyecto ($1)', $type);
	}

	return $clase_manejador;
}

/**
 * Consolida la información asociada al proyecto (configuración, módulos, etc.) y la retorna en un arreglo de datos.
 *
 * @param string $app_name
 * @return array
 */
function micode_modules_project_data(string $app_name, mixed $data_repo = false, mixed $m = false) {

	if (!is_array($data_repo)) {
		$data_repo = micode_modules_repo($app_name);
	}

	// Recupera solo información del proyecto local
	$data_proyecto = micode_modules_proyecto_ini($app_name, $data_repo);

	$data_proyecto['type'] = $data_repo['type'];

	if ($m === false) {
		$m = new \miFrame\Local\AdminModules(true);
	}

	// Obtiene modulos disponibles
	$listado = $m->getModulesApp($app_name, $data_repo);

	$listado['new'] = $m->getModulesNotInstalled();			// Disponibles (inicialmente, todos)

	/*
	// Entre los instalados, valida cuales ya no existen.
	// Revisa dependencias en los instalados para validar si debe adicionar algun modulo.
	// Reporta cambios desde la última vez que se registraron.
	foreach ($listado['pre'] as $modulo => $info) {

		if (!isset($listado['new'][$modulo])) {
			// Valida que no sea uno de los archivos listados en "req" (requeridos)
			// usualmente definidos en locals como "add"
			unset($listado['pre'][$modulo]);
		}
		else {
			// Mantiene datos del valor en "new" pero preserva path y otros
			$path = $info['path'];
			$datetime = $info['datetime'];
			$size = $info['size'];

			$info = $listado['new'][$modulo];

			// Los actuales (de "new") los guarda como "sys-xxx" para posible referencia
			$info['sys-path'] = $info['path'];
			$info['sys-datetime'] = $info['datetime'];
			$info['sys-size'] = $info['size'];
			// Restaura los valores locales
			$info['path'] = $path;
			$info['datetime'] = $datetime;
			$info['size'] = $size;

			if (isset($info['uses']) && count($info['uses']) > 0) {
				foreach ($info['uses'] as $k => $dep_name) {
					if (!isset($listado['pre'][$dep_name])) {
						if (isset($listado['new'][$dep_name])) {
							$listado['add'][$dep_name] = $listado['new'][$dep_name];
							unset($listado['new'][$dep_name]);
						}
					}
				}
			}

			// Remplaza contenido por uno mas completo
			$listado['pre'][$modulo] = $info;

			// Remueve elemento
			unset($listado['new'][$modulo]);
		}
	}

	// Ordena listados construidos en este ciclo
	ksort($listado['add']);
	// ksort($listado['del']);
	*/

	$sistema = micode_modules_sistema_ini();

	// Valida archivo README.md
	$readme = micode_modules_readme($data_proyecto['path']);

	$data_startup = false;
	// Valida los datos de starup (si alguno)
	if (isset($data_repo['startup'])) {
		$startup = $data_repo['startup'];
		$data_startup = micode_modules_startup_data($startup);
	}

	return array('config' => $data_proyecto,
		'modules' => $listado,
		'system' => $sistema,
		'readme-path' => $readme,
		'mirepo' => $data_repo,
		'startup' => $data_startup
	);
}

function micode_modules_readme(string $path) {

	$path_readme = '';
	$lpath = strtolower(str_replace("\\", '/', $path));
	$root = strtolower(str_replace("\\", '/', $_SERVER['DOCUMENT_ROOT'])) . '/';
	if (substr($lpath, 0, strlen($root)) !== $root) {
		$path_readme = miframe_path($_SERVER['DOCUMENT_ROOT'], $path, 'README.md');
	}
	else {
		$path_readme = miframe_path($path, 'README.md');
	}

	return $path_readme;
}

function micode_modules_project_new() {

	$data_proyecto = miframe_define_config('miproyecto');
	// Adiciona fecha de creación
	$data_proyecto['since'] = date('Y/m/d');
	$sistema = micode_modules_sistema_ini();
	$listado = array();

	return array('config' => $data_proyecto, 'modules' => $listado, 'system' => $sistema, 'readme-path' => '');
}

/**
 * Remueve archivos locales asociados a módulos previamente seleccionados.
 */
function micode_modules_remove(string $filename) {

	if (file_exists($filename)) {
		// echo "REMOVE $filename<hr>";
		unlink($filename);
	}

	// Revisar directorio, si no queda nada, removerlo
	$dir = dirname($filename);
	while (is_dir($dir)) {
		$fileList = glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_NOSORT);
		if (count($fileList) <= 0) {
			rmdir($dir);
			// Busca hacia abajo
			$dir = dirname($dir);
		}
		else {
			// Termina ciclo
			break;
		}
	}

	return true;
}

// $path es relativo al DOCUMENT_ROOT
function micode_modules_repo_filename(string $app_name, bool $check_file = true, string $path = '') {

	$file_repo = '';
	if ($path == '') {
		// Busca en el repositorio de proyectos la ruta
		$filepath = miframe_path(MIFRAME_PROJECTS_REPO, strtolower($app_name) . '.path');
		if (file_exists($filepath)) {
			$path = trim(file_get_contents($filepath));
		}
	}
	if ($path != '') {
		$file_repo = miframe_path($_SERVER['DOCUMENT_ROOT'], $path, 'micode.private', 'repo.ini');
		if ($path == '' || ($check_file && !file_exists($file_repo))) {
			miframe_error('No pudo ubicar archivo de proyecto en $1', $file_repo);
		}
	}
	else {
		miframe_error('No se encontró archivo con la ubicación del proyecto $1', $app_name);
	}

	return $file_repo;
}

function micode_modules_repo(string $app_name, string $filename = '') {

	// Lee archivo de proyecto local para recuperar la ruta final
	if ($filename == '') {
		$filename = micode_modules_repo_filename($app_name);
	}
	$data_repo = miframe_inifiles_get_data($filename, false);
	$data_repo['inifile'] = $filename;

	// Valida casos especiales
	if (!isset($data_repo['since'])) {
		$data_repo['since'] = miframe_filecreationdate($filename);
	}
	if (!isset($data_repo['startup'])) {
		$data_repo['startup'] = '';
	}

	// Ruta del proyecto
	$data_repo['path'] = dirname(dirname($filename));

	return $data_repo;
}

/**
 * Retorna path local donde se ubican los directorios de modulos.
 *
 * @param string $app_name
 * @param bool $create_dir TRUE crea el directorio destino si no existe.
 */
function micode_modules_path(string $app_name, bool $create_dir = false, mixed $data_repo = false) {

	$path_miframe = '';

	if (!is_array($data_repo)) {
		// Lee archivo de proyecto local para recuperar la ruta final
		$data_repo = micode_modules_repo($app_name);
	}
	if (isset($data_repo['path'])) {
		$appmodule_sub = 'micode';
		// Recupera data indicando si se usa un directorio diferente para los modulos
		if (isset($data_repo['app-modules']) && $data_repo['app-modules'] != '') {
			$appmodule_sub = $data_repo['app-modules'];
		}

		$path_miframe = miframe_path($data_repo['path'], $appmodule_sub);

		if ($create_dir && !is_dir($path_miframe)) {
			if (!@mkdir($path_miframe, 0777, true)) {
				$path_miframe = '';
			}
		}
	}

	return $path_miframe;
}

// return string/array
function micode_modules_types(string $type = '') {

	$type = strtolower(trim($type));
	$listado = miframe_data_get('modules-types');
	if (!is_array($listado)) {
		$inifile = miframe_path(MIFRAME_ROOT, 'data/lib-managers.ini');
		$listado = miframe_inifiles_get_data($inifile, false);
		// Valida que existan los respectivos archivos
		foreach ($listado as $tipo => $titulo) {
			if ($tipo != '') {
				$clase_manejador = micode_modules_class($tipo, false);
				if ($clase_manejador === false) {
					unset($listado[$tipo]);
				}
				// elseif ($type != '' && $type == $tipo) {
					// return $titulo;
				// }
			}
		}
		// Lo guarda en memoria para no volver a leer el archivo .ini si se requiere de nuevo
		miframe_data_put('modules-types', $listado);
	}

	if ($type != '') {
		// Solo requiere el titulo de un único modulo
		if (isset($listado[$type])) { return $listado[$type]; }
		else { return false; }
	}

	// Retorna listado completo
	return $listado;
}

function micode_modules_eval_type(string $type) {

	$validos = false;
	if ($type != '') {
		// Recupera tipos validos
		$validos = (micode_modules_types($type) !== false);
	}

	return $validos;
}

/**
 * Inicializa arreglo de datos
 */
function miframe_get_inifile(string $filename, array &$data) {

	$dataini = miframe_inifiles_get_data($filename, false);
	foreach ($dataini as $k => $v) {
		if (array_key_exists($k, $data)) {
			$data[$k] = $v;
		}
	}

	return true;
}

/**
 * Recupera información del proyecto
 */
function micode_modules_proyecto_ini(string $app_name, array $data_repo) {

	$path_modulos = micode_modules_path($app_name, false, $data_repo);

	// Datos esperados del bloque proyecto
	$data = miframe_define_config('miproyecto', $data_repo['type']);

	$filename = miframe_path($path_modulos, 'config', 'miproyecto.ini');
	if (!file_exists($filename)) {
		miframe_error('Archivo **miproyecto.ini** no encontrado.', debug: $filename);
	}

	miframe_get_inifile($filename, $data);

	// Redefine nombre y tipo del proyecto para coincidir con el path y dato registrado en $data_repo.
	$data['project-name'] = strtolower($app_name);
	$data['type'] = strtolower($data_repo['type']);

	// Verifica si tiene registrada la fecha de creación. Si no, asume la de creación del directorio
	// o de modificado, la que sea mas antigua (a veces al copiar un directorio o moverlo, la fecha de
	// creación puede aparecer como más reciente).
	if (!isset($data['since']) || $data['since'] == '') {
		$data['since'] = miframe_filecreationdate($filename). ' (A)';
	}

	// Ruta del proyecto
	$data['path'] = $data_repo['path'];

	return $data;
}

function micode_modules_startup_data(string $startup = '') {

	$data = array();
	$startup = strtolower(trim($startup));
	$path = micode_modules_repository_path('templates/startup');
	if ($startup != '') { $path .= DIRECTORY_SEPARATOR . $startup; }
	else { $path .= '/*'; }
	$dirs = glob($path, GLOB_ONLYDIR | GLOB_NOSORT);

	if (is_array($dirs) && count($dirs) > 0) {
		foreach ($dirs as $k => $subdir) {
			$inifile = $subdir . '/tpl-config.ini';
			if (file_exists($inifile)) {
				$dataini = miframe_inifiles_get_data($inifile, false);
				if (isset($dataini['type'])) {
					$dataini['path'] = $subdir;
					$basename = strtolower(basename($subdir));
					// Busca elemento particular
					if ($startup != '' && $startup == $basename) {
						return $dataini;
					}
					$data[$basename] = $dataini;
				}
			}
		}
	}

	return $data;
}
