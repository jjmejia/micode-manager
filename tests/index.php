<?php
/**
 * miCode - Organizador de código para proyectos en PHP y otros lenguajes.
 *
 * Este script se incluye para prevenir consultas directas a este directorio desde el navegador.
 * Para consultar correctamente la aplicación, abrir desde "public/index.php".
 *
 * @author John Mejia
 * @since Agosto 2023
 */

// Para consultar correctamente la aplicación, abrir desde "public/index.php".
$location = '../public/localtests/list';
$mensaje = "<script>window.location='{$location}';</script>" .
	"Esta página ha sido consultada de forma incorrecta (E1043)." .
	"<a href=\"{$location}\">Favor consultar desde esta página</a>.";
if (!headers_sent()) {
	header("HTTP/1.1 301 Moved Permanently");
	header("Location: {$location}");
}
exit($mensaje);
