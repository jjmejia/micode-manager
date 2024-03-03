<?php
/**
 * Autoload de clases para uso en proyectos de miCode.
 * Requiere constantes MIFRAME_SRC y MIFRAME_LOCALCONFIG_PATH para leer namespaces asociados al
 * proyecto local.
 *
 * @micode-uses miframe-common-functions
 * @micode-uses miframe-common-debug
 * @author John Mejia
 * @since Enero 2023
 */

// Guías para autoloaders: https://www.php-fig.org/psr/psr-4/
function miframe_autoload_classes(string $className) {

	$class = strtolower($className);
	$basename = basename($class);
	$path = '';

	// Namespaces definidos en parámetros del proyecto actual
	$namespaces = array(
		'micode' => miframe_data_get('php-namespaces-micode'),
		'local' => miframe_data_get('php-namespaces-local')
		);

	if (!is_array($namespaces['micode'])) {
		// No se han cargado datos, procede
		$namespaces['micode'] = array();
		// Namespaces de los modulos incluidos
		$inifile = miframe_path(MIFRAME_LOCALCONFIG_PATH, 'php-namespaces.ini');
		if (file_exists($inifile)) {
			$namespaces['micode'] = parse_ini_file($inifile, false, INI_SCANNER_TYPED);
		}
		// Registra valores
		miframe_data_put('php-namespaces-micode', $namespaces['micode']);
	}

	if (!is_array($namespaces['local'])) {
		$namespaces['local'] = array();
		// Namespaces del proyecto local
		$inifile = miframe_path(MIFRAME_LOCALCONFIG_PATH, 'php-namespaces-local.ini');
		if (file_exists($inifile)) {
			$contenido = file_get_contents($inifile);
			$arreglo = explode("\n", $contenido);
			foreach ($arreglo as $i => $linea) {
				$linea = trim($linea);
				// Omite comentarios y líneas en blanco
				if ($linea == '' || substr($linea, 0, 1) === ';') { continue; }
				$params = explode('=', $linea . '=');
				$params[0] = strtolower(trim($params[0]));
				$params[1] = trim($params[1]);
				if ($params[1] != '') {
					$namespaces['local'][$params[0]] = $params[1];
				}
			}
		}
		// Registra valores
		miframe_data_put('php-namespaces-local', $namespaces['local']);
	}

	// Ejemplos:
	// miframe\manager\* = xxx\managers\*.php
	// miframe\local\* = xxx\lib\class\*.php
	if (count($namespaces['local']) > 0) {
		if (isset($namespaces['local'][$class])) {
			$path = $namespaces['local'][$class];
			if (!file_exists($path)) {
				// Lo referencia al raíz del proyecto
				$path = miframe_path(MIFRAME_SRC, $path);
			}
		}
		else {
			// Busca parciales
			foreach ($namespaces['local'] as $nameclass => $namepath) {
				if (substr($nameclass, -1) == '*') {
					// Valida directorio parcial
					$len = strlen($nameclass) - 1;
					$compara = substr($class, 0, $len) . '*';
					if ($compara == $nameclass) {
						$path = miframe_path(MIFRAME_SRC, str_replace('*', substr($className, $len), $namepath));
						break;
					}
				}
			}
		}
	}

	// Busca en namespaces asociados a las clases de repositorio incluidas en el proyecto
	if ($path == ''
		&& isset($namespaces['micode'][$class])
		&& $namespaces['micode'][$class] != ''
		) {
		$path = $namespaces['micode'][$class];
		if (!file_exists($path)) {
			// Adiciona path de modulos locales
			$path = miframe_path(MIFRAME_LOCALMODULES_PATH, $path);
		}
	}

	// echo "$className - $path (" . file_exists($path) . ")<hr>"; // exit;

	if ($path != '' && file_exists($path)) {
		include_once $path;
	}
}

// Autoload para carga de clases ("miframe_autoload_classes" se define en modules.php).
spl_autoload_register('miframe_autoload_classes');