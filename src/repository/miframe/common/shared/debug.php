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

function miframe_is_vscode_on() {
	return (miframe_data_get('vscode-on') == true);
}

function miframe_vscode_enable(bool $value) {
	miframe_data_put('vscode-on', $value, true);
}

/**
 * Retorna texto con la secuencia de llamados actual (rastreo o backtrace) o uno capturado previamente.
 *
 * @param mixed $trace Backtrace obtenido previamente. Si no se indica, recupera el actual usando debug_backtrace().
 * @return string Texto con la secuencias de llamados
 */
function miframe_debug_backtrace_info(array $trace = null, string $function = '') {

	if (is_null($trace)) {
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	}

	$trace_cadena = '';
	// Retorna invocacion hacia atras
	$ignorar_primeras = true;
	foreach ($trace as $idebug => $infotrack) {
		if ($ignorar_primeras &&
			($infotrack['function'] == __FUNCTION__ || $infotrack['function'] == 'miframe_error_show')
		 	) {
			// print_r($infotrack); echo "<hr>";
			continue;
		}
		// Ya no ignora más primeras...
		$ignorar_primeras = false;
		if ($function != '' && $infotrack['function'] != $function) {
			// echo $function . ' / ' . $infotrack['function'] . '<hr>';
			continue;
		}
		if (isset($infotrack['file'])) {
			if ($trace_cadena != '') { $trace_cadena .= '<br />' . PHP_EOL; }
			$ultimo_track = miframe_text('Línea $1 - $2', $infotrack['line'], $infotrack['function']);
			if (!miframe_is_vscode_on()) {
				$trace_cadena .= '<b>' . $infotrack['file'] . '</b> ' . $ultimo_track;
			}
			else {
				// vscode://file/{full path to file}:line
				// https://stackoverflow.com/questions/48641921/is-it-possible-to-use-the-vscode-hyperlink-to-open-a-file-or-directory-in-code
				$trace_cadena .= "<a href=\"vscode://file/{$infotrack['file']}:{$infotrack['line']}\">{$infotrack['file']}</a> " . $ultimo_track;
			}
		}
	}

	return $trace_cadena;
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
		if (!miframe_is_web()) {
			$debug_message = var_export($data, true);
		}
		else {
			$debug_message = '<div class="miframe-debug">' . miframe_var_export($data) . '</div>';
		}
	}

	return $debug_message;
}

function miframe_var_export(mixed $data, bool $showtype = false) {

	$text = '';
	$total = 0;
	$pre = '';

	if (!isset($GLOBALS['miframe_var_export_count'])) {
		$GLOBALS['miframe_var_export_count'] = 0;
	}

	$GLOBALS['miframe_var_export_count'] ++;
	$id = 'mivex' . $GLOBALS['miframe_var_export_count'];

	if (!isset($GLOBALS['miframe_var_export_script'])) {
		$GLOBALS['miframe_var_export_script'] = true;
		$pre = '<script>function shvex(id) { if (document.getElementById(id).style.display != "block") { document.getElementById(id).style.display = "block"; } else { document.getElementById(id).style.display = "none"; } }</script>' . PHP_EOL;
	}

	$type = '';
	if ($showtype) {
		$type = '<i>(' . gettype($data) . ')</i> ';
		if (is_bool($data)) { $data = ($data === true) ? 'true' : 'false'; }
		elseif (is_string($data) && strlen($data) > 0) { $type = str_replace(')', ':' . strlen($data) . ')', $type); }
		elseif (is_array($data) && count($data) > 0) { $type = str_replace(')', ':' . count($data) . ')', $type); }
	}

	if (is_array($data)) {
		$borde = '';
		if (count($data) > 0) {

			// Limita cantidad máxima en caso que haya recursión
			if ($GLOBALS['miframe_var_export_count'] >= 500) {
				$text = '<span class="debug-error"><b>Aviso:</b> Suspende DEBUG porque alcanzó tope máximo de items a mostrar.</span>';
			}
			else {
				// Lista elementos
				foreach ($data as $k => $v) {
					$sub = '</td><td class="debug-item-value' . $borde . '">' . miframe_var_export($v, true);
					$text .= '<tr>' . PHP_EOL;
					$text .= '<td class="debug-item-name' . $borde . '" valign="top">' .
						'<b>' . $k . '</b>' .
						$sub .
						'</td>' . PHP_EOL;
					$text .= '</tr>' . PHP_EOL;
					$borde = ' debug-separador';
					$total ++;
				}

				$visible = ' debug-show';

				if ($id != 'mivex1' || $total > 3) {
					$pre .= '<span class="debug-button"><a href="javascript:shvex(\'' . $id . '\')">' .
						miframe_text('Mostrar/Ocultar') .
						'</a></span></td></tr>' . PHP_EOL .
						'<tr><td colspan="2">';
					$visible = '';
				}

				$text = $pre.
					'<table id="' . $id. '" border="0" cellspacing="0" class="debug-container' . $visible . '">' . PHP_EOL .
					$text .
					'</table>' . PHP_EOL;
			}
		}
		$text = $type . $text;
	}
	elseif (is_object($data)) {
		$text = $pre . '<pre style="font-size:12px;">' . var_export($data, true) . '</pre>';
	}
	else {
		$text = $type . nl2br(htmlspecialchars(wordwrap(trim($data), 75, PHP_EOL, true)));
	}

	return $text;
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
			$titulo = miframe_text('PHP Error') . " ($errno) ";
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