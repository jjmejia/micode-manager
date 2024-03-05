<?php
/**
 * Librería de funciones para proyectos que usen Router y Views integrados.
 *
 * @micode-uses miframe-file-inifiles
 * @micode-uses miframe-interface-router
 * @micode-uses miframe-interface-views
 * @micode-uses miframe-interface-request
 * @micode-uses miframe-interface-editconfig
 * @author John Mejia
 * @since Abril 2022
 * @version 1.0.0
 */

namespace miFrame\Admin;

// Adaptando a principios SOLID y mejores practicas...

use \miFrame\Interface\Views 	  as miViews;
use \miFrame\Interface\Request 	  as miRequest;
use \miFrame\Interface\RouterIni  as miRouter;
use \miFrame\Interface\EditConfig as miEditConfig;
use \miFrame\Interface\Params 	  as miParams;
// use \miFrame\Utils\UI\HTMLSupport as miHTML;

class MiProyecto { // extends Router

	public $formAction = '';

	private $view = false;			// Objeto interface/Views
	private $post = false;			// Objeto interface/Request
	private $router = false;		// Objeto interface/Router
	private $config = false;		// Objeto interface/EditConfig <-- VALIDAR SI SE REQUIERE GLOBAL
	private $params = false;		// Objeto interface/Params
	private $date_start = 0;

	public function __construct() {

		$this->date_start = microtime(true);

		// Inicializa suplementos
		// Por principios SOLID esto debiera declararse fuera de esta clase
		// (no debe haber un new Class dentro de la clase) sin embargo lo mantenemos
		// para efectos de facilitar el proceso.
		$this->router = new miRouter();
		$this->post = new miRequest();
		$this->view = new miViews();
		$this->params = new miParams();

		// Exportar al REQUEST
		$this->router->autoExport = true;
	}

	public function executionTime() {
		return (microtime(true) - $this->date_start);
	}

	public function showExecutionTime(string $text) {

		$t = $this->executionTime();
		echo miframe_debug_box($t, 'Execution Time (' . $text . ')');
	}

	public function validateJSONRequest(string $api_starts_with = '') {

		$resultado = (($api_starts_with != '' && $this->router->requestStartWith($api_starts_with)) ||
			$this->router->isJSONRequest());

		if ($resultado) {

			$this->router->forceJSON(true);

			// Informa que la salida es en JSON
			header('Content-Type: application/json');
		}

		return $resultado;
	}

	public function loadViews(string $path_views) {

		$this->view->setPathFiles($path_views);

		$filename = $this->view->fileView('config-view.ini');

		if (!file_exists($filename)) {
			miframe_error('No se ha indicado una Vista valida');
		}

		// Inicializa configuración
		$this->view->loadConfig($filename);
	}

	public function startView(string $filename, array $data) {

		$this->view->debug = $this->router->debug;
		// $this->view->force_json = $this->router->force_json;

		// Método para crear URLs (debe ir luego de la captura de datos de navegacion captureUsearAction() y no debe inicializar "form-action" en los defaults de las vistas)
		if ($this->formAction == '') {
			$this->formAction = $this->router->createRouteURL(null, false);
		}

		// Inicializa parámetros
		$this->params->clear();

		// Define valores estándar para el proyecto
		$this->params->set('form-action', $this->formAction);
		$this->params->set('author', $this->userName());
		$this->params->set('author-email', $this->userEmail());
		$this->params->set('page-title', $this->projectTitle());

		// Accciones adicionales al detour <-- No requerido pues con este modelo,
		// cuando invoca $this->router->detour() no se ha inicializado aún la vista.
		/*$this->router->detourCall(
			function() {
				$this->view->cancelLayout();
			});*/

		$this->params->append($data);
		$this->view->capture($filename);
	}

	/**
	 * Retorna el nombre del Propietario del proyecto (usualmente el mismo Desarrollador).
	 * Este valor debe haber sido previamente leido de un archivo .ini de configuración y guardado en memoria.
	 *
	 * @return string Texto
	 */
	public function userName() {
		return miframe_data_get('micode-user');
	}

	/**
	 * Retorna el correo electrónico del Administrador (Desarrollador).
	 * Este valor debe haber sido previamente leido de un archivo .ini de configuración y guardado en memoria.
	 *
	 * @return string Texto
	 */
	public function userEmail() {
		return miframe_data_get('micode-user-email');
	}

	/**
	 * Retorna el nombre corto de la aplicación actual, usado en el Administrador para crear
	 * el directorio que la contiene.
	 * Este valor debe haber sido previamente leido de un archivo .ini de configuración y guardado en memoria.
	 *
	 * @return string Texto
	 */
	public function projectName() {
		return miframe_data_get('project-name');
	}

	/**
	 * Retorna el título de la aplicación actual.
	 * Este valor debe haber sido previamente leido de un archivo .ini de configuración y guardado en memoria.
	 *
	 * @return string Texto.
	 */
	public function projectTitle() {
		return miframe_data_get('project-title');
	}

