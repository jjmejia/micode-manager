<?php
/**
 * miFrame - Administrador web para miCode.
 *
 * La interfaz provee acceso a los proyectos creados y los módulos disponibles en el repositorio.
 * Hace uso de librerías definidas en el repositorio.
 *
 * @author John Mejia
 * @since Abril 2022
 */

if (!defined('MIFRAME_ROOT')) {
	// No se ha invocado desde el index.php correcto. Redirecciona para realizar la consulta correcta.
	// Si no se consulta desde el index.php indicado, las rutas relativas no funcionarán correctamente.
	include_once __DIR__ . '/lib/modules/reload-index.php';
}

// Directorio base del proyecto actual "src/admin"
define('MIFRAME_BASEDIR', __DIR__);

// Directorio con los scripts del sistema "src" (de preferencia sin acceso web)
define('MIFRAME_SRC', MIFRAME_ROOT . DIRECTORY_SEPARATOR . 'src');

// Directorio con los archivos de configuración del sistema "data" (de preferencia sin acceso web)
define('MIFRAME_DATA', MIFRAME_ROOT . DIRECTORY_SEPARATOR . 'data');

// Path para registro local de proyectos
define('MIFRAME_PROJECTS_REPO', MIFRAME_DATA . DIRECTORY_SEPARATOR . 'projects');

// Valida librerias externas y otros valores de inicio requeridos para que funcione este proyecto.
include_once __DIR__ . '/lib/modules/check-externals.php';

// Definiciones requeridas para uso de los archivos en el repositorio.
include_once __DIR__ . '/micode/initialize.php';

// Funciones exclusivas del administrador web.
include_once __DIR__ . '/lib/modules/admin.php';

$app = new \miFrame\Admin\MiProyecto();

try {
	// Habilita mensajes para depuración (se habilita por configuración de la aplicación)
	// $app->debug(true);

	// Carga rutas (puede definir un archivo diferente por ejemplo si la consulta es para Web Services)
	$app->loadRoutes(
		miframe_path(MIFRAME_DATA, 'base', 'rutas.ini'),
		miframe_path(__DIR__, 'control')
		);

	// Configuración de vistas
	// (si hay multiples directorios de vistas para Web, indicar el path a la vista deseada)
	$app->loadViews(
		miframe_path(MIFRAME_DATA, 'base', 'vistas.ini'),
		miframe_path(__DIR__, 'views/web'),
		miframe_path(__DIR__, 'views/api')
		);

	// Se asegura que haya configurado "sistema.ini"
	if ($app->userEmail() == '' || $app->userName() == '') {
		$app->router->runDefault('settings.php', true);
		exit;
	}

	// $app->router->showInfo();

	// Procesa comandos recibidos por REQUEST (automáticamente detecta del URI si no existe este valor)
	// Si el server no soporta (o no tiene configurado) para que todas las peticiones sean redirigidas a este
	// script (de forma que se detecta la petición deseada al interpretar el URI), entonces se debe invocar este
	// script usando "cmd" como la variable POST que contiene la petición deseada (ruta).
	$app->router->bindPost('cmd');

	// Procesa entrada
	$app->router->run();

	/*

	Forma alterna sin recurrir a un archivo de configuración:

		// Registrar los enrutamientos manualmente
		$app->AddRoute(...);

	O realizar la validación uno a uno...

		// Registra script a ejecutar al invocar abort()
		$app->abortHandler('actions/projects/error.php');

		// Acciones a ejecutar antes de detener la ejecución del script actual
		$app->beforeStopHandler('xxxx');

		// Acción a realizar si no recibe parámetro POST/GET
		$app->runDefault('actions/projects/list.php');

		// Acciones a ejecutar dependiendo de lo indicado en POST(cmd)
		// NOTA: Dado que cada secuencia se evalua en el orden aquí listado, puede
		// resultar ligeramente más rápido y eficiente definir las rutas usando AddRoute()
		// y ejecutar el método $app->run().
		$app->runOnce('projects/create', 'actions/projects/create.php');
		$app->runOnce('modules/detail', 'actions/modules/detail.php');
		...

	*/

	// Si nada funciona, presenta mensaje de error
	$app->router->notFound(
		miframe_text('Página no encontrada'),
		miframe_text('La referencia **$1** no está asociada con una página valida.', $app->router->request())
		);
}
catch (Exception $e) {
	// Captura excepción
	$data = miframe_error_info($e);
	// Emplea método provisto por el router
	$app->router->abort($data['title'], $data['message'], $data['trace']);
}