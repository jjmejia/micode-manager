<?php
/**
 * Si una petición de URI no especifica un fichero, entonces el index.php o index.html que estén en el directorio
 * dado serán devueltos. Si ninguno de los ficheros existen, la búsqueda de index.php e index.html continuará en
 * el directorio padre y continuará así hasta encontrar uno de ello o se alcance el directorio raíz. Si se encuentra
 * index.php o index.html, se devuelve este y $_SERVER['PATH_INFO'] se establece a la parte final del URI. De lo
 * contrario se devuelve un código de respuesta 404.
 *
 * NOTA: cURL no puede ser usado cuando se ejecuta el server built-in de PHP, asi como consultas a bases de datos
 * cuyo driver use "threads" para su ejecución (https://stackoverflow.com/questions/29173953/php-built-in-server-cant-curl).
 *
 * @author John Mejia
 * @since Septiembre 2023
 */

// Valida que este script se esté ejecutando desde el servidor interno
if (php_sapi_name() !== 'cli-server') {
    exit('Script no ejecutado desde el Servidor web interno de PHP (PHP Built-in web server)');
}

// Declara constante para identificar este script
define('MIFRAME_LOCAL_SERVER', php_sapi_name());

// Para compensar el problema del server de no ejecutar el index asociado si la URL tiene una parte
// inicial valida, se revisa el valor de "SERVER_URI" cuando "SCRIPT_FILENAME" toma el path del router
// (este archivo), lo que ocurre siempre que el enlace corresponde a un script o archivo que no existe.
if ($_SERVER['SCRIPT_FILENAME'] == __FILE__ && $_SERVER['REQUEST_URI'] != '') {
	// Se ubica en el directorio raíz
	chdir($_SERVER['DOCUMENT_ROOT']);
	// Busca hasta encontrar un "index.php" valido
	// Recupera la cadena sin parametros GET
	$dirname = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	while ($dirname != '') {

		// Habilite para no procesar si invoca archivos PHP
		if (strtolower(trim(substr($dirname, -4, 4))) === '.php') { break; }

		$dirname = dirname($dirname);
		if ($dirname == DIRECTORY_SEPARATOR) {
			// Llego al raiz
			$dirname = '';
		}
		$filename = '.' . $dirname . '/index.php';
		if (file_exists($filename)) {
			// Redefine valor de SCRIPT_FILENAME, SCRIPT_NAME y PATH_INFO
			// (PHP_SELF no es estándar en versiones recientes).
			$_SERVER['SCRIPT_NAME'] = substr($filename, 1);
			$_SERVER['SCRIPT_FILENAME'] = realpath($filename);
			$_SERVER['PATH_INFO'] = substr($_SERVER['REQUEST_URI'], strlen($dirname));
			// Indica que usa router
			$_SERVER['PHPROUTER_SCRIPT'] = __FILE__;
			// Se ubica en el directorio asociado
			chdir(dirname($_SERVER['SCRIPT_FILENAME']));
			// Reporta cambio al log de errores para seguimiento
			error_log("PHPROUTER {$_SERVER['REQUEST_URI']} --> {$_SERVER['SCRIPT_NAME']}");
			// Elimina variables usadas en este script para que no sean heredadas al script.
			unset($dirname);
			unset($filename);
			// Invoca archivo
			include $_SERVER['SCRIPT_FILENAME'];
			// Termina el script
			return;
		}
	}
}

// Hace que el servidor se ejecute normalmente.
return false;
