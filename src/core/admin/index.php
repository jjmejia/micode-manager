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

// Definiciones requeridas para uso de los archivos en el repositorio.
include_once __DIR__ . '/micode/initialize.php';
// Funciones exclusivas del administrador web.
include_once __DIR__ . '/lib/modules-admin.php';

// Módulos a usar
miframe_use('miframe/miproyecto', 'miframe/router', 'interface/obviews', 'interface/request', 'interface/errors', 'interface/debug');

$app = new \miFrame\MiProyecto();

// Habilita mensajes para depuración
$app->debug(true);

// Define valores para la plantilla a usar.
// LO hace antes de evaluar enrutamiento para que los scripts llamados reutilicen esta configuración.
$app->view->load(miframe_path(__DIR__, 'views', 'layout.php'), [
	'author' => $app->User(),
	'author-email' => $app->UserEmail(),
	'title' => $app->Title(),
	]);

// Carga subrutinas de salida a pantalla
$app->view->subroutines('admin-subs.php');

// Procesa comandos recibidos por REQUEST
$app->bind('cmd');

// Path base para ubicar scripts
$app->pathScripts('actions');

// Acción a realizar si no recibe parámetro POST
$app->empty('projects/list');

// Acciones a ejecutar dependiendo de lo indicado en POST(cmd)
$app->run('projects/create');
$app->run('modules/create');
$app->run('modules/list/?type');
$app->run('modules/detail/?type/module');
$app->run('projects/edit/?app');
$app->run('tests/list/?type');
$app->run('tests/run/?type/file');

// Acción a ejecutar si ninguna de las anteriores se cumple
$app->default('projects/error');