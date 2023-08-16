<?php
/**
 * Definición de constantes para relacionar archivos a incluir.
 * Versión personalizada para uso de la aplicación de Administración.
 *
 * @author John Mejia
 * @since Abril 2022
 */

// Raíz (solo para Administrador y proyectos) - Se declara MIFRAME_ROOT en el index.php principal
if (!defined('MIFRAME_ROOT')) {
	// El script principal no fue invocado desde "public/index.php"
	exit('Esta página ha sido consultada de forma incorrecta (E1039).');

	/*
	NOTA: Como este script pudo ser invocado desde un script mal formateado, no se puede garantizar el path usado.
	PENDIENTE: Función que detecte el path usado para incvocar el index.php correcto.
	$location = '../index.php';
	$mensaje = "<script>window.location='{$location}';</script>" .
		"Esta página ha sido consultada de forma incorrecta." .
		"<a href=\"{$location}\">Favor consultar desde esta página</a>.";
	if (!headers_sent()) {
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: {$location}");
	}
	exit($mensaje);
	*/
}

// Directorio para ubicar los módulos asociados al proyecto.
// Puede declararse externamente para casos especiales, como la validación de inicio en "chexk-externals.php".
if (!defined('MIFRAME_LOCALMODULES_PATH')) {
	define('MIFRAME_LOCALMODULES_PATH', __DIR__);
}

 // Directorio base del proyecto actual (xxx/admin)
define('MIFRAME_BASEDIR', dirname(__DIR__));

// Patrón para ubicar archivo de configuración de proyecto (Si no existe lo crea)
define('MIFRAME_LOCALCONFIG_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'config');

// Path para registro local de proyectos
define('MIFRAME_PROJECTS_REPO', MIFRAME_ROOT . DIRECTORY_SEPARATOR . 'projects');

// Deben haberse creado los archivos basicos de arranque
include_once MIFRAME_LOCALMODULES_PATH . '/miframe/common/modules.php';

// Habilita mensajes de depuración (por configuración se maneja)
// miframe_debug_enable(true);

// Realiza includes requeridos por este modulo pero igual debe definir el respectivo modules.files
// para asegurarse que los archivos sean incluidos en producción.
miframe_include_module('/miframe/common/functions.php');
miframe_include_module('/miframe/common/debug.php');
// Si incluyó librería para manejo de errores realiza el cargue automático
miframe_include_module('miframe/common/errors.php');
miframe_include_module('miframe/common/phpsettings.php');


// ----------------------------------------------------------------------------------
// Autoload para carga de clases ("miframe_autoload_classes" se define en modules.php).
// spl_autoload_register('miframe_autoload_classes');
miframe_include_module('miframe/common/autoload.php');

// Carga datos de .ini
miframe_get_proyecto_ini();

// Inicializa valores PHP (log errores, etc.)
phpsettings_load();

// ----------------------------------------------------------------------------------

// print_r($_SERVER);
// echo "DEBUG? " . miframe_is_debug_on() . '<hr>';
// echo "<hr><pre>"; $c = get_defined_constants(true); print_r($c['user']); echo "<hr>"; print_r($GLOBALS['MIFRAMEDATA']); exit;
