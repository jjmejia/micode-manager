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
include_once MIFRAME_BASEDIR . '/lib/modules/check-externals.php';

// Definiciones requeridas para uso de los archivos en el repositorio.
include_once MIFRAME_ROOT . '/micode/initialize.php';

// Funciones exclusivas del administrador web.
include_once MIFRAME_BASEDIR . '/lib/modules/admin.php';

$app = new \miFrame\Admin\MiProyecto();

try {
	// Habilita mensajes para depuración (se habilita por configuración de la aplicación)
	// $app->debug(true);
	$app->router->useRequestURI();

	// CSS a usar por método $app->localBox()
	// $app->framebox_css = $app->router->createURL('/public/resources/css/miframebox.css');
	// $app->setFilenameCSS(MIFRAME_ROOT . '/public/resources/css/miframebox.css');

	// Inicializa directorios para vistas y base para ejecución de rutas
	$app->view->setPathFiles(miframe_path(MIFRAME_BASEDIR, 'views'));
	$app->router->setPathFiles(miframe_path(MIFRAME_BASEDIR, 'control'));

	// Valida si es una solicitud de API o JSON
	if ($app->router->requestStartWith('api') || $app->router->jsonRequest()) {

		$app->initializeJson();

		// Carga rutas para consultas API
		$app->loadRoutes(miframe_path(MIFRAME_DATA, 'routes', 'api.ini'));

		// Configuración de vistas para API
		$app->loadView('api');

		// Si no ha configurado datos, genera error
		if (!$app->existsDataProject()) {
			$app->router->abort(
				miframe_text('Error: Datos de proyecto no configurados'),
				miframe_text('Ingrese por Web y configure el sistema.')
				);
		}
	}
	else {
		// Carga rutas para consultas WEB
		$app->loadRoutes(miframe_path(MIFRAME_DATA, 'routes', 'web.ini'));

		// Configuración de vistas para Web
		// (si hay multiples directorios de vistas para Web, indicar el path a la vista deseada)
		$app->loadView('web');

		// Se asegura que haya configurado valores registrados en "sistema.ini"
		// (solamente para consultas WEB)
		if (!$app->existsDataProject()) {
			$app->router->runAction('settings.php', '(settings-check)');
		}
	}

	// $app->router->showInfo();

	/*

	Forma alterna sin recurrir a un archivo de configuración:


		// Recomendado: Registrar script a ejecutar al invocar abort()
		$app->addAbortRoute('actions/projects/error.php');

		// Opcional: Acciones a ejecutar antes de detener la ejecución del script actual
		$app->addBeforeStopRoute('xxxx');

		// Asigna modo de captura del enlace de navegación
		$app->router->assignMode('uri');

		// Definir todo el mapa de rutas manualmente

		// Acción a realizar si no recibe parámetro POST/GET (por defecto)
		$app->addDefaultRoute('actions/projects/list.php');

		// Registrar los enrutamientos públicos uno a uno
		$app->AddRoute('projects/create', 'actions/projects/create.php');
		$app->AddRoute('modules/detail', 'actions/modules/detail.php');
		...

		// o por medio de un arreglo asociativo (referencia => script)
		// Este arreglo puede ser tomando por ejemplo de un archivo .ini diferente al esperado
		// o de una base de datos.
		$mapa = array(
			'projects/create' => 'actions/projects/create.php',
			'modules/detail' => 'actions/modules/detail.php',
			...
			);
		$app->AddRoutes($mapa);

	*/

	// Procesa entrada
	$app->router->run();

	// Si nada funciona, presenta mensaje de error
	$app->router->notFound(
		miframe_text('Página no encontrada'),
		miframe_text('La referencia **$1** no está asociada con una página valida.', $app->router->request())
		);

}
catch (\Throwable | \Exception $e) {
	// Throwable For PHP 7, Exception for PHP 5
	// Captura excepción manual (throw new Exception)
	// https://stackoverflow.com/a/51700135
	$app->abort($e);
}
