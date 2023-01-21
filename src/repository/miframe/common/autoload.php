<?php
/**
 * Autoload de clases para uso en proyectos de miCode.
 * Requiere constantes MIFRAME_ROOT y MIFRAME_LOCALCONFIG_PATH para leer namespaces asociados al
 * proyecto local.
 *
 * @micode-uses miframe/common/functions
 * @micode-uses miframe/common/debug
 * @author John Mejia
 * @since Enero 2023
 */

// Guías para autoloaders: https://www.php-fig.org/psr/psr-4/
function miframe_autoload_classes(string $className) {

	$class = strtolower($className);
	$basename = basename($class);
	$path = '';

	// Namespaces definidos en parámetros del proyecto actual
	$namespaces = miframe_data_get('php-namespaces');
	if (!is_array($namespaces)) {
		// No se han cargado datos, procede
		$namespaces = array();
		// Namespaces de los modulos incluidos
		$inifile = miframe_path(MIFRAME_LOCALCONFIG_PATH, 'php-namespaces.ini');
		if (file_exists($inifile)) {
			$namespaces['micode'] = parse_ini_file($inifile, false, INI_SCANNER_TYPED);
		}
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
		// Registra en variable global
		miframe_data_put('php-namespaces', $namespaces);
	}

	// Ejemplos:
	// miframe\manager\* = xxx\managers\*.php
	// miframe\local\* = xxx\lib\class\*.php
	if (is_array($namespaces)) {
		if (isset($namespaces['local'])
			&& is_array($namespaces['local'])
			) {
			if (isset($namespaces['local'][$class])) {
				$path = $namespaces['local'][$class];
				if (!file_exists($path)) {
					// Lo referencia al raíz del proyecto
					$path = miframe_path(MIFRAME_ROOT, $path);
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
							$path = miframe_path(MIFRAME_ROOT, str_replace('*', substr($className, $len), $namepath));
							break;
						}
					}
				}
			}
		}

		// Busca en namespaces asociados a las clases de repositorio incluidas en el proyecto
		if ($path == ''
			&& isset($namespaces['micode'])
			&& is_array($namespaces['micode'])
			&& isset($namespaces['micode'][$class])
			&& $namespaces['micode'][$class] != ''
			) {
			$path = $namespaces['micode'][$class];
			if (!file_exists($path)) {
				// Adiciona path de modulos locales
				$path = miframe_path(MIFRAME_LOCALMODULES_PATH, $path);
			}
		}
	}

	// echo "$className - $path (" . file_exists($path) . ")<hr>"; // exit;

	if ($path != '' && file_exists($path)) {
		include_once $path;
	}
}

// Autoload para carga de clases ("miframe_autoload_classes" se define en modules.php).
spl_autoload_register('miframe_autoload_classes');