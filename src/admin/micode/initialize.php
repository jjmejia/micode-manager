<?php
/**
 * Definición de constantes para relacionar archivos a incluir.
 * Versión personalizada para uso de la aplicación de Administración.
 *
 * @author John Mejia
 * @since Abril 2022
 */

include_once __DIR__ . '/miframe/common/modules.php';

// Habilita mensajes de depuración (por configuración se maneja)
// miframe_debug_enable(true);

// Define valores de constantes requeridas por "modules/admin.php"
// ----------------------------------------------------------------------------------
$dirname = dirname(__DIR__);

// Directorio base del proyecto actual (xxx/admin)
define('MIFRAME_BASEDIR', $dirname);

// Directorio para ubicar los módulos asociados al proyecto
define('MIFRAME_LOCALMODULES_PATH', __DIR__);

// Patrón para ubicar archivo de configuración de proyecto (Si no existe lo crea)
define('MIFRAME_LOCALCONFIG_PATH', miframe_path(__DIR__, 'config'));

// Si incluyó librería para manejo de errores realiza el cargue automático
miframe_include_module('miframe/common/errors.php');
miframe_include_module('miframe/common/phpsettings.php');

// ----------------------------------------------------------------------------------

// Raíz (solo para Administrador y proyectos) - Se declara MIFRAME_ROOT en el index.php principal
if (!defined('MIFRAME_ROOT')) {
	miframe_redir('../index.php', 'Script no ejecutado correctamente.');
}

// Path para registro local de proyectos
define('MIFRAME_PROJECTS_REPO', miframe_path(MIFRAME_ROOT, 'projects'));

// ----------------------------------------------------------------------------------
// Autoload para carga de clases ("miframe_autoload_classes" se define en modules.php).
// spl_autoload_register('miframe_autoload_classes');
miframe_include_module('miframe/common/autoload.php');

// Carga datos de .ini
miframe_get_proyecto_ini();

// Inicializa valores PHP (log errores, etc.)
// Sugerencia: Esta línea solo se adiciona si incluye módulo miframe/common/phpsettings.
phpsettings_load();

// ----------------------------------------------------------------------------------

// print_r($_SERVER);
// echo "DEBUG? " . miframe_is_debug_on() . '<hr>';
// echo "<hr><pre>"; $c = get_defined_constants(true); print_r($c['user']); echo "<hr>"; print_r($GLOBALS['MIFRAMEDATA']); exit;
