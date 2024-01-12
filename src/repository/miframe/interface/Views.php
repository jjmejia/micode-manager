<?php
/**
 * Librería de funciones requeridas para salida a pantalla.
 * 'MIFRAME_ABORT_VIEW'
 *
 * @micode-uses miframe/interface/request
 * @author John Mejia
 * @since Abril 2022.
 */

namespace miFrame\Interface;

class Views extends \miFrame\Interface\Shared\BaseClass {

	private $secciones = array();
	private $seccion_default = array();
	private $layout = array();
	private $buffer = '';
	private $view_name = '';
	private $view_title = '';
	private $start_engine = false;

	public function __construct() {

		$this->initialize();
		$this->color_debug = '#0969da';

		// Inicializa contenedor de layout
		$this->layout = array('file' => '', 'file-error' => '', 'file-default' => '');

	}

	private function startEngine() {

		if (!$this->start_engine) {
			// Marca inicio
			$this->start_engine = true;
			// Funcion para ejecutar al cierre (en caso que termine el script antes de realizar el render)
			register_shutdown_function(array($this, 'show'));
			// Captura todo en adelante
			// ob_start();
		}
	}

	public function setViewName(string $name) {
		$this->view_name = $name;
	}

	public function fileView(string $basename) {

		return miframe_path($this->path_files, $this->view_name, $basename);
	}

	/**
	 * Carga plantilla a usar para mostrar salida a pantalla.
	 *
	 * @param string $template
	 */
	public function layout(string $filename, string $namesection) {

		$this->setLayouts('file', $filename);

		if ($namesection != '') {
			// Ejecuta script
			$this->seccion_default[0] = $namesection;
		}
		else {
			// Desglosa mensajes de error según sea el caso
			if ($filename == '' && $namesection == '') {
				miframe_error('No declaró archivo vista ni sección por defecto');
			}
			if ($namesection == '') {
				miframe_error('No declaró sección por defecto');
			}
			elseif ($filename != '') {
				miframe_error('No encontró archivo vista', debug: $filename);
			}
			else {
				miframe_error('No declaró archivo vista');
			}
		}
	}

	public function layoutError(string $basename) {

		$this->setLayouts('file-error', $basename);
	}

	public function layoutDefault(string $basename, bool $optional = false) {

		$this->setLayouts('file-default', $basename, $optional);
	}

	private function setLayouts(string $name, string $basename, bool $optional = false) {

		if ($basename != '') {
			$path = $this->fileView($basename);
			if (file_exists($path)) {
				$this->layout[$name] = $path;
			}
			elseif (!$optional) {
				miframe_error('No encontró archivo vista **$1** ($2)', $basename, $name);
			}
		}
	}

	private function sectionName(string $name) {

		$uname = strtolower(trim($name));
		// Reduce tamaño asociado
		if (strlen($uname) > 32) { $uname = miframe_mask($name); }

		return $uname;
	}

	private function sectionDefault() {

		if (!isset($this->seccion_default[0])) {
			$this->seccion_default[0] = '__trash__';
		}

		return $this->seccion_default[0]; // Valor por defecto siempre
	}

	//OBVIEWSECION this->section->show('xxx')
	/**
	 *
	 * @param string $name
	 * @param string $template Template a usar, usar "{{ content }}" para indicar dónde irá el contenido.
	 */
	public function get(string $namesection) {

		$salida = '';
		$uname = $this->sectionName($namesection);
		if ($uname != '' && isset($this->secciones[$uname])) {
			$salida .= $this->secciones[$uname];
		}

		if ($this->debug) {
			if (!miframe_is_web()) {
				$salida = PHP_EOL . PHP_EOL . ">>> START MiFrame.section " . $namesection . PHP_EOL .
					$salida .
					PHP_EOL . PHP_EOL . ">>> END MiFrame.section " . $namesection . PHP_EOL . PHP_EOL;
			}
			else {
				$salida = PHP_EOL . PHP_EOL . "<div style=\"border:1px dashed #333;padding:0;margin:0;margin-top:2px;\">" . PHP_EOL .
					"<div style=\"padding:5px;margin:0;font-size:12px;background:#333;color:#fff;line-height:1\"><b>MiFrame.section</b> " . htmlspecialchars($namesection) . "</div>" . PHP_EOL .
					$salida .
					PHP_EOL . "</div>\n\n" . PHP_EOL . PHP_EOL;
			}
		}

		return $salida;
	}

	//OBVIEWSECION
	public function put(string $namesection, string $content, bool $append = false, string $conector = '') {

		$uname = $this->sectionName($namesection);
		if ($uname != '') {
			if (!$append || !isset($this->secciones[$uname])) {
				$this->secciones[$uname] = '';
			}
			if ($append && $this->secciones[$uname] !== '') {
				// Evita adicionar multiples conectores consecutivos
				$this->secciones[$uname] .= $conector;
				$this->secciones[$uname] .= rtrim($content); // No remueve fin de linea a la izquierda
			}
			else {
				$this->secciones[$uname] = trim($content);
			}
		}
	}

