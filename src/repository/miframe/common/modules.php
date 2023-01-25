<?php
/**
 * Librería de funciones para uso de los modulos en miFrame.
 *
 * @micode-uses miframe/common/functions
 * @micode-uses miframe/common/debug
 * @author John Mejia
 * @since Abril 2022.
 */

function miframe_get_proyecto_ini() {

	$data = array();
	// Miproyecto.ini es el archivo de proyecto, para uso exclusivo de "micode"?
	// No, no es exclusivo porque datos como el "project-title" podrían ser usados en el
	// proyecto local. Por tanto se almacena en "micode/config".
	$inifile = MIFRAME_LOCALCONFIG_PATH . '/miproyecto.ini';

	if (file_exists($inifile) && !defined('MIFRAME_MIPROYECTO_INI')) {
		define('MIFRAME_MIPROYECTO_INI', $inifile);
		// Recupera información de miproyecto.ini
		$d = parse_ini_file($inifile, false, INI_SCANNER_TYPED);
		// Items a no incluir en el entorno global (son informativos nada mas)
		$ignorar = array('project-desc-info', 'since', 'path', 'startup');
		foreach ($d as $k => $v) {
			$k = strtolower($k);
			if ($k == 'debug') {
				// Valores: false, true. Si no existe, asume el definido para el sistema.
				miframe_debug_enable($v != false);
			}
			elseif (!in_array($k, $ignorar) && $v != '' && !is_array($v)) {
				$data[$k] = trim($v);
				// Registra valores en $_SERVER para que puedan ser consultados donde quiera
				miframe_data_put($k, $data[$k]);
			}
		}
	}

	return $data;
}

// Previene se sobreescriban variables de la función que invoca
function miframe_include_module($modulo) {

	// Nota: No usa miframe_path() para poder usar esta función aunque no haya incluido functions.php
	$filename = MIFRAME_LOCALMODULES_PATH . DIRECTORY_SEPARATOR . $modulo;
	if (file_exists($filename)) {
		include_once $filename;
		return true;
	}

	// Si llega aquí es porque no encontró el archivo a incluir
	return false;
}
