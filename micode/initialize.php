<?php
/**
 * Definición de constantes y carga de librerías registradas en "micode".
 * Versión personalizada para uso de la aplicación de Administración.
 *
 * @author John Mejia
 * @since Abril 2022
 */

// Bloque a incluir en todo archivo "initialize.php"...

// Directorio para ubicar los módulos asociados al proyecto.
// Puede declararse externamente para casos especiales, como la validación de inicio en "chexk-externals.php".
if (!defined('MIFRAME_LOCALMODULES_PATH')) {
	define('MIFRAME_LOCALMODULES_PATH', __DIR__);
}

// Patrón para ubicar archivo de configuración de proyecto
define('MIFRAME_LOCALCONFIG_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'config');

// ----------------------------------------------------------------------------------
// A partir de aqui incluye los módulos requeridos, asegurándo primero aquellos que son
// requeridos por otros

// Deben haberse creado los archivos basicos de arranque
require_once MIFRAME_LOCALMODULES_PATH . '/miframe/common/functions.php';
require_once MIFRAME_LOCALMODULES_PATH . '/miframe/common/debug.php';
require_once MIFRAME_LOCALMODULES_PATH . '/miframe/common/errors.php';
require_once MIFRAME_LOCALMODULES_PATH . '/miframe/common/phpsettings.php';
require_once MIFRAME_LOCALMODULES_PATH . '/miframe/common/modules.php';

// Autoload para carga de clases (debe cargarse posterior a miframe_get_proyecto_ini())
require_once MIFRAME_LOCALMODULES_PATH . '/miframe/common/autoload.php';

// ----------------------------------------------------------------------------------

// print_r($_SERVER);
// echo "DEBUG? " . miframe_is_debug_on() . '<hr>';
// echo "<hr><pre>"; $c = get_defined_constants(true); print_r($c['user']); echo "<hr>"; print_r($GLOBALS['MIFRAMEDATA']); exit;
