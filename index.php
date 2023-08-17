<?php
/**
 * miCode - Organizador de código para proyectos en PHP y otros lenguajes.
 *
 * Esta propuesta se realiza considerando que un desarrollador (particularmente aquellos freelance) puedan
 * manejar los diferentes bloques de código funcionales (librerías include con funciones y/o clases) que realizan
 * en su trabajo, de forma que puedan reusarlos en sus diferentes proyectos sin estar copiando los archivos una y
 * otra vez. Así mismo, las mejoras que realicen a futuro podrán beneficiar proyectos pasados. La aplicación
 * provee los medios para que una vez se tenga listo el proyecto se pueda generar un paquete que contenga todos
 * los archivos que necesite para su uso de forma independiente.
 *
 * @author John Mejia
 * @since Abril 2022
 */

// Para consultar correctamente la aplicación, abrir desde "public/index.php".
$location = 'public/index.php';
$mensaje = "<script>window.location='{$location}';</script>" .
	"Esta página ha sido consultada de forma incorrecta (E1041)." .
	"<a href=\"{$location}\">Favor consultar desde esta página</a>.";
if (!headers_sent()) {
	header("HTTP/1.1 301 Moved Permanently");
	header("Location: {$location}");
}
exit($mensaje);
