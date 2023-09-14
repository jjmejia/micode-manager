<?php
/**
 * Enrutador para uso del Servidor web interno de PHP.
 *
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
if (php_sapi_name() !== 'cli-server' || !isset($_SERVER['SERVER_SOFTWARE'])) {
    exit('Script no ejecutado desde el Servidor web interno de PHP (PHP Built-in web server)');
}

// "SCRIPT_FILENAME" toma el valor del router cuando hay una coincidencia parcial con el valor
// en "REQUEST_URI", por ejemplo si REQUEST_URI = "a/b/c" y solo existe "a/b" pero no existe un
// "index.php" o "index.html" en "a/b".
// NOTA: Si se invoca este script desde Web sobre el mismo servidor PHP, no lo ejecuta.
if ($_SERVER['SCRIPT_FILENAME'] == __FILE__ && $_SERVER['REQUEST_URI'] != '') {
	// Se ubica en el directorio raíz
	chdir($_SERVER['DOCUMENT_ROOT']);
	// Invoca siempre el index.php
	$_SERVER['SCRIPT_FILENAME'] = realpath($_SERVER['DOCUMENT_ROOT'] . '/index.php');
	if (file_exists($_SERVER['SCRIPT_FILENAME'])) {
		// Redefine valor de SCRIPT_FILENAME, SCRIPT_NAME y PATH_INFO
		// (PHP_SELF no es estándar en versiones recientes).
		$_SERVER['SCRIPT_NAME'] = '/' . basename($_SERVER['SCRIPT_FILENAME']);
		// Indica que usa router
		$_SERVER['PHPROUTER_SCRIPT'] = __FILE__;
		// Reporta cambio al log de errores para seguimiento
		error_log("PHPROUTER {$_SERVER['REQUEST_URI']} --> {$_SERVER['SCRIPT_NAME']}");
		// Invoca archivo
		include $_SERVER['SCRIPT_FILENAME'];
		// Termina el script
		return;
	}
}

// Hace que el servidor se ejecute normalmente.
return false;
