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

function miframe_box_css(string $html) {

	if (strtolower(substr($html, 0, 4)) === 'url:') {
		$path = trim(substr($html, 4));
		$html = '';
		if ($path != '') {
			$html = '<link rel="stylesheet" href="' . $path . '">' . PHP_EOL;
		}
	}
	elseif ($html !== '') {
		$html = '<style>' . PHP_EOL . $html . PHP_EOL . '</style>' . PHP_EOL;
	}

	miframe_data_put('miframe-box-css', $html);
}

/**
 * Cajas de diálogo en pantalla.
 * Cuando se ejecuta desde consola, remueve los tags HTML.
 * Puede personalizar la salida web a pantalla modificando los estilos usados.
 *
 * @param string $title Título de la presentación.
 * @param string $message Mensaje a mostrar.
 * @param string $style Define el tema usado para mostrar la ventana (colores). Puede ser uno de los siguientes:
 * 			mute (estilo por defecto), info, warning, alert, critical, console.
 * @param string $footnote Texto a mostrar en la parte baja de la ventana.
 * @return string Texto HTML para consultas web, texto regular para consola.
 */
function miframe_box(string $title, string $message, string $style = '', string $footnote = '') {

	$salida = '';
	// $showscrolls = true;
	// * @param bool $showscrolls TRUE para restringir la altura de la ventana con la información (si el contenido es mayor se habilitan scrolls
	// *			en la ventana para permitir su visualización), FALSE para presentar el contenido sin restricción de altura (sin scrolls).

	$fecha = date('Y/m/d H:i:s');

	if (miframe_is_web()) {

		// Definición de la ventana a usar por defecto si no se personaliza
		/*
		$estilos = array(
			'alert'		=> 'red',
			'mute'		=> 'gray',
			'info'		=> 'blue',
			'warning'	=> 'brown',
			'critical'	=> 'darkred',
			'console'	=> 'black'
			);
		*/
		$max_alto = ' box-message-limited';
		// if (!$showscrolls) { $max_alto = ''; }

		if ($footnote != '') {
			$footnote = "<div class=\"box-footnote box-$style\">$footnote</div>";
			}

		// $fecha = date('Y/m/d H:i:s');
		if ($title == '') { $title = '. . .'; }

		$salida = miframe_data_get('miframe-box-css', '?');
		if ($salida == '?') {
			// No se encontró valor definido, lee archivo css
			$html = file_get_contents(__DIR__ . '/framebox.css');
			$salida = '<style>' . PHP_EOL . $html . PHP_EOL . '</style>' . PHP_EOL;
		}

		// Elimina estilos para no repetir carga
		miframe_box_css('');

		$salida .= "<div class=\"miframe-box box-$style\">" .
			'<div class="box-title"><b>' . $title . '</b></div>' .
			'<div class="box-message' . $max_alto . '">'.
			$message .
			'</div>'.
			$footnote .
			// '<div class="box-date">' . $fecha . '</div>' .
			'</div>';
	}
	else {
		// Salida por consola
		$message = strip_tags($message);
		$salida = "\n\n---\n$title\n$message\n---\n\n";
	}

	return $salida;
}

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
