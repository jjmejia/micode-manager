<?php
/**
 * Librería mínima de funciones requeridas para las aplicaciones nativas de miFrame.
 *
 * @author John Mejia
 * @since Abril 2022
 */

include_once __DIR__ . '/debug.php';

/**
 * Evalua si está ejecutando desde un Web Browser.
 *
 * @return bool TRUE si está consultando por WEB, FALSE si es por consola (cli).
 */
function miframe_is_web() {

	return (miframe_server_get("REMOTE_ADDR") !== '' && miframe_data_get('miframe-noweb', false) === false);
}

function miframe_set_noweb(bool $noweb) {
	miframe_data_put('miframe-noweb', $noweb);
}

/**
 * Dispara una Excepción con el mensaje indicado y termina ejecución del script.
 * Si indica un argumento con nombre "debug" adicionará dicho mensaje sólo en modo DESARROLLO. En este caso,
 * si el mensaje es un arreglo, mostrará su contenido.
 *
 * @param string $message Mensaje de error.
 * @param mixed $args Argumentos de valores a remplazar en $message (ver miframe_text() para más detalles).
 */
function miframe_error(string $message, mixed ...$args) {

	$debug_message = '';
	if (isset($args['debug'])) {
		miframe_data_put('error-debug', miframe_debug_dump($args['debug']));
		unset($args['debug']);
	}
	miframe_data_put('error-backtrace', miframe_debug_backtrace_info());
	$message = miframe_text($message, ...$args);
	// if ($debug_message != '') { $message .= ' - DEBUG: ' . $debug_message; }
	throw new \Exception($message);
	// trigger_error($message, E_USER_ERROR);
	exit;
}

function miframe_error_info(mixed $e) {

	$titulo = 'PHP Exception';
	$code = $e->getCode();
	if ($code > 0) { $titulo .= " [" . $code . "]"; }
	// El backtrace reportado en este punto es inservible (OJO! Validar de nuevo - SEP/2022).
	// En caso que sea generado desde miframe_error(), almacena el trace y mensaje de depuración (si aplica)
	// Por defecto no muestra porque la exception no genera info realmente útil.
	$trace = $e->getTrace();
	// Adiciona el item actual (no listado en el trace())
	$arreglo = array('file' => $e->getFile(), 'line' => $e->getLine(), 'function' => '(Exception ' . get_class($e) . ')');
	array_unshift($trace, $arreglo);
	// print_r($e); echo "<hr>";
	$mensaje = $e->getMessage();
	$texto = strip_tags($mensaje) . ' (' . $trace[0]['file'] . " línea " . $trace[0]['line'] . ')';
	$track_cadena = "<b>" . basename($trace[0]['file']) . "</b> Línea " . $trace[0]['line'];

	if (miframe_is_debug_on()) {
		$infotrace = miframe_data_get('error-backtrace');
		// Valida en caso que no exista "error-backtrace"
		if ($infotrace == '') {
			$infotrace = miframe_debug_backtrace_info($trace);
		}
		if ($infotrace != '') {
			$track_cadena = $infotrace;
		}
	}

	$mensaje_debug = miframe_data_get('error-debug');
	if ($mensaje_debug != '') {
		$mensaje .= '<div style="margin-top:10px;border-top:1px solid #ccc;padding:10px 0;font-size:9pt;width:90%">' .
			'<b>DEBUG:</b> ' .
			$mensaje_debug .
			'</div>';
		$texto .= PHP_EOL . 'DEBUG: ' . $mensaje_debug;
	}

	return array('title' => $titulo, 'message' => $mensaje, 'trace' => $track_cadena, 'text' => $texto);
}