	//OBVIEWSECION
	public function remove(string $namesection) {

		$uname = $this->sectionName($namesection);
		if ($uname != '') {
			unset($this->secciones[$uname]);
		}
	}

	//OBVIEWSECION
	public function exists(string $namesection) {

		$uname = $this->sectionName($namesection);
		if ($uname != '') {
			return array_key_exists($uname, $this->secciones);
		}

		return false;
	}

	//OBVIEWSECION
	/**
	 * @param string $section
	 */
	public function capture(string $filename = '', string $namesection = '') {

		// Por comodidad, permite definir una seccion en $filename iniciando con ":"
		if (substr($filename, 0, 1) == ':' && $namesection == '') {
			$namesection = trim(substr($filename, 1));
			$filename = '';
		}

		if ($filename != '') {
			// Captura desde un archivo en el directorio de vistas
			$path = $this->fileView($filename);
			if (file_exists($path)) {
				// Ejecuta script
				$this->start($namesection);
				$this->include($path, 'CAPTURE ' . $filename);
				$this->stop();
			}
			elseif ($this->layout['file-default'] != '') {
				// Plantilla común a usar por defecto
				$this->start($namesection);
				$this->include($this->layout['file-default'], 'CAPTURE/DEFAULT ' . $filename);
				$this->stop();
			}
			else {
				// Reporta error
				miframe_error('No encontró archivo vista "$1"', $path, debug:dirname($filename));
			}
		}
		else {
			// Habilita captura de salida a pantalla directamente hasta la finalización del script
			// o hasta ejecutar stop().
			$this->start($namesection);
		}

	}

	public function start(string $namesection) {

		$this->startEngine();

		if ($namesection != '') {
			$this->seccion_default[] = $namesection;
		}
		ob_start();
	}

	//OBVIEWSECION?
	public function stop() {

		// Exporta a pantalla los restos del buffer (si alguno)
		$this->buffer('', true);
		// Termina captura y envia contenido a catchbuffer().
		$this->catchbuffer(ob_get_contents());
		ob_end_clean();
	}

	//OBVIEWSECION
	/**
	 * Función de captura de salida a pantalla
	 *
	 * @param string $buffer
	 */
	private function catchbuffer(string $buffer) {

		$namesection = $this->sectionDefault(); // Valor por defecto siempre
		if (count($this->seccion_default) > 1) {
			// Hay mas de una seccion en captura, recupera la ultima (modelo FILO)
			$namesection = array_pop($this->seccion_default);
		}

		// Adiciona a la sección indicada
		$this->put($namesection, $buffer, true);

		// Libera memoria y previene salida a pantalla antes de continuar
		$buffer = '';

		return $buffer;
	}

	public function cancelLayout() {

		$this->show(true);
	}

	public function show(bool $cancel = false) {

		// Forza terminación de capturas previas
		while (ob_get_level()) { $this->stop(); }

		if ($cancel) {
			$this->layout['file'] = '';
			return;
		}

		// Confirma si ocurrió un FATAL ERROR y lo captura para su presentacion
		$this->includeLastError();

		if (!isset($this->layout['file']) || $this->layout['file'] == '') {
			$namesection = $this->sectionDefault();
			if ($namesection != '') {
				echo $this->get($namesection);
				$this->remove($namesection);
			}
			// Muestra secciones capturadas previamente?
			$this->secciones = array_filter($this->secciones);
			if (count($this->secciones) > 0) {
				foreach ($this->secciones as $namesection => $contenido) {
					echo miframe_box(miframe_text('Sección $1', $namesection), nl2br(htmlentities($contenido)), 'info');
				}
			}
		}
		else {
			// Salida a pantalla final
			$this->include($this->layout['file']); //, 'LAYOUT ' . $this->layout['file']);

			// Limpia ya que terminó
			$this->layout['file'] = '';
		}
	}

	/**
	 * Incluye archivo con subrutinas. Debe invocarse luego de cargar base (layout)
	 * para poder usar $this->path_files.
	 *
	 * @param string $filename Archivo con la definición de subrutinas. Puede ser completo o relativo a $this->path_files.
	 */
	/*public function expandWith(string $filename) {

		$path = $filename;
		if (!file_exists($path)) {
			// Complementa path (usa por defecto donde encontró el layout)
			$path = miframe_path($this->path_files, $filename);
		}

		if (!$this->include($path, 'EXPAND ' . $filename)) {
			miframe_error('No existe librería de rutinas **$1** (buscado en: $2)', basename($filename), implode(', ', [ dirname($filename), $this->path_files ]));
		}
	}*/

	//OBVIEWSECTION section->capturesub
	public function call(string $namesection, string $namesub, mixed ...$args) {

		$namesub = strtolower(trim($namesub));
		if (isset($this->contenedor[$namesub])) {
			// Ejecuta la subrutina y almacena salida a pantalla en una sección temporal
			// $this->put($namesection, '');
			$this->start($namesection);
			call_user_func($this->contenedor[$namesub], ...$args);
			$this->stop();
		}
	}

