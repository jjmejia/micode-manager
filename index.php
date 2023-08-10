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

include_once __DIR__ . '/src/repository/miframe/common/functions.php';

$location = 'src/index.php';
$mensaje = "<script>window.location='{$location}';</script>" .
	miframe_text("Esta página ha sido consultada de forma incorrecta.") .
	"<a href=\"{$location}\">" . miframe_text('Favor consultar desde esta página') . "</a>.";
if (!headers_sent()) {
	header("Location: {$location}");
}
exit($mensaje);