/**
 * Crea un path uniforme con los segmentos indicados.
 * Evalua ".." y estandariza el separador de segmentos, usando DIRECTORY_SEPARATOR.
 * Basado en ejemplo encontrado en https://www.php.net/manual/en/dir.constants.php#114579
 * (Builds a file path with the appropriate directory separator).
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
	if (file_exists($path)) {
		$path = realpath($path);
	}
	else {
		// Remueve ".." y "." sobre paths de archivos que no existen
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
				elseif ($arreglo[$i] == '.') {
					$arreglo[$i] = '';
				}
			}
			// Remueve elementos en blanco
			$path = implode(DIRECTORY_SEPARATOR, array_filter($arreglo));
		}
	}

	return $path;
}

/**
 * Retorna la extensión dada para el archivo indicado.
 * Basado en https://stackoverflow.com/questions/173868/how-to-get-a-files-extension-in-php
 *
 * @param string $filename Nombre del archivo a validar.
 * @return string extensión del archivo, en minúsculas e incluye el ".".
 */
function miframe_extension(string $filename) {

	$extension = pathinfo($filename, PATHINFO_EXTENSION);
	if ($extension != '') { $extension = '.' . strtolower($extension); }
	return $extension;
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
 * Recupera valor de elemento en la variable superglobal $_SERVER.
 *
 * @param string $name Nombre del elemento de $_SERVER a recuperar
 * @param string $default Valor por defecto a usar si el elemento no existe en $_SERVER.
 * @return string Valor del elemento.
 */
function miframe_server_get(string $name, string $default = '') {

	$retornar = $default;
	$name = strtoupper($name);
	if (isset($_SERVER[$name])) {
		$retornar = $_SERVER[$name];
	}

	return $retornar;
}

/**
 * Guarda valor en la variable global $_SERVER.
 *
 * @param string $name Nombre del elemento de $_SERVER a recuperar
 * @param string $value Valor a usar.
 */
function miframe_server_set(string $name, mixed $value) {

	$_SERVER[$name] = $value;
}

/*
function miframe_include_args(string $name, mixed &$value, bool $replace = false) {
	// Apunta a la posición de la variable en $value para no replicar contenido
	$llave = trim(strtolower($name));
	if ($llave != '') {
		if (!isset($GLOBALS['MIFRAMEARGS'])) { $GLOBALS['MIFRAMEARGS'] = array(); }
		if (!array_key_exists($name, $GLOBALS['MIFRAMEARGS']) || $replace) {
			// Lo guarda por referencia para no duplicar valores
			$GLOBALS['MIFRAMEARGS'][$name] =& $value;
		}
	}

	return $llave;
}

/**
 * Realiza la inclusión de un script si existe.
 * Valida si el archivo existe y luego extrae los valores de $args al contexto actual para su uso
 * en el script a incluir.
 *
 * @param string $filename Path del script a incluir.
 * @param mixed $args Arreglo con los nombres de variables globales a usar en el script.
 * @return bool TRUE si el script existe. FALSE en otro caso.
 */
/*
function miframe_include_file(string $filename, mixed $args = false) {

	if ($filename != '' && file_exists($filename)) {
		if (is_array($args) && isset($GLOBALS['MIFRAMEARGS'])) {
			foreach ($args as $k => $v) {
				if (array_key_exists($v, $GLOBALS['MIFRAMEARGS'])) {
					// Lo pasa por referencia para mantener una única copia
					$$v =& $GLOBALS['MIFRAMEARGS'][$v];
				}
			}
		}

		// Libera memoria
		unset($args);

		include_once $filename;

		return true;
	}

	return false;
}
*/

/**
 * Almacena valor para su uso global durante la ejecución actual.
 *
 * @param string $name Nombre de la variable a guardar.
 * @param string $value Valor de la variable. Si la variable ya fue asignada no la modifica, a menos
 *               que asigne $rewrite = true.
 * @param bool $rewrite TRUE para reescribir el valor almacenado si ya existe.
 */
function miframe_data_put(string $name, mixed $value, bool $rewrite = true) {

	if (!array_key_exists('MIFRAMEDATA', $GLOBALS)) { $GLOBALS['MIFRAMEDATA'] = array(); }
	$name = strtoupper(miframe_only_alphanum($name, '_'));
	if (!array_key_exists($name, $_SERVER) || $rewrite) {
		$GLOBALS['MIFRAMEDATA'][$name] = $value;
	}

	// Remueve elementos en blanco
	// if (array_key_exists($name, $_SERVER)
	// 	&& ($_SERVER[$name] === '' || $_SERVER[$name] === false)
	//  	) {
	// 	unset($_SERVER[$name]);
	// }
}

function miframe_data_put_array(array $data, array $ignorar = array()) {

	foreach ($data as $k => $v) {
		if ($k != '' && !in_array($k, $ignorar)) {
			if (!is_array($v)) {
				$v = trim($v);
			}
			// Registra valores en $_SERVER para que puedan ser consultados donde quiera
			miframe_data_put($k, $v);
		}
	}
}

/**
 * Recupera valor almacenado previamente usando miframe_data_put().
 * Los valores se guardan en la variable superglobal PHP $GLOBALS['MIFRAMEDATA'].
 * NOTA: $_SERVER está dentro de $GLOBALS.
 *
 * @param string $name Nombre de la variable a buscar.
 * @param string $default Valor a retornar si la variable no ha sido previamente registrada.
 * @return mixed Vaor de la variable.
 */
function miframe_data_get(string $name, string $default = '') {

	$retornar = $default;
	$name = strtoupper(miframe_only_alphanum($name, '_'));
	if (miframe_data_exists($name)) {
		$retornar = $GLOBALS['MIFRAMEDATA'][$name];
	}

	return $retornar;
}

/**
 * Valida si el nombre de variable indicado ha sido previamente registrado usando miframe_data_put().
 *
 * @param string $name Nombre de la variable a buscar.
 * @return bool TRUE si la vafriable ya fue registrada. FALSE en otro caso.
 */
function miframe_data_exists(string $name) {

	$name = strtoupper(miframe_only_alphanum($name, '_'));
	return (isset($GLOBALS['MIFRAMEDATA']) && array_key_exists($name, $GLOBALS['MIFRAMEDATA']));
}

/**
 * Registra función para uso global durante la ejecución actual.
 *
 * @param string $name Nombre para registro de la función.
 * @param callable $fun Función (puede ser un string con el nombre de una función valida o una función anónima)
 */
function miframe_data_fun(string $name, callable $fun) {

	$name = strtolower(trim($name));
	if ($name != '') {
		$GLOBALS['MIFRAMEDATAFUN'][$name] = $fun;
	}
}

/**
 * Ejecuta función global previamente registrada usando miframe_data_fun().
 *
 * @param string $name Nombre de la función a buscar.
 * @param mixed &$return Valor retornado por la función.
 * @param mixed $args Argumentos de la función.
 * @return bool TRUE si la función solicitada existe y fue ejecutada (haya o no retornado algún
 *         valor). FALSE en otro caso.
 */
function miframe_data_call(string $fun, mixed &$return, ...$args) {

	$name = strtolower(trim($fun));
	if ($name != ''
 		&& isset($GLOBALS['MIFRAMEDATAFUN'])
		&& isset($GLOBALS['MIFRAMEDATAFUN'][$name])
		) {
		$return = call_user_func($GLOBALS['MIFRAMEDATAFUN'][$name], ...$args);
		return true;
	}
	elseif ($fun != '' && function_exists($fun)) {
		$return = call_user_func($fun, ...$args);
		return true;
	}

	return false;
}

/**
 * Retorna directorio temporal a usar.
 * Si indica $subdir, retorna path completo de $subdir dentro del directorio temporal.
 * EL directorio se recupera de:
 * - Valor registrado en "temp-path" usando previamente miframe_data_put().
 * - Directorio donde se almacena el registro de errores de PHP (error_log).
 * - Directorio temporal del sistema.
 *
 * @param string $subdir Opcional. Directorio deseado dentro del temporal.
 * @param bool $create_dir Crear directorio $subdir si no existe (el directorio temporal base debe existir).
 * @return string Path
 */
function miframe_temp_dir(string $subdir = '', bool $create_dir = false) {

	// Recupera directorio temporal
	$dir = miframe_data_get('temp-path');
	$existedata = ($dir != '');
	if (!$existedata) {
		// Intenta recuperarlo del path asociado al log de errores de PHP (puede no existir si no está bien configurado)
		$dir = dirname(ini_get('error_log'));
		if ($dir == '') {
			// Lo asocia al directorio temporal asignado por el S.O.
			$dir = sys_get_temp_dir();
		}
	}
	// El directorio temporal base DEBE existir en el servidor, no debe ser creado
	if ($dir == '' || !is_dir($dir)) {
		// error_log('No pudo obtener directorio Temporal o no existe ' . $dir);
		miframe_error('No pudo obtener directorio Temporal o no existe', debug: $dir);
	}
	// Asocia directorio temporal si existe para agilizar validaciones
	if (!$existedata) {
		miframe_data_put('path-temp', realpath($dir));
	}
	if ($subdir != '') {
		// No permite el uso de ".." en $subdir para no mover la creación del directorio a otro lado
		$dir = miframe_path($dir, str_replace('..', '', $subdir));
		if (!is_dir($dir) && $create_dir) {
			if (!@mkdir($dir, 0777, true)) {
				miframe_error('No pudo crear subdirectorio Temporal requerido: $1', $subdir);
			}
		}
	}

	return $dir;
}

function miframe_text(string $text, mixed ...$args) {

	// Valida si debe reprocesar el texto para traducciones (los valores de $args no se traducen)
	if (!miframe_data_call('miframe-traslate', $texto, $text)) {
		$texto = $text;
	}

	// Recomendaciones Markdown:
	// Bold 		**bold text**		__bold text__		strong
	// Italic 		*italicized text*	_italicized text_	em
	// Bold+Italic 	***bold text***		___bold text___		em-strong
	// Code 		`code`									code
	// Enlaces		[titulo](enlace)
	// Escaping with "\"
	// Nota: Los "*" son preferibles a "_"
	// https://www.markdownguide.org/basic-syntax/

	// https://github.com/jbroadway/slimdown/blob/master/Slimdown.php
	$patrones = array(
		'/(\*\*|__)(\S.*?)\1/'	=> '<strong>\2</strong>',		// bold
		'/(\*|_)(\S.*?)\1/' 	=> '<em>\2</em>',				// emphasis
		'/`(\S.*?)`/' 			=> '<code>\1</code>',			// inline code
		'/\[http(\S.*?)\]/' 			=> '<a href="http\1" target="_blank">http\1</a>',			// enlaces
		// '/\[([^\[]+)\]\(([^\)]+)\)/' => '<a href=\'\2\'>\1</a>',            // links
		);

	foreach ($patrones as $p => $s) {
		$texto = preg_replace($p, $s, $texto);
	}

	// BUG POR REVISAR: Si contiene variable $PHP_DATE_TIME lo convierte a $PHP<em>DATE</em>TIME

	// Sugerencias manejo multiples idiomas:
	// Soporte: https://poedit.net/
	// https://phptherightway.com/#i18n_l10n

	$llaves = array();
	foreach ($args as $k => $v) {
		if (is_numeric($k)) {
			$llaves[$k] = '$' . ($k + 1);
		}
		else {
			$llaves[$k] = '$' . $k;
		}
	}

	$texto = str_replace($llaves, $args, $texto);

	// Opcional: https://www.php.net/manual/es/function.strtr.php
	// $texto = strtr($texto, $args);

	return $texto;
}

// https://alvinalexander.com/php/php-string-strip-characters-whitespace-numbers/
function miframe_only_alphanum(string $string, string $replace = '', string $allowed_cars = '') {

	// Cómo evitar que "/", "-", "[", "]" y otros usados por preg_replace() sean usados y generen conflicto?
	// Usualmente se usaría \- pero no estoy seguro de cómo implementarlo apropiadamente. Sorry...
	$allowed_cars = 'a-zA-Z0-9'. $allowed_cars;
	return preg_replace("/[^$allowed_cars]/", $replace, $string);
}

/**
 * Enmascara texto.
 * Si el texto es menor a 16 caracteres, lo convierte directamente. Si es mayor, genera un md5 como base.
 * No usar para encriptar texto sensible.
 *
 * @param string $text Texto a enmascarar.
 * @param string $prefix Prefijo a adicionar al texto enmascarado.
 * @param bool $force_md5 Forza el uso de md5 aunque el texto tenga menos de 16 caracteres.
 * @return string Texto
 */
function miframe_mask(string $text, string $prefix = '', bool $force_md5 = false) {

	$text = trim($text);
	$len = strlen($text);
	// echo "PRE $text";
	if ($len > 0) {
		// https://stackoverflow.com/questions/959957/php-short-hash-like-url-shortening-websites
		// Base 36 retorna una cadena algo mas corta que la de md5()
		// Si el nombre es menor a 16, convierte directamente el hexadecimal. Si es mayor, usa md5
		// Ej: project-title = nzqlna07fmokg440o08o (13 => 20 caracteres)
		if ($len <= 10 && is_numeric($text)) {
			$text = dechex(intval($text)); // 0..8 carácteres
			// echo " / POSDEC $text";
		}
		elseif ($len < 16) {
			$text = bin2hex($text); // 0..30 carácteres
			// echo " / POSBIN $text";
		}
		else {
			$text = md5($text); // Siempre 32 carácteres
			// echo " / POSMD5 $text";
		}

		// base_convert() se revienta con hexadecimales de mas de 12 caracteres.
		// Ej: project-name --bin2hex--> 70726f6a6563742d6e616d65 --base_convert--> 3dgof33ejmw4ss0o0ko
		// Pero al recuperar valor, retorna: 70726f6a65637c0000000000
		// Esto significa que $text con inicio similar tendrán valores similares. Ej:
		// * project-name / POSBIN 70726f6a6563742d6e616d65 / FINAL cfg3dgof33ejmw4ss0o0ko
		// * project-desc / POSBIN 70726f6a6563742d64657363 / FINAL cfg3dgof33ejmw4ss0o0ko
		// * project-path / POSBIN 70726f6a6563742d70617468 / FINAL cfg3dgof33ejmw4ss0o0ko
		// Por ello se convierte en bloques de 12 caracteres.

		$nuevo = '';
		while ($text != '') {
			$nuevo .= base_convert(substr($text, 0, 12), 16, 36);
			$text = substr($text, 12);
		}

		$text = $prefix . $nuevo; // 0..9,a..z
		// echo " / FINAL $text<hr>";
	}

	return $text;
}

function miframe_class_load($class, ...$args) {

	$clase_manejador = false;
	if (class_exists($class)) {
		$clase_manejador = new $class(...$args);
	}

	return $clase_manejador;
}

/**
 * Convierte valor en bytes a un texto con formato.
 * Ej. 1024 se convierte en 1K o 1KB, este último cuando $fullsufix = true.
 *
 * @param mixed $size Tamaño en bytes a dar formato.
 * @param bool $fullsufix TRUE retorna "KB", "MB" o "GB" como sufijo. De lo contrario retorna "K", "M" o "G".
 * @return string Texto con formato
 */
function miframe_bytes2text(mixed $size, bool $fullsufix = false) {

	$num = 0;
	if (is_numeric($size) && $size != 0) {
		$tipos = array('', 'K', 'M', 'G');
		if ($fullsufix) { $tipos = array(' bytes', ' KB', ' MB', ' GB'); }
		$ciclos = -1;
		do {
			$num = $size;
			$ciclos ++;
			$size = ($size / 1024);
		} while ($size >= 1 && isset($tipos[$ciclos]));

		// Da formato al numero final
		$num = str_replace('.00', '', number_format($num, 2)) . $tipos[$ciclos];
	}

	return $num;
}

function iso2utf8(string $contenido) {

	// Valida si debe decodificar el código
	$eval_code = array('á', 'é', 'í', 'ó', 'ú', 'ñ', 'Ñ');
	foreach ($eval_code as $car) {
		$car8 = iconv("UTF-8", "ISO-8859-1//IGNORE", $car);
		if (strpos($contenido, $car8) !== false) {
			// Encontrado, decodifica todo el texto
			$contenido = iconv("ISO-8859-1", "UTF-8//IGNORE", $contenido);
			break;
		}
	}

	return $contenido;
}