	public function buffer($buffer, bool $force_echo = false) {

		$this->buffer .= $buffer;

		// Previene desborde de memoria
		if (strlen($this->buffer) > 1024 || $force_echo) {
			echo $this->buffer;
			$this->buffer = '';
		}
	}

	/**
	 * Usa constantes para fijar valores booleanos de control... mejorar descripcion
	 */
	/*
	public function once(string $name) {

		$name = 'MIFRAME_BOOL_' . strtoupper(miframe_only_alphanum($name));
		$valor = !defined($name);
		if ($valor) { define($name, true); }

		return $valor;
	}
	*/

	/*
	public function abort(string $title, string $message) {

		$namesection = $this->sectionDefault(); // Valor por defecto siempre
		$pre = $this->get($namesection);
		$this->put($namesection, '');

		if ($this->layout['file-error'] != '') {
			$this->capture($this->layout['file-error'], [ 'title' => $title, 'message' => $message, 'footnote' => $pre ]);
		}
		else {
			// Si no pudo ejecutar lo anterior, presenta mensaje base
			if ($pre != '') {
				$pre = "<p><b>Contenido previo</b></p><pre>" . htmlspecialchars($pre) . "</pre>";
			}
			// Mensaje con error a pantalla
			$this->put($namesection, $this->sprintf($title, $message, $pre));
		}

		$this->cancelLayout();

		exit;
	}
	*/

	private function includeLastError() {

		$last_error = error_get_last();
		if (is_array($last_error)
			&& isset($last_error['message'])
			) {
			// La salida a pantalla sale sin caracteres especiales
			$local_message = htmlspecialchars($last_error['message'], ENT_COMPAT);
			$namesection = $this->sectionDefault(); // Valor por defecto siempre
			$pordefecto = $this->get($namesection);
			$pos = strpos($pordefecto, $local_message);
			if ($pos !== false) {
				// Preserva lo ultimo
				$pordefecto = trim(substr($pordefecto, 0, $pos));
				if (strtolower(substr($pordefecto, -19)) === '<b>fatal error</b>:') {
					// Remueve titulo FATAL ERROR
					$pordefecto = trim(substr($pordefecto, 0, -19));
				}
				// Elimina posible fin de linea
				if (strtolower(substr($pordefecto, -6)) === '<br />') {
					// Remueve titulo FATAL ERROR
					$pordefecto = trim(substr($pordefecto, 0, -6));
				}
			}
			$pordefecto .= miframe_box(miframe_debug_error_code($last_error['type']), $last_error['message'], 'critical', "<b>{$last_error['file']}</b> Línea {$last_error['line']}");
			$this->put($namesection, $pordefecto);
		}
	}

	private function getConfigData(array $data, string $param, string $default = '') {

		if (isset($data[$param]) && $data[$param] != '') {
			$default = $data[$param];
		}

		return $default;
	}

	/**
	 * Carga enrutamientos listados de un archivo .ini.
	 *
	 * El nombre de grupo debe ser único, para identificar el tipo de vista (en caso de opciones múltiples).
	 * Ejemplo:
	 *
	 * 		title = (Título para esta vista, a usar en administraciones de plantillas)
	 * 		layout = "layout.php" (opcional)
	 * 		main-section = "contenido" (obligatorio)
	 * 		tpl-error = "error.php"
	 * 		tpl-default = "default.php"
	 *
	 * @param string $filename Nombre del archivo .ini a cargar,
	 * @param bool $rewrite TRUE para remplazar enrutamientos existentes. FALSE adiciona a los ya registrados.
	 * @param string $dirbase Path a usar para ubicar los scripts.
	*/
	public function loadConfig(string $filename) {

		if (!file_exists($filename)) {
			miframe_error('Archivo no encontrado: $1', $filename);
		}

		$data = parse_ini_file($filename, true, INI_SCANNER_RAW);

		$this->loadDataConfig($data);

	}

	public function loadDataConfig(array $data) {

		$this->view_title = $this->getConfigData($data, 'title');

		// Define valores para la plantilla a usar.
		// LO hace antes de evaluar enrutamiento para que los scripts llamados reutilicen esta configuración.
		$this->layout(
			$this->getConfigData($data, 'layout'),
			$this->getConfigData($data, 'main-section')
			);

		// Plantilla para reporte de errores
		$this->layoutError($this->getConfigData($data, 'tpl-error'));

		// Plantilla por defecto a usar si no encuentra la invocada por $view->capture()
		$this->layoutDefault($this->getConfigData($data, 'tpl-default'));

		// Carga subrutinas de salida a pantalla
		// $this->expandWith($this->getConfigData($data, 'expand-with'));
	}

	public function getTitle() {

		return $this->view_title;
	}
}
