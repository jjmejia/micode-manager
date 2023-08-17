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

// Directorio con los scripts del sistema (de preferencia sin acceso web)
define('MIFRAME_ROOT', __DIR__ . '/../src');

// Directorio con los archivos de configuración del sistema (de preferencia sin acceso web)
define('MIFRAME_DATA', __DIR__ . '/../data');

// Consulta script principal
include_once MIFRAME_ROOT . '/admin/index.php';
