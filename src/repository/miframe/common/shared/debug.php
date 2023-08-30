<?php
/**
 * Librería mínima de funciones para mensajes de depuración en pantalla (web o CLI).
 *
 * @author John Mejia
 * @since Abril 2022
 */

/**
 * Evalua si está habilitado el modo debug.
 * El modo debug se habilita definiendo la constante "MIFRAME_DEBUG_ON" con valor TRUE.
 *
 * @return bool TRUE si está habilitado modo debug, FALSE en otro caso.
 */
function miframe_is_debug_on() {
	return (miframe_data_get('debug-on') == true);
}

/**
 * Habilita modo debug.
 *
 * @param bool $value TRUE para hablitar modo debug, FALSE lo deshabilita.
 */
function miframe_debug_enable(bool $value) {
	miframe_data_put('debug-on', $value, true);
}

/**
 * Retorna texto con la secuencia de llamados actual (rastreo o backtrace) o uno capturado previamente.
 *
 * @param mixed $track Backtrace obtenido previamente. Si no se indica, recupera el actual usando debug_backtrace().
 * @return string Texto con la secuencias de llamados
 */
function miframe_debug_backtrace_info(array $track = null, string $function = '') {

	if (is_null($track)) {
		$track = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	}

	$track_cadena = '';
	// Retorna invocacion hacia atras
	$ultimo_track = '';
	foreach ($track as $idebug => $infotrack) {
		if ($infotrack['function'] == __FUNCTION__
			// Ignora si es miframe_error() invocado desde functions.php?
			// || ($infotrack['function'] == 'miframe_error' && basename($infotrack['file']) == 'functions.php')
			) {
			// print_r($infotrack); echo "<hr>";
			continue;
		}
		if ($function != '' && $infotrack['function'] != $function) {
			// echo $function . ' / ' . $infotrack['function'] . '<hr>';
			continue;
		}
		if (isset($infotrack['file'])) {
			if ($track_cadena != '') { $track_cadena .= '<br />' . PHP_EOL; }
			$ultimo_track = miframe_text('**$1** Línea $2 - $function', $infotrack['file'], $infotrack['line'], function: $infotrack['function']);
			$track_cadena .= $ultimo_track;
		}
	}

	return $track_cadena;
}

/**
 * Retorna contenido de una variable en formato texto para visualización.
 *
 * @param mixed $data Variable a mostrar.
 * @param bool $force TRUE habilita uso aunque miframe_is_debug_on() retorne false.
 * @return string Contenido de la variable en formato texto.
 */
function miframe_debug_dump(mixed $data, bool $force = false) {

	$debug_message = '';
	if ($force || miframe_is_debug_on()) {
		if (!is_string($data) && !is_numeric($data)) {
			$debug_message = print_r($data, true);
		}
		else {
			$debug_message = $data;
		}
	}

	return $debug_message;
}

function miframe_debug_error_code($errno) {

	$titulo = '';

	switch ($errno) {

		case E_USER_ERROR: // PHP Fatal Error
			$titulo .= 'User ';
		case E_ERROR : // Posiblemente fatal
			$titulo .= miframe_text('PHP Error Fatal');
			break;

		case E_RECOVERABLE_ERROR : // Posiblemente fatal, pero recuperable
			$titulo .= miframe_text('PHP Error Recuperable');
			break;

		case E_USER_WARNING:
			$titulo .= 'User ';
		case E_WARNING: // PHP Warning
			$titulo .= miframe_text('PHP Aviso');
			break;

		case E_USER_NOTICE:
			$titulo .= 'User ';
		case E_NOTICE: // PHP Notice
			$titulo .= miframe_text('PHP Novedad');
			break;

		case E_USER_DEPRECATED:
			$titulo .= 'User ';
		case E_DEPRECATED: // PHP Deprecated
			$titulo .= miframe_text('PHP Obsoleto');
			break;

		default:
			$titulo = miframe_text('PHP Error Desconocido') . " ($errno)";
			break;
	}

	return $titulo;
}

function miframe_debug_uploads_error_code() {

	/*
	https://www.php.net/manual/en/features.file-upload.errors.php#115746
	$phpFileUploadErrors = array(
    0 => 'There is no error, the file uploaded with success',
    1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
    3 => 'The uploaded file was only partially uploaded',
    4 => 'No file was uploaded',
    6 => 'Missing a temporary folder',
    7 => 'Failed to write file to disk.',
    8 => 'A PHP extension stopped the file upload.',
);
	*/
}