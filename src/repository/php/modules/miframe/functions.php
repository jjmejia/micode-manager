<?php
/**
 * Librería de funciones requeridas para las aplicaciones nativas de miFrame.
 *
 * @author John Mejia
 * @since Abril 2022
 */

/**
 * Evalua si el proyecto está embebido en "miCode/projects".
 * Para determinar esto, este script se ubica en los siguientes directorios (según su uso):
 * - admin: miCode/repository/php/modules/miframe
 * - local (en miCode): miCode/repository/php/modules/miframe
 * - local (paquete entregable): (proyecto)/micode/modules/miframe
 *
 * @return bool TRUE si está ejecutando en modo Desarrollo. FALSE en modo Producción.
 */
function is_workingcopy() {

	$dirlocal = strtolower(__DIR__);
	$patheval = DIRECTORY_SEPARATOR . miframe_path('repository', 'php', 'modules', 'miframe');
	return (substr($dirlocal, -strlen($patheval)) === $patheval);
}

/**
 * Evalua si está habilitado el modo debug.
 * El modo debug se habilita definiendo la constante "MIFRAME_DEBUG_ON" con valor TRUE.
 *
 * @return bool TRUE si está habilitado modo debug, FALSE en otro caso.
 */
function is_debug_on() {
	return (defined('MIFRAME_DEBUG_ON') && MIFRAME_DEBUG_ON === true);
}

/**
 * Evalua si está ejecutando desde un Web Browser.
 *
 * @return bool TRUE si está consultando por WEB, FALSE si es por consola (cli).
 */
function is_web() {

	return (isset($_SERVER["REMOTE_ADDR"]) && $_SERVER["REMOTE_ADDR"] != '');
}

/**
 * Genera mensaje de error y reporta al log de errores de PHP.
 *
 * @param string $message Mensaje de error.
 * @param mixed $debug_message Mensaje adicional a mostrar sólo en modo Debug. Si es un arreglo, mostrará su contenido.
 * @param bool $endscript TRUE termina la ejecucón del script, FALSE continúa.
 */
function miframe_error(string $message, mixed $debug_message = '', bool $endscript = true) {
	// throw new Exception("Value must be 1 or below");

	$tipo = E_USER_WARNING;
	if ($endscript) { $tipo = E_USER_ERROR; }
	if (is_array($debug_message)) { $debug_message = print_r($debug_message, true); }
	if (is_debug_on() && $debug_message != '') { $message .= ' - ' . $debug_message; }
	trigger_error($message, $tipo);
	// En caso que el manejador no termine la ejecución, valida de nuevo
	if ($endscript) { exit; }
}

/**
 * Cajas de diálogo en pantalla.
 * Cuando se ejecuta desde consola, remueve los tags HTML.
 * Puede personalizar la salida web a pantalla definiendo manualmente la función miframe_box_handler()
 * con los mismos parámetros de esta función. Debe retornar un texto HTML.
 *
 * @param string $title Título de la presentación.
 * @param string $message Mensaje a mostrar.
 * @param string $style Define el tema usado para mostrar la ventana (colores). Puede ser uno de los siguientes:
 * 			mute (estilo por defecto), info, warning, alert, critical, console.
 * @param string $footnote Texto a mostrar en la parte baja de la ventana.
 * @param bool $limited TRUE para restringir la altura de la ventana con la información (si el contenido es mayor se habilitan scrolls
 *			en la ventana para permitir su visualización), FALSE para presentar el contenido sin restricción de altura (sin scrolls).
 * @return string Texto HTML para consultas web, texto regular para consola.
 */
function miframe_box(string $title, string $message, string $style = '', string $footnote = '', bool $limited = true) {

	$salida = '';

	if (is_web()) {
		if (function_exists('miframe_box_handler')) {
			$salida = miframe_box_handler($title, $message, $style, $footnote, $limited);
		}
		else {
			$estilos = array(
				'alert' => 'red',
				'mute' => 'gray',
				'info' => 'blue',
				'warning' => 'brown',
				'critical' => 'darkred',
				'console' => 'black'
				);

			$color = 'gray';
			if (isset($estilos[$style])) { $color = $estilos[$style]; }

			$max_alto = 'max-height:200px;';
			if (!$limited) { $max_alto = ''; }

			if ($footnote != '') {
				$footnote = '<hr size="1"><small>' . $footnote . '</small></div>';
			}

			$salida = "<div style=\"font-family:Segoe UI, Arial;font-size:14px;border:2px solid $color;padding:10px;margin:10px 0\">".
				'<div><b>'.
				$title .
				'</b></div>'.
				'<div style="' . $max_alto . 'max-width:100%;overflow:auto">'.
				$message .
				$footnote .
				'</div>'.
				'</div>';
		}
	}
	else {
		// Salida por consola
		$message = strip_tags($message);
		$salida = "\n\n---\n$title\n$message\n---\n\n";
	}

	return $salida;
}

