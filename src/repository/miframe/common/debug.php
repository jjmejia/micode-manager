<?php
/**
 * Librería de funciones para mensajes de depuración en pantalla (web o CLI).
 * Estas funciones en general se usan para presentar información de depuración en pantalla. Para que funcionen se
 * requiere se haya definido externamente una constante "MIFRAME_DEBUG_ON" con valor TRUE.
 *
 * @micode-uses miframe-common-shared
 * @author John Mejia
 * @since Abril 2022
 */

// Funciones debug compartidas con otras aplicaciones
include_once __DIR__ . '/shared/debug.php';

/**
 * Presenta en pantalla una ventana HTML con la descripción (contenido) de la expresión indicada.
 * Enmascara tags HTML incluidos en la expresión para prevenir comportamientos no deseados.
 *
 * @param mixed $expression Variable con el contenido que se quiere mostrar en pantalla.
 * @param string $title Título para la presentación.
 * @param int $ignore_first Indica cuantos elementos del trace ignorar. El primer elemento corresponde a este archivo.
 * @param bool $limited TRUE para restringir la altura de la ventana con la información (si el contenido es mayor se habilitan scrolls
 *			en la ventana para permitir su visualización), FALSE para presentar el contenido sin restricción de altura (sin scrolls).
 * @param bool $force TRUE habilita uso aunque miframe_is_debug_on() retorne false.
 */
function miframe_debug_box(mixed $expression, string $title = '', bool $limited = true, bool $force = false) {

	if ($force || miframe_is_debug_on()) {
		$title = trim("DEBUG $title");
		$track_cadena = miframe_debug_backtrace_info();
		$salida = miframe_debug_dump($expression, true);

		echo miframe_box($title, $salida, 'mute', $track_cadena, $limited);
	}
}

function debug_warning(string $text, string $function) {
	$track_cadena = miframe_debug_backtrace_info(null, $function);
	echo miframe_box($text, '', 'mute', $track_cadena);
}
/**
 * Despliega contenido de las variables $_REQUEST (diferenciando si se recibieron a través de POST o GET),
 * argumentos al ejecutar por consola y registro en $_FILES, si existen.
 */
function miframe_debug_request_box() {

	$metodo = miframe_server_get('REQUEST_METHOD');
	$request = array('METHOD' => $metodo);

	if (count($_POST) > 0) {
		$request['POST'] = &$_POST;
	}
	if (count($_GET) > 0) {
		$request['GET'] = &$_GET;
	}
	// Obtiene valores de $_REQUEST que no esten en POST ni GET
	$request_diff = array_diff_key($_REQUEST, $_POST);
	$request_diff = array_diff_key($request_diff, $_GET);
	if (count($request_diff) > 0) {
		$request['*'] = &$request_diff;
	}
	global $argv, $argc;
	if (isset($argv) && count($argv) > 0) {
		$request['CLI (' . $argc . ')'] = &$argv;
	}
	if (count($_FILES) > 0) {
		$request['FILES'] = &$_FILES;
	}

	miframe_debug_box($request, 'REQUEST');
}

/**
 * Opciones a usar al editar parámetro debug en .inis
 * Se define aquí por si se usa el editor de configuraciones en el cliente (configdata)
 */
function miframe_debug_config_options() {

	// $estado_actual = 0;
	// if (miframe_is_debug_on()) { $estado_actual = 1; }
	$opciones = array(
		1 => miframe_text('Habilitado'),
		0 => miframe_text('No habilitado')
	);
	// Opción de sistema
	// $opciones[''] = miframe_text('Mismo del sistema ($1)', strtolower($opciones[$estado_actual]));

	return $opciones;
}

function miframe_debug_data_box() {

	if (isset($GLOBALS['MIFRAMEDATA'])) {
		miframe_debug_box($GLOBALS['MIFRAMEDATA'], 'MIFRAMEDATA');
	}
}

function miframe_debug_pause(string $message = '') {

	$title = 'SCRIPT PAUSE';
	$track_cadena = miframe_debug_backtrace_info();
	exit(miframe_box($title, $message, 'mute', $track_cadena));
}

function miframe_debug_defines($full = false) {

	$titulo = 'All';
	$defines = get_defined_constants(true);
	if (!$full && isset($defines['user'])) {
		$defines = $defines['user'];
		$titulo = 'User';
	}
	miframe_debug_box($defines, 'DEFINES ' . $titulo);
}

function miframe_debug_show_code(string $filename, int $line = 0, int $maxlines = 3, bool $formatted = false) {

	$contents = '';
	$filename = trim($filename);
	if ($filename !== '' && is_file($filename)) {
		// Remplaza tabs por 4 espacios
		$contents = str_replace("\t", '    ', file_get_contents($filename));

		// Titulo del archivo
		$filename_info = '';
		$total_lineas = substr_count($contents, "\n") + 1;
		// Adiciona información del archivo
		if ($formatted) {
			$filename_info = '<b>' . $filename . '</b>';
		}
		else {
			$filename_info = $filename . "\n" . str_repeat('_', strlen($filename));
		}
		$filename_info .= ' (' . miframe_text('$1 Líneas', $total_lineas) . ')' . PHP_EOL;

		if ($line > 0) {
			// $line=1 equivale a $line[0]
			if ($line <= $total_lineas) {
				$lines = explode("\n", $contents);
				$contents = '';
				for ($i = $line - $maxlines - 1; $i < $line + $maxlines; $i++) {
					if (array_key_exists($i, $lines)) {
						$line_info = ($i + 1);
						$line_data = '';
						if ($formatted) {
							if ($contents != '') { $contents .= '<br />' . PHP_EOL; }
							$line_data = "<span class=\"debug-line\">" . $line_info . "</span>" .
								// Previene supresión de espacios en blanco a la izquierda
								str_replace(' ', '&nbsp;', rtrim($lines[$i]));
							if (($i + 1) == $line) {
								$line_data = "<span class=\"debug-line-selected\">{$line_data}</span>";
							}
						}
						else {
							// if ($contents != '') { $contents .= PHP_EOL; }
							$line_data = PHP_EOL . $line_info . "    ";
							if (($i + 1) == $line) {
								$line_data = $line_info . " >> ";
							}
							// Previene supere la longitud de una linea de consola
							$line_data .= substr(trim($lines[$i]), 0, 75 - strlen($line_data));
						}
						$contents .= $line_data;
					}
				}
			}
			else {
				$contents = miframe_text('**Error:** No existe la línea solicitada ($1).', $line);
				// Prevent html formatting
				// $formatted = false;
			}
		}
	}
	elseif ($filename !== '') {
		$contents = miframe_text('**Error**: No existe el archivo $1', $filename);
		// Prevent html formatting
		// $formatted = false;
	}

	if ($formatted) {
		// Add html format
		$contents = '<div class="miframe-debug-code"><div class="debug-title">' . $filename_info . '</div>' . $contents . '</div>';
	}
	else {
		$contents = $filename_info . $contents . PHP_EOL;
	}

	return $contents;
}
