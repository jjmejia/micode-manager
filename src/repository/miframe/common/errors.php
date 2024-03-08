<?php
/**
 * Librería para registro de una función propietaria para manejo de errores y una mejor presentación de estos
 * mensajes en pantalla.
 *
 * @micode-uses miframe-common-functions
 * @micode-uses miframe-common-debug
 * @author John Mejia
 * @since Abril 2022
 */

 /**
 * Función para manejo de errores por defecto.
 * Cuando el nivel de error es E_ERROR o E_USER_ERROR, se termina la ejecución del programa.
 * Si el nivel de error no está incluido en la configuración de error_reporting (asignado en php.ini), no realiza acción alguna.
 * Basado en ejemplo tomado de: http://php.net/manual/en/function.set-error-handler.php
 * Para habilitar esta función como manejador de errores por defecto, use:
 *     set_error_handler('errors_handler_box');
 *
 * @param int $errno Nivel de error (Ej. E_USER_NOTICE, E_ERRPR, E_WARNING, etc.)
 * @param string $errstr Mensaje de error.
 * @param string $errfile Nombre del archivo donde se genera el error.
 * @param int $errline Número de la linea de $errfile donde se genera el error.
 * @param bool $return_message TRUE para no enviar el mensaje a pantalla sino para retornarlo como texto
 * 			(en caso que sea invocada la función directamente).
 */

function miframe_error_show(int $errno, string $errstr, string $errfile, int $errline, array $trace = null) {

	// Los errores nativos de core y muy criticos no son capturables
	$titulo = miframe_debug_error_code($errno);

	$cerrar = false;
	$estilo = 'info';

	switch ($errno) {

		case E_USER_ERROR: // PHP Fatal Error
			// $titulo .= 'User ';
		case E_ERROR : // Posiblemente fatal
			// $titulo .= 'PHP Fatal Error';
			$cerrar = true;
			$estilo = 'critical';
			break;

		case E_RECOVERABLE_ERROR : // Posiblemente fatal, pero recuperable
			// $titulo .= miframe_text('PHP Error Recuperable');
			$estilo = 'critical';
			break;

		case E_USER_WARNING:
			// $titulo .= 'User ';
		case E_WARNING: // PHP Warning
			// $titulo .= 'PHP Warning';
			$estilo = 'warning';
			break;

		case E_USER_NOTICE:
			// $titulo .= 'User ';
		case E_NOTICE: // PHP Notice
			// $titulo .= 'PHP Notice';
			break;

		case E_USER_DEPRECATED:
			// $titulo .= 'User ';
		case E_DEPRECATED:
			// $titulo .= 'PHP Deprecated';
			$estilo = 'mute';
			break;

		default:
			// $titulo = miframe_text('PHP Desconocido');
			$estilo = 'critical';
			$cerrar = true;
			break;
		}

	$infoerror = strip_tags($errstr);
	$salida = "$titulo [$errno]: $infoerror";
	if ($cerrar) {
		$salida .= ' - ' . miframe_text('Script interrumpido');
	}
	$salida .= " ($errfile línea $errline)";

	// GUarda al log de errores
	error_log($salida);

	if (miframe_is_web()) {
		// Salida web. Reconstruye $salida
		$infoerror = str_replace("\n", "<br />\n", strip_tags($errstr, '<b><i>'));
		if ($cerrar) {
			$titulo .= ' <div class="box-stop">Script interrumpido</div>';
		}
		// En modo convencional, evita mostrar todo el path del archivo actual
		$track_cadena = "Reportado en <b>" . basename($errfile) . "</b> Línea $errline";
		// Opcionalmente presenta usa rastreo completo si esta definido (en módulo interface/debug)
		if (miframe_is_debug_on()) {
			$track_cadena = miframe_debug_backtrace_info($trace);
			if ($cerrar && $errline > 0) {
				$infoerror .= miframe_debug_show_code($errfile, $errline, 3);
			}
		}
		$estilo .= ':error';
		$salida = miframe_box($titulo, $infoerror, $estilo, $track_cadena);
	}
	else {
		// Salida a pantalla
		$salida = PHP_EOL . PHP_EOL . '---' . PHP_EOL . $salida . PHP_EOL . '---' . PHP_EOL . PHP_EOL;
	}

	// Limpia registro de error
	error_clear_last();

	echo $salida;

	if ($cerrar) { exit(); }

	// Retorna TRUE para no ejecutar el manejador interno de errores de PHP.
	return true;
}

set_error_handler(
	function (int $errno, string $errstr, string $errfile, int $errline) {

		if (!(error_reporting() & $errno)) {
			// Este código de error no está incluido en error_reporting
			return;
		}

		miframe_error_show($errno, $errstr, $errfile, $errline);
	}
);

// El argumento puede ser del tipo "Exception" o "Error".
// set_exception_handler() maneja los throw y los Fatal Error.
set_exception_handler(
	function (\Throwable | \Exception $e) {

		$data = miframe_error_info($e);

		// Para diferenciar los errores de Excepciones, suma 1000 al valor
		miframe_error_show($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTrace());

		exit();
	}
);
