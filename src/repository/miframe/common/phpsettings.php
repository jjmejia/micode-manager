<?php
/**
 * Utilidades para configuración y gestión del entorno PHP.
 *
 * @todo Validación de modulos requeridos en PHP como gettext, fileinfo, etc.
 *
 * @author John Mejia
 * @since Abril 2022
 */

/**
 * Define nombre a usar para el log de errores de PHP y controla su tamaño.
 * Se asigna un nombre diferente para consultas web y por consola (cli) para prevenir
 * que si el archivo se crea por ej. al ejecutar por cli, pueda tener restriccion de permisos
 * a los que no pueda acceder el usuario web y se genere error en PHP al intentar escribir
 * en el log son los suficientes permisos.
 * Usa el nombre dado al log de errores en PHP.ini (error_log) para obtener el directorio destino.
 * Si no existe, usa el directorio temporal del sistema.
 *
 * @param int $size_kb Tamaño en KB del log de errores. Si supera el valor lo renombra e inicia uno nuevo.
 * @param bool $is_web TRUE para consultas Web.
 */
function phpsettings_errorlog_rotate(int $size_kb = 0, string $sufix = '', string $dirname = '') {

	$size = $size_kb * 1024;
	$archivo = 'php-error.log';

	if ($dirname == '') {
		// Intenta recuperar el temporal del log actual
		$logactual = ini_get('error_log');
		if ($logactual == '') {
			// Dado que usa el path actual para determinar el directorio destino,
			// genera error si no se ha configurado ninguno.
			$dirname = sys_get_temp_dir();
		}
		else {
			// Recupera el directorio asociado
			$dirname = dirname($logactual);
			$archivo = basename($logactual);
		}
	}

	$extension = pathinfo($archivo, PATHINFO_EXTENSION);
	$archivo = pathinfo($archivo, PATHINFO_FILENAME);

	if ($sufix != '') { $archivo .= '-' . $sufix; }
	if ($extension != '') { $extension = '.' . $extension; }

	// Previene conflicto de permisos en los archivos (Windows puede asignar permisos diferentes según se ejecuten
	// por consola o Web la primera vez que se abre el archivo de errores).
	// if (!$is_web) { $archivo .= '-cli'; }

	$logactual = $dirname . DIRECTORY_SEPARATOR . $archivo . $extension;

	$inicializar = (file_exists($logactual) && $size > 0 && filesize($logactual) > $size);

	// Revisa tamaño del archivo de errores
	if ($inicializar) {
		@rename($logactual, $dirname . DIRECTORY_SEPARATOR . $archivo . '(old)' . $extension);
		}

	// Configura log de errores en PHP
	ini_set('error_log', $logactual);

	if (!file_exists($logactual)) {
		// Inicializa log de errores
		error_log('MIFRAME START ERRORLOG');
	}

	return $logactual;
}

/**
 * Configura visualización de errores en pantalla
 *
 * @param bool $show_all TRUE para visualizar todos los errores. FALSE solo los criticos.
 */
function phpsettings_errorlog_display(bool $show_all = false) {

	ini_set('html_errors', '1');

	if ($show_all) {
		ini_set('display_errors', '1');
		error_reporting(E_ALL);
	}
	else {
		ini_set('display_errors', '0');
		// Notificar todos los errores excepto E_NOTICE
		error_reporting(E_ALL ^ E_NOTICE);
	}
}

function phpsettings_timezones() {
	// Retorna arreglo con opciones de timezone

	$defecto = date_default_timezone_get();
	// $lista_zona = array();
	$lista_zona = array('' => miframe_text('Valor por defecto ($1)', $defecto));
	if (function_exists('date_default_timezone_set')) {
		// Timezones disponibles
		$timezone_identifiers = DateTimeZone::listIdentifiers();
		// http://www.php.net/manual/en/datetimezone.listidentifiers.php
		for ($i=0; $i < count($timezone_identifiers); $i++) {
			// echo "$timezone_identifiers[$i]<br>\n";
			$lista_zona[$timezone_identifiers[$i]] = str_replace('/', ' / ', $timezone_identifiers[$i]);
		}
	}

	return $lista_zona;
}

function phpsettings_charsets() {

	// Tomados de https://www.php.net/manual/en/function.htmlentities.php
	$charsets = array(
		'' => '',
		'ISO-8859-1' => 'Western European, Latin-1',
		'ISO-8859-5' => 'Latin/Cyrillic',
		'ISO-8859-15' => 'Western European, Latin-9',
		'UTF-8' => 'ASCII multi-byte 8-bit Unicode',
		'cp866' => 'DOS-specific Cyrillic',
		'cp1251' => 'Windows-specific Cyrillic',
		'cp1252' => 'Windows-specific for Western European',
		'KOI8-R' => 'Russian',
		'BIG5' => 'Traditional Chinese, mainly used in Taiwan',
		'GB2312' => 'Simplified Chinese',
		'BIG5-HKSCS' => 'Big5 Traditional Chinese',
		'Shift_JIS' => 'Japanese',
		'EUC-JP' => 'Japanese',
		'MacRoman' => 'Charset used by Mac OS (Obsoleto)'
		);

	// Adiciona el código a la descripción
	foreach ($charsets as $k => $v) {
		if ($k == '') {
			$defecto = ini_get('default_charset');
			$v = miframe_text('Valor por defecto');
			if ($defecto != '') {
				$v .= ' (' . $defecto . ')';
			}
			$charsets[$k] = $v;
		}
		else {
			$charsets[$k] = $k . ' - ' . $v;
		}
	}

	return $charsets;
}

function phpsettings_load() {

	// Valida tamaño configurado para error_log
	$errorlog_size_kb = miframe_text2bytes(miframe_data_get('php-errorlog-size', '10M')) / 1024;

	// Asocia el log al proyecto actual
	$sufijo = miframe_data_get('project-name');
	// Separa los logs Web/CLI
	if (!miframe_is_web()) {
		if ($sufijo != '') { $sufijo .= '-'; }
		$sufijo .= 'cli';
	}

	// Directorio temporal a usar para registro del log de errores
	$path_temp = miframe_temp_dir();

	// Mantenimiento del log de errores.
	$error_log = phpsettings_errorlog_rotate($errorlog_size_kb, $sufijo, $path_temp);

	// Habilita full despliegue de errores en modo Desarrollo
	phpsettings_errorlog_display(miframe_is_debug_on());

	// Setting adicionales del PHP
	$settings = array(
		'php-charset' => 'default_charset',
		'php-timezone' => 'date.timezone'
	);
	foreach ($settings as $name => $phpini) {
		$valor = miframe_data_get($name, '');
		if ($valor !== '') {
			ini_set($phpini, $valor);
		}
	}
}
