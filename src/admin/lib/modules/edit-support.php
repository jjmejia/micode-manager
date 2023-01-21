<?php
/**
 * Librería de funciones requeridas para gestión del sistema.
 *
 * @author John Mejia
 * @since Mayo 2022
 */

function micode_edit_module_types(string $text = '') {

	$listado = array();
	$validos = micode_modules_types();
	// Busca startups para cada tipo
	foreach ($validos as $type => $title) {
		if ($text != '') { $title .= ' - ' . $text; }
		$listado[$type] = $title;
	}
	ksort($listado);

	return $listado;
}

function micode_edit_startups() {

	$listado = micode_edit_module_types(miframe_text('Proyecto en blanco'));
	$data = micode_modules_startup_data();
	foreach ($data as $name => $info) {
		$type = $info['type'];
		if (isset($validos[$type])) {
			$listado[$type . '/' . $name] = $validos[$type] . ' - ' . $info['title'];
		}
	}
	ksort($listado);

	return $listado;
}

function micode_edit_template(string &$template, array $params) {

	// Valida si contiene {{ ... }}
	if (strpos($template, '{{') !== false) {
		$fun = function($matches) use ($params) {
			$name = strtolower(trim(str_replace(array('{{', '}}'), '', $matches[0])));
			$pos = strpos($name, ':');
			$cmd = '';
			if ($pos !== false) {
				$cmd = strtolower(trim(substr($name, $pos + 1)));
				$name = trim(substr($name, 0, $pos));
			}
			$valor = "{{ $name }}";
			if (isset($params[$name])) { $valor = $params[$name]; }
			// Valida si debe dar algun formato
			switch ($cmd) {
				case 'e':
					$valor = htmlspecialchars($valor);
					break;

				default:
			}
			// echo "TEMPLATE $name = $valor<hr>";
			return $valor;
			};
		$regexp = "/\{\{.*?\}\}/";
		$template = preg_replace_callback($regexp, $fun, $template);
	}
}

/*
function micode_edit_startups_paths($startup, $path, $proyecto_path, $startup_path, $path_modulos) {

	$pos = strpos($path, ':');
	$destino = miframe_path($proyecto_path, $path);
	if ($pos !== false) {
		$destino = trim(substr($path, $pos + 1));
		$destino = miframe_path($path_modulos, '..', $destino);
		$path = trim(substr($path, 0, $pos));
	}
	$origen = miframe_path($startup_path, $path);
	// Continua el proceso de validación
	if (!file_exists($origen)) {
		miframe_error('No existe archivo "$1" indicado en el modelo inicial "$2"', $origen, $startup);
	}

	return array('src' => $origen, 'dest' => $destino);
}
*/

function micode_edit_startups_files($startup, $startup_info, $data_proyecto, $path_modulos) {

	$startup_files = array();
	// Valida si definió el uso de alguna plantilla de inicio
	if (isset($startup_info['files'])) {
		// "files" puede crecer dependiendo de la configuración que encuentre
		// Cada elemento puede ser:
		// - [nombre archivo] Archivo a copiar en la raiz del proyecto
		// - [nombre archivo]:[Path destino incluido nombre final, con base en directorio "micode"]
		foreach ($startup_info['files'] as $k => $path) {
			$pos = strpos($path, '>');
			$destino = miframe_path($_SERVER['DOCUMENT_ROOT'], $data_proyecto['config']['path'], $path);
			if ($pos !== false) {
				$destino = trim(substr($path, $pos + 1));
				$destino = miframe_path($path_modulos, $destino);
				$path = trim(substr($path, 0, $pos));
			}
			$origen = miframe_path($startup_info['path'], $path);
			// Continua el proceso de validación
			if (!file_exists($origen)) {
				miframe_error('No existe archivo "$1" indicado en el modelo inicial "$2"', $origen, $startup);
			}

			// echo "$origen --> $destino<hr>";
			$startup_files[] = array('path' => $path, 'src' => $origen, 'dest' => $destino);
		}
	}

	return $startup_files;
}