	/**
	 * Valida si ya se asociaron los datos mínimos asociados a proyectos.
	 *
	 * @return bool TRUE si ya tiene datos registrados para correo y nombre de usuario, FALSE en otro caso.
	 */
	public function validateDataProject(string $action = '') {

		$resultado = (trim($this->userEmail()) !== '' && trim($this->userName()) !== '');
		if (!$resultado) {
			// miframe_is_web() retorna FALSE para consultas JSON o por consola
			if ($action == '' || !miframe_is_web()) {
				$this->router->abort(
					miframe_text('Datos de proyecto no configurados'),
					miframe_text('Contacte al Administrador para configurar correctamente el sistema.')
					);
			}
			else {
				// Ejecuta solamente si la consulta es via Web
				return $this->router->runAction($action, '(settings-check)');
			}
		}

		return $resultado;
	}

	/**
	 * Asigna/Remueve modo depuración, para inclusión de mensajes adicionales en las vistas, log de errores PHP, etc.
	 *
	 * @param bool $value
	 */
	public function debug(bool $value) {
		$this->router->debug = $value;
		if ($this->view !== false) { $this->view->debug = $value; }
		miframe_debug_enable($value);
	}

	/**
	 * Carga archivo de configuración para el router.
	 *
	 * @param string $basename Nombre del archivo ini a cargar (relativo al directorio "micode/config" del proyecto)
	 */
	public function loadRoutes(string $filename) {

		// Carga configuración de rutas
		$this->router->loadConfig($filename);
	}

	/**
	 * HTML alternativo para presentación de cajas con mensajes desplegadas por medio de la función miframe_box().
	 *
	 * @param string $title Título de la presentación.
	 * @param string $message Mensaje a mostrar.
	 * @param string $style Define el tema usado para mostrar la ventana (colores). Puede ser uno de los siguientes:
	 * 			mute (estilo por defecto), info, warning, alert, critical, console.
	 * @param string $footnote Texto a mostrar en la parte baja de la ventana.
	 * @return string Texto HTML.
	 */
	/*
	public function localBox(string $title, string $message, string $style = '', string $footnote = '') {

		$max_alto = ' box-message-limited';
		// if (!$showscrolls) { $max_alto = ''; }

		if ($footnote != '') {
			$footnote = "<div class=\"box-footnote box-$style\">$footnote</div>";
			}

		if ($title != '') {
			$title = '<div class="box-title">' . $title . '</div>';
			}

		$salida = $this->html->getStylesCSS(true);

		$salida .= "<div class=\"miframe-box box-$style\">" .
			$title .
			'<div class="box-message' . $max_alto . '">'.
			$message .
			$footnote .
			'</div>'.
			'</div>';

		return $salida;
	}

	public function apiBox(string $title, string $message, string $style = '', string $footnote = '', bool $showscrolls = true) {

		if ($footnote != '') {
			$footnote = PHP_EOL . "---" . PHP_EOL . $footnote . PHP_EOL . '---' . PHP_EOL;
			}

		if ($title != '') {
			$title = '*** ' . strtoupper($title) . PHP_EOL . PHP_EOL;
			}

		$salida = PHP_EOL . PHP_EOL . strip_tags(
			$title .
			$message .
			$footnote
			);

		return $salida;
	}
	*/

	public function startEditConfig() {

		if ($this->config === false) {
			$this->config = new miEditConfig();
			$this->config->debug = $this->router->debug;
		}
	}

	public function reload(string $cmd, array $params = array()) {

		$data = array();
		// Guarda en temporal los mensajes y retorna un valor de caché
		if ($this->config !== false && $this->config->existsMessages()) {
			$data['msg'] = $this->config->getMessages();
		}
		// Crea pagina a recargar
		$enlace = $this->router->reload($cmd, $params, $data);

	}

	public function __get(string $name) {
		// Valida alguna de los objetos privados
		// Se maneja de esta forma (y no declarando cada objeto como tipo publico) para prevenir que
		// sea modificado el objeto como tal por accidente.
		$validos = [ 'view', 'post', 'router', 'config', 'params' ];
		if (in_array($name, $validos)) {
			if ($this->$name === false) {
				// Intenta acceder a un objeto no declarado aun
				miframe_error('El elemento "$1" no ha sido aún instanciado en la clase $2', $name, get_class($this));
			}
			return $this->$name;
		}
		// Si llega a este punto, está intentando leer un item no valido
		miframe_error('El elemento "$1" no existe en la clase $2', $name, get_class($this));
	}

	// Throwable o Exception
	public function abort(mixed $e) {

		$data = miframe_error_info($e);
		// Emplea método provisto por el router
		$this->router->abort($data['title'], $data['message'], $data['trace']);
		exit;
	}

	/*

	// include once 'clases/configuracion.php';
	ini_set('default_charset', miProyecto_Configuracion::Param('charset'));

	// Manejo de tiempo del sistema (https://www.php.net/manual/es/timezones.php)
	ini_set('date.timezone', miProyecto_Configuracion::Param('timezone'));
	date_default_timezone_set($zona_horaria);

	// upload_max_filesize is only changeable in PHP_INI_PERDIR
	// upload_max_filesize = 2M
	// post_max_size = 8M
	// memory_limit = 128M
	// Generally speaking, memory_limit should belarger than post_max_size.
	// Note thatto have no memory limit, set this directive to -1.
	// expose_php - php.ini solo y debe estar en "0" en producción

	// enable_post_data_reading = 0 bloquea llenado de $_POST y similares, incluido $_FILES, usar  php://input
	// Pero php://input is not available with enctype="multipart/form-data".

	*/
	// }

}