<?php
/**
 * Librería de funciones para proyectos que usen Router y Views integrados.
 *
 * @micode-uses miframe/file/inifiles
 * @micode-uses miframe/interface/router
 * @micode-uses miframe/interface/views
 * @micode-uses miframe/interface/request
 * @micode-uses miframe/interface/EditConfig
 * @author John Mejia
 * @since Abril 2022
 * @version 1.0.0
 */

namespace miFrame\Admin;

class MiProyecto { // extends Router

	public $view = false;			// Objeto interface/Views
	public $post = false;			// Objeto interface/Request
	public $router = false;			// Objeto interface/Router
	public $config = false;			// Objeto interface/EditConfig
	public $formAction = '';

	private $view_path_web = '';
	private $view_path_json = '';
	private $view_file = '';
	private $view_name = '';
	private $framebox_css = false;

	public function __construct() {

		// Inicializa suplementos
		$this->router = new \miFrame\Interface\Router();
		$this->post = new \miFrame\Interface\Request();

		// Exportar al REQUEST
		$this->router->autoExport = true;

		// Redefine include a usar en Router y View para que cuando se invoquen, "$this" haga
		// referencia a este objeto (MiProyecto).
		$this->router->setIncludeFun(array($this, 'includeFile'));

		// Registra ventanas modales personalizadas
		miframe_data_fun('miframe-box-web', array($this, 'localBox'));
	}

	public function loadView(string $filename, string $path_files_web, string $path_files_json, string $name = '') {

		$this->view_file = $filename;
		$this->view_name = $name;
		$this->view_path_web = $path_files_web;
		$this->view_path_json = $path_files_json;
	}

	public function startView(string $filename, array $data) {

		if ($this->view === false) {
			// Inicializa la clase solamente la primera vez que se invoca

			if ($this->view_file === '' || !file_exists($this->view_file)) {
				miframe_error('Vistas no configuradas');
			}

			$this->view = new \miFrame\Interface\Views();

			$this->view->debug = $this->router->debug;
			$this->view->force_json = $this->router->force_json;

			// Debe ir antes que se genere cualquier posible invocación a includes (sea por layout o error)
			$this->view->setIncludeFun(array($this, 'includeFile'));

			// Adiciona directorio a buscar vistas API/WEB
			$view_base = $this->view_path_web;
			if ($this->view->jsonRequest()) {
				$view_base = $this->view_path_json;
			}
			else {
				// En caso que haya definido multiples tipos de vistas, aquí selecciona el path a usar
				// $this->view_name
			}
			// Inicializa directorios
			$this->view->setPathFiles($view_base);
			// Inicializa configuración
			if ($this->view->jsonRequest()) {
				// Asume que siempre declara un layout por default para JSON pero no aborta si no existe
				$this->view->layoutDefault('default.php', true);
			}
			else {
				$this->view->loadConfig($this->view_file, $this->view_name);
			}
		}

		// Método para crear URLs (debe ir luego del bindPost() y no debe inicializar "form-action" en los defaults de las vistas)
		if ($this->formAction == '') {
			$this->formAction = $this->router->getFormAction();
		}

		$this->view->setParams( [
			'form-action' => $this->formAction,
			'author' => $this->userName(),
			'author-email' => $this->userEmail(),
			'page-title' => $this->projectTitle(),
			]);

		// Accciones adicionales al detour <-- No requerido pues con este modelo,
		// cuando invoca $this->router->detour() no se ha inicializado aún la vista.
		/*$this->router->detourCall(
			function() {
				$this->view->cancelLayout();
			});*/

		$this->view->capture($filename, $data);
	}

	public function includeFile(string $filename) {

		include_once $filename;
	}

	/**
	 * Retorna el nombre del Propietario del proyecto (usualmente el mismo Desarrollador).
	 * Este valor debe haber sido previamente leido de un archivo .ini de configuración y guardado en memoria.
	 *
	 * @return string Texto
	 */
	public function userName() {
		return miframe_data_get('micode-user', 'Anónimo');
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
	public function loadRoutes(string $filename, string $path_files) {
		// Carga configuración de rutas
		$this->router->loadConfig($filename, false);
		// Define path a buscar archivos de rutas
		$this->router->setPathFiles($path_files);
	}

	/**
	 * HTML alternativo para presentación de cajas con mensajes desplegadas por medio de la función miframe_box().
	 *
	 * @param string $title Título de la presentación.
	 * @param string $message Mensaje a mostrar.
	 * @param string $style Define el tema usado para mostrar la ventana (colores). Puede ser uno de los siguientes:
	 * 			mute (estilo por defecto), info, warning, alert, critical, console.
	 * @param string $footnote Texto a mostrar en la parte baja de la ventana.
	 * @param bool $showscrolls TRUE para restringir la altura de la ventana con la información (si el contenido es mayor se habilitan scrolls
	 *			en la ventana para permitir su visualización), FALSE para presentar el contenido sin restricción de altura (sin scrolls).
	 * @return string Texto HTML.
	 */
	public function localBox(string $title, string $message, string $style = '', string $footnote = '', bool $showscrolls = true) {

		$max_alto = ' box-message-limited';
		if (!$showscrolls) { $max_alto = ''; }

		if ($footnote != '') {
			$footnote = "<div class=\"box-footnote box-$style\">$footnote</div>";
			}

		if ($title != '') {
			$title = '<div class="box-title">' . $title . '</div>';
			}

		$salida = '';

		if (!$this->framebox_css) {
			$url_base = $this->router->createURL('/admin/public/css/miframebox.css');
			$salida .= "<link rel=\"stylesheet\" href=\"$url_base\">";
			$this->framebox_css = true; // No repite este bloque
			}

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
			$title = PHP_EOL . PHP_EOL . '*** ' . strtoupper($title) . PHP_EOL . PHP_EOL;
			}

		$salida = strip_tags(
			$title .
			$message .
			$footnote
			);

		return $salida;
	}

	public function startEditConfig() {

		$this->config = new \miFrame\Interface\EditConfig();
		$this->config->debug = $this->router->debug;

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