<?php
/**
 * Librería de funciones para uso de los modulos en miFrame.
 *
 * @micode-uses miframe-common-functions
 * @micode-uses miframe-common-debug
 * @micode-uses-optional miframe-common-phpsettings
 * @author John Mejia
 * @since Abril 2022.
 */

function miframe_get_proyecto_ini() {

	$data = array();
	// Miproyecto.ini es el archivo de proyecto, para uso exclusivo de "micode"?
	// No, no es exclusivo porque datos como el "project-title" podrían ser usados en el
	// proyecto local. Por tanto se almacena en "micode/config".
	$inifile = MIFRAME_LOCALCONFIG_PATH . DIRECTORY_SEPARATOR . 'miproyecto.ini';

	if (file_exists($inifile) && !defined('MIFRAME_MIPROYECTO_INI')) {
		define('MIFRAME_MIPROYECTO_INI', $inifile);
		// Recupera información de miproyecto.ini
		$data = parse_ini_file($inifile, false, INI_SCANNER_TYPED);
		if (is_array($data)) {
			$data = array_change_key_case($data, CASE_LOWER);
			// Items a no incluir en el entorno global (son informativos nada mas)
			$ignorar = array('project-desc-info', 'since', 'path', 'startup', 'debug', 'vscode');
			if (array_key_exists('debug', $data)) {
				// Valores: false, true. Si no existe, asume el definido para el sistema.
				miframe_debug_enable(boolval($data['debug']) !== false);
			}
			if (array_key_exists('vscode', $data)) {
				// Valores: false, true. Si no existe, asume el definido para el sistema.
				miframe_vscode_enable(boolval($data['vscode']) !== false);
			}
			// Guarda información
			miframe_data_put_array($data, $ignorar);
		}
		else {
			// Garantiza retorno de arreglo
			$data = array();
		}
	}

	return $data;
}

function miframe_get_sistema_ini() { // micode_modules_sistema_ini

	$data = array();

	// Lee archivo de configuración de sistema (complementa miproyecto.ini)
	$filename = MIFRAME_LOCALCONFIG_PATH . '/sistema.ini';

	if (file_exists($filename) && !defined('MIFRAME_SISTEMA_INI')) {
		define('MIFRAME_SISTEMA_INI', $filename);
		// Carga datos registrados en el archivo .ini
		$data = parse_ini_file($filename, false, INI_SCANNER_TYPED);
		if (is_array($data) && count($data) > 0) {
			$data = array_change_key_case($data, CASE_LOWER);
			// Guarda información
			miframe_data_put_array($data);
		}
		else {
			// Garantiza retorno de arreglo
			$data = array();
		}
	}

	return $data;
}

// Carga datos de proyecto.ini (propios de miCode Manager)
miframe_get_proyecto_ini();

// Carga datos de sistema.ini (propios de cada proyecto)
miframe_get_sistema_ini();

// Luego de cargar atributos, inicializa valores PHP (log errores, etc.)
if (function_exists('phpsettings_load')) {
	phpsettings_load();
}