/**
 * Crea un path uniforme.
 * Evalua ".." y estandariza el separador de segmentos, usando DIRECTORY_SEPARATOR.
 * Basado en ejemplo encontrado en https://www.php.net/manual/en/dir.constants.php#114579
 * (Builds a file path with the appropriate directory separator).
 * Aviso: Por precaución es mejor no usar realpath(), especialmente en entornos Windows muy controlados.
 * Referencia: https://www.php.net/manual/en/function.realpath
 * > The running script must have executable permissions on all directories in the hierarchy, otherwise realpath() will return false.
 *
 * @param string $segments Segmentos del path a construir.
 * @return string Path
 */
function miframe_path(...$segments) {

    $path = join(DIRECTORY_SEPARATOR, $segments);
	// Confirma en caso que algun elemento de $segments contenga uncaracter errado
	if (DIRECTORY_SEPARATOR != '/') {
		$path = str_replace('/', DIRECTORY_SEPARATOR, $path);
	}
	// Remueve ".."
	if (strpos($path, '..') !== false) {
		$arreglo = explode(DIRECTORY_SEPARATOR, $path);
		$total = count($arreglo);
		for ($i = 0; $i < $total; $i++) {
			if ($arreglo[$i] == '..') {
				$arreglo[$i] = '';
				while ($arreglo[$i - 1] == '' && $i >= 0) { $i--; }
				if ($i > 0) {
					$arreglo[$i - 1] = '';
				}
			}
		}
		// Remueve elementos en blanco
		$path = implode(DIRECTORY_SEPARATOR, array_filter($arreglo));
	}

	return $path;
}

/**
 * Tomado de: https://www.php.net/manual/es/function.ini-get.php
 */
function miframe_bytes(string $val) {

    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);

	$valor = intval($val);

    switch($last) {
        case 'g':
            $valor *= 1024;
        case 'm':
            $valor *= 1024;
        case 'k':
            $valor *= 1024;
    }

    return $valor;
}

/**
 * Retorna la fecha de creación o modificación de un archivo o directorio, aquella que sea más antigua.
 * No siempre usa la fecha de creación porque puede pasar que al copiar un directorio o moverlo, la fecha de
 * creación sea más reciente que la de modificación.
 *
 * @param string $filename Nombre del archivo/directorio a validar.
 * @return string Fecha recuperada.
 */
function miframe_filecreationdate(string $filename) {

	$retornar = '';

	if (file_exists($filename)) {
		$fechamod = filemtime($filename);
		$fechacrea = filemtime($filename);
		if ($fechacrea > $fechamod) { $fechacrea = $fechamod; }
		$retornar = date('Y/m/d', $fechacrea);
	}

	return $retornar;
}

/**
 * Retorna el archivo, linea, función, etc. de donde se ejecutó esta función.
 * Hace uso de debug_backtrace() para recuperar la secuencia de invocaciones y toma el segundo elemento,
 * que corresponde al archivo solicitado. Ejemplo:
 * ```
 * 	[0] => (   // Esta función
 * 	       [file] => ...\micode\repository\php\modules\miframe\Router.php
 * 	       [function] => miframe_caller
 * 	       ...
 * 	       )
 * 	[1] => ( // Quien invoca miframe_caller()
 * 	       [file] => ...w\micode\repository\php\modules\miframe\MiProyecto.php
 * 	       [function] => __construct
 * 	       ...
 * 	       )
 * 	[2] => ( // El valor de interés
 * 	       [file] => ...\micode\core\admin\index.php
 * 	       ...
 * 	       )
 * ```
 */
function miframe_caller() {
	$track = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	return $track[2];
}