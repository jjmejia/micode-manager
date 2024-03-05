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

try {
	// Crea la aplicación principal, referida a la clase MiProyecto
	miframe_register_app(\miFrame\Admin\MiProyecto::class);

	// Habilita mensajes para depuración (se habilita por configuración de la aplicación)
	// miframe_app()->debug(true);
	// miframe_app()->router->useRequestURI(false); // Usa _route para indicar las rutas

	// CSS a usar por método miframe_app()->localBox()
	// miframe_app()->framebox_css = miframe_app()->router->createURL('/public/resources/css/miframebox.css');
	// miframe_app()->setFilenameCSS(MIFRAME_ROOT . '/public/resources/css/miframebox.css');

	// Inicializa directorio base para ejecución de rutas (en este caso, es el mismo para APIs y WEB)
	miframe_app()->router->setPathFiles(miframe_path(MIFRAME_BASEDIR, 'control'));

	// Valida si es una solicitud de API o JSON
	if (miframe_app()->validateJSONRequest('api')) {

		// Carga rutas para consultas API
		miframe_app()->loadRoutes(miframe_path(MIFRAME_DATA, 'routes', 'api.ini'));

		// Inicializa directorios para vistas
		miframe_app()->loadViews(miframe_path(MIFRAME_BASEDIR, 'views', 'api'));
	}
	else {
		// Carga rutas para consultas WEB
		miframe_app()->loadRoutes(miframe_path(MIFRAME_DATA, 'routes', 'web.ini'));

		// Configuración de vistas para Web
		// (si hay multiples directorios de vistas para Web, indicar el path a la vista deseada)
		miframe_app()->loadViews(miframe_path(MIFRAME_BASEDIR, 'views', 'web'));
	}

	// Se asegura que haya configurado valores registrados en "sistema.ini"
	// (solamente para consultas WEB, para JSON genera error)
	miframe_app()->validateDataProject('settings.php');

	// miframe_app()->router->showInfo();

	/*

	Forma alterna sin recurrir a un archivo de configuración:


		// Recomendado: Registrar script a ejecutar al invocar abort()
		miframe_app()->addAbortRoute('actions/projects/error.php');

		// Opcional: Acciones a ejecutar antes de detener la ejecución del script actual
		miframe_app()->addBeforeStopRoute('xxxx');

		// Asigna modo de captura del enlace de navegación
		miframe_app()->router->assignMode('uri');

		// Definir todo el mapa de rutas manualmente

		// Acción a realizar si no recibe parámetro POST/GET (por defecto)
		miframe_app()->addDefaultRoute('actions/projects/list.php');

		// Registrar los enrutamientos públicos uno a uno
		miframe_app()->AddRoute('projects/create', 'actions/projects/create.php');
		miframe_app()->AddRoute('modules/detail', 'actions/modules/detail.php');
		...

		// o por medio de un arreglo asociativo (referencia => script)
		// Este arreglo puede ser tomando por ejemplo de un archivo .ini diferente al esperado
		// o de una base de datos.
		$mapa = array(
			'projects/create' => 'actions/projects/create.php',
			'modules/detail' => 'actions/modules/detail.php',
			...
			);
		miframe_app()->AddRoutes($mapa);

	*/

	// Procesa entrada
	miframe_app()->router->run();

	// Si nada funciona, presenta mensaje de error
	miframe_app()->router->notFound();

}
catch (\Throwable | \Exception $e) {
	// Throwable For PHP 7, Exception for PHP 5
	// Captura excepción manual (throw new Exception)
	// https://stackoverflow.com/a/51700135

	if (miframe_check_app()) {
		miframe_app()->abort($e);
	}
	else {
		// No se ha definido objeto principal
		$data = miframe_error_info($e);
		echo miframe_box($data['title'], nl2br($data['message']), 'critical', $data['trace']);
	}
}
