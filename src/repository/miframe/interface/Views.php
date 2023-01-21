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

	public function __construct() {

		$this->initialize();
		$this->color_debug = '#0969da';

		// Funcion para ejecutar al cierre (en caso que termine el script antes de realizar el render)
		register_shutdown_function(array($this, 'show'));

		// Inicializa contenedor de layout
		$this->layout = array('file' => '', 'file-error' => '', 'file-default' => '');

		// Captura todo en adelante
		ob_start();
	}

	/**
	 * Carga plantilla a usar para mostrar salida a pantalla.
	 *
	 * @param string $template
	 * @param $params
	 */
	public function layout(string $filename, string $namesection) {

		$this->setLayouts('file', $filename);

		if ($namesection != '') {
			// Ejecuta script
			// $this->layout['file'] = $filename;
			$this->seccion_default[0] = $namesection;
			$this->params = array();
			// $this->path_files = dirname($filename);
		}
		else {
			// Desglosa mensajes de error según sea el caso
			if ($filename == '' && $namesection == '') {
				miframe_error('No declaró archivo vista ni sección por defecto');
			}
			elseif ($namesection == '') {
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
			$path = miframe_path($this->path_files, $basename);
			if (file_exists($path)) {
				$this->layout[$name] = $path;
			}
			elseif (!$optional) {
				miframe_error('No encontró archivo vista **$1** ($2)', $basename, $name);
			}
		}
	}

	/**
	 * Define valores por defecto.
	 *
	 * @param array $defaults
	 */
	public function setParams(array $defaults) {
		$this->params = $this->params + $defaults;
	}

	public function setParam(string $name, mixed $value) {
		$this->params[$name] = $value;
	}

	public function showParams(string $title = '', bool $limited = false) {

		if ($title == '') { $title = 'ViewParams'; }
		$salida = '<pre>' .
			htmlspecialchars(miframe_debug_dump($this->params)) .
			'</pre>';

		return miframe_box($title, $salida, 'mute', '', $limited);
	}

	public function param(string $name, mixed $default = '', mixed $options = '') {

		$name = trim($name);

		if ($name == '') { return $default; }

		$valor = '';
		$operador = '';
		$pos = strpos($name, ':');

		if ($pos !== false) {
			$operador = strtolower(trim(substr($name, $pos + 1)));
			$name = trim(substr($name, 0, $pos));
		}

		$continuar = false;
		if (strpos($name, '->') !== false) {
			// Está solicitando datos de un arreglo.
			// Aviso: Si alguno de los valores de llave a buscar contiene "->" genera un
			// resultado no esperado...
			$paths = explode('->', $name);
			if (array_key_exists($paths[0], $this->params)) {
				$valor = $this->params[$paths[0]];
				$total_paths = count($paths);
				for ($i = 1; $i <= $total_paths - 1; $i++) {
					// Continua si el siguiente elemento es un arreglo o en el ultimo elemento, en cuyo caso puede
					// ser cualquier valor.
					$continuar = (is_array($valor) && array_key_exists($paths[$i], $valor));
					if ($continuar) {
						$valor = $valor[$paths[$i]];
					}
					else {
						// No hay coincidencia, suspende
						$valor = '';
						break;
					}
				}
			}
		}
		else {
			// Valor sencillo
			$continuar = array_key_exists($name, $this->params);
			if ($continuar) {
				$valor = $this->params[$name];
			}
		}

		/*if (!$continuar) {
			// En modo DEBUG presenta mensaje
			$this->printDebug(miframe_text('Parámetro/variable "$1" no declarada previamente', $name), miframe_debug_backtrace_info());
		}*/

		if ($continuar && $valor !== '') {

			switch ($operador) {

				case 'text':
					// Remueve tags HTML
					$valor = strip_tags($valor);
					break;

				case 'escape':
				case 'e':
					// Si es una cadena texto, procede. Si es unarreglo, lo hace para cada elemento del arreglo.
					// htmlentities o htmlspecialchars?
					// https://stackoverflow.com/questions/46483/htmlentities-vs-htmlspecialchars
					// (htmlentities) is identical to htmlspecialchars() in all ways, except with htmlentities(), all characters which have HTML
					// character entity equivalents are translated into these entities.
					if (is_string($valor)) {
						$valor = htmlspecialchars($valor);
					}
					elseif (is_array($valor)) {
						// $valor = $this->params[$name];
						foreach ($valor as $k => $v) {
							if (is_string($v)) {
								$valor[$k] = htmlspecialchars($v);
							}
						}
					}
					break;

				case 'uppercase':
				case 'u':
					$valor = strtoupper($valor);
					break;

				case 'lowercase':
				case 'l':
					$valor = strtolower($valor);
					break;

				case 'count':
					if (is_array($valor)) {
						$valor = count($valor);
					}
					else {
						$valor = 0;
					}
					break;

				case 'len':
					if (is_string($valor)) {
						$valor = strlen($valor);
					}
					else {
						$valor = 0;
					}

				case 'date':
					if (is_numeric($valor) && $valor > 0) {
						$valor = date('Y/m/d', $valor);
					}
					// Si no es numerico, mantiene el valor actual
					break;

				case 'bytes':
					if ($valor > 0) {
						$valor = miframe_bytes2text($valor);
					}
					break;

				case 'implode':
					$valor = $this->implodeParams($valor, $options);
					break;

				/* REMOVIDO
				case 'foreach':
					// $options es una función a ejecutar (si retorna TRUE no acumula pero ignora que use $default).
					// array_walk() no se usa porque puede requerirse que retorne un valor
					if (is_array($valor) && count($valor) > 0 && is_callable($options)) {
						$temp = '';
						$usar_default = false;
						foreach ($valor as $k => $v) {
							$item = $options($k, $v);
							if ($item === false) {
								// Cancela operacion y retorna vacio (habilita uso de $default)
								$temp = '';
								$usar_default = true;
								break;
							}
							elseif ($item !== true && $item != '') {
								// Retorna TRUE si está ejecutando pero no retorna texto, posiblemente sale directo a pantalla.
								$temp .= $item;
							}
						}
						$valor = $temp;
						if (!$usar_default) { $default = ''; }
					}
					else {
						// Nada qué hacer
						$valor = '';
					}
					break;
				*/

				case 'bool':
					$valor = ($valor === true
						|| (is_numeric($valor) && $valor > 0)
						|| (is_string($valor) && $valor !== '')
						|| (is_array($valor) && count($valor) > 0)
						);
					break;

				// case 'file':
				// 	break;

				default:
			}
		}

		if ($valor == '') { $valor = $default; }

		return $valor;
	}

	/**
	 * Ejemplos:
	 * <ul>{{ <li>$1</li> }}</ul>
	 * <a href="$2">$1</a>
	 */
	private function implodeParams(mixed $values, string $template) {

		$text = '';
		if (is_array($values) && count($values) > 0) {
			// En este caso, $options contiene el conector o una cadena donde el
			// conector está entre {{ ... }}
			$pre = '';
			$pos = '';
			$ini = strpos($template, '{{');
			if ($ini !== false) {
				$fin = strpos($template, '}}', $ini);
				if ($fin !== false) {
					$pre = substr($template, 0, $ini);
					$pos = substr($template, $fin + 2);
					$template = trim(substr($template, $ini + 2, $fin - $ini - 3));
				}
			}
			foreach ($values as $k => $v) {
				if ($v != '') {
					$text .= str_replace(array('$1', '$2'), array($v, $k), $template);
				}
			}
			if ($text != '' && ($pre != '' || $pos != '')) {
				// Complementa salida
				$text = $pre . $text . $pos;
			}
			// $valor = $this->template($template,
			// 	function($matches) use ($valor) {
			// 		return implode(str_replace(array('{{', '}}'), '', $matches[0]), $valor);
			// 	});
		}

		return $text;
	}

	public function enumParams(string $pre, array $data, string $pos = '', string $empty_text = '') {

		$text = '';
		foreach ($data as $param => $template) {
			$valor = $this->param($param);
			if (is_array($valor) && count($valor) > 0) {
				// En este caso, $template es el usado por "implode"
				$text .= $this->implodeParams($valor, $template);
			}
			elseif ($valor != '') {
				$text .= trim(str_replace('$1', $valor, $template));
			}
		}
		if ($text != '') {
			// Complementa salida con textos pre/pos
			$text = $pre . $text . $pos;
		}
		else {
			// Texto alternativo a usar si no hay nada que mostrar
			$text = $empty_text;
		}

		return $text;
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
			$salida = "\n\n<!-- START MiFrame.section " . htmlspecialchars($namesection) . " -->\n\n" .
				$salida .
				"\n\n<!-- END MiFrame.section " . htmlspecialchars($namesection) . " -->\n\n";
		}

		/* PENDIENTE
		if ($this->debug) {
			$salida = "\n\n<div style=\"border:1px solid #0969da;margin:1px;padding:1px\">" .
				"<div style=\"background:#0969da;color:#fff;font-size:12px;padding:1px 4px;\">".
				"<b>DEBUG " . get_class() . '</b> - ' . htmlspecialchars($namesection) . "</div>\n\n" .
				$salida .
				"\n\n</div>\n\n";
		}
		*/

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
				$this->secciones[$uname] .= $conector;
			}
			$this->secciones[$uname] .= trim($content);
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
	public function capture(string $filename = '', array $params = array(), string $namesection = '') {

		$this->params = $params + $this->params;

		// Por comodidad, permite definir una seccion en $filename iniciando con ":"
		if (substr($filename, 0, 1) == ':' && $namesection == '') {
			$namesection = trim(substr($filename, 1));
			$filename = '';
		}

		if ($filename != '') {
			// Captura desde un archivo en el directorio de vistas
			$path = miframe_path($this->path_files, $filename);
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
				$this->abort(
					miframe_text('Archivo no encontrado'),
					miframe_text('No encontró archivo vista "$1"', $path, debug:$this->path_files)
					);
			}
		}
		else {
			// Habilita captura de salida a pantalla directamente hasta la finalización del script
			// o hasta ejecutar stop().
			$this->start($namesection);
		}

	}

	public function start(string $namesection) {

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

	// Operadores soportados para condicion: :numeric, :text, :count, :bool
	// Puede indicar valor a mostrar con {{ xxxx }} para evitar recargar la memoria
	public function iif(mixed $condicion, string $true_text, string $false_text = '') {

		// Si la confición es un string, valida solicitud
		if (is_string($condicion)) {

			$name = $condicion;
			$condicion = false;
			$valor = $this->param($name);
			$operador = '';
			$negar = false;

			$pos = strpos($name, ':');
			if ($pos !== false) {
				$operador = strtolower(trim(substr($name, $pos + 1)));
				$name = substr($name, 0, $pos);
				$negar = (substr($operador, 0, 1) === '!');
				if ($negar) {
					$operador = trim(substr($operador, 1));
				}
			}

			switch ($operador) {

				case 'empty':
					// Elementos vacios
					if (is_array($valor)) {
						$condicion = (count($valor) <= 0);
					}
					elseif (is_string($valor)) {
						$condicion = ($valor === '');
					}
					elseif (is_numeric($valor)) {
						$condicion = ($valor === 0);
					}
					break;

				case 'file':
					$condicion = false;
					if (is_string($valor) && $valor != '') {
						$condicion = file_exists($valor);
					}
					break;

				// Fecha en formato numerico (mejorar)
				/*case 'is_date':
					if (is_numeric($valor)) {
						$condicion = ($valor > 0);
					}
					break;*/

				default:
					$condicion = ($valor != false);
			}

			if ($negar) { $condicion = !$condicion; }
		}

		$retornar = ($condicion) ? $true_text : $false_text;

		return $this->template($retornar);
	}

	// Ej: <div>{{ param }} </div>
	public function template(string $template, callable $fun = null) {

		// Valida si contiene {{ ... }}
		if (strpos($template, '{{') !== false) {
			if (is_null($fun)) {
				// Define función estandar
				$fun = function($matches) {
					return $this->param(str_replace(array('{{', '}}'), '', $matches[0]), $matches[0]);
				};
			}
			$regexp = "/\{\{.*?\}\}/";
			$template = preg_replace_callback($regexp, $fun, $template);
		}

		return $template;
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
	public function once(string $name) {

		$name = 'MIFRAME_BOOL_' . strtoupper(miframe_only_alphanum($name));
		$valor = !defined($name);
		if ($valor) { define($name, true); }

		return $valor;
	}

	public function abort(string $title, string $message) {

		$namesection = $this->sectionDefault(); // Valor por defecto siempre
		$pre = $this->get($namesection);
		$this->put($namesection, '');

		if ($this->layout['file-error'] != '') {
			$this->capture($this->layout['file-error'], [ 'title' => $title, 'message' => $message, 'pre' => $pre ]);
		}
		else {
			// Si no pudo ejecutar lo anterior, presenta mensaje base
			if ($pre != '') {
				$pre = "<p><b>Contenido previo</b></p><pre>" . htmlspecialchars($pre) . "</pre>";
			}
			// Mensaje con error a pantalla
			$this->put($namesection, $this->sprintf("<h1>$title</h1>\n<p>$message</p>", $pre));
		}

		$this->cancelLayout();

		exit;
	}

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

	private function getConfigView(array $data, string $param, string $default = '') {

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
	 * 		[micode-default]
	 * 		name = (Título para esta vista, a usar en administraciones de plantillas)
	 * 		layout = "layout.php"
	 * 		main-section = "contenido"
	 * 		tpl-error = "error.php"
	 * 		tpl-default = "default.php"
	 * 		...
	 *
	 * @param string $filename Nombre del archivo .ini a cargar,
	 * @param string $name Indica si debe escoger solamente un grupo a procesar del archivo .ini
	 * @param bool $rewrite TRUE para remplazar enrutamientos existentes. FALSE adiciona a los ya registrados.
	 * @param string $dirbase Path a usar para ubicar los scripts.
	*/
	public function loadConfig(string $filename, string $name = '') {

		if (!file_exists($filename)) {
			miframe_error('Archivo no encontrado: $1', $filename);
		}

		$data = parse_ini_file($filename, true, INI_SCANNER_RAW);

		$name = strtolower(trim($name));
		// Si $name = '', toma el primero de la lista
		if ($name == '' && count($data) > 0) {
			$llaves = array_keys($data);
			$name = $llaves[0];
		}
		if ($name != '' && isset($data[$name])) {

			$data = $data[$name];
			// Define valores para la plantilla a usar.
			// LO hace antes de evaluar enrutamiento para que los scripts llamados reutilicen esta configuración.
			$this->layout(
				$this->getConfigView($data, 'layout'),
				$this->getConfigView($data, 'main-section')
				);

			// Plantilla para reporte de errores
			$this->layoutError($this->getConfigView($data, 'tpl-error'));

			// Plantilla por defecto a usar si no encuentra la invocada por $view->capture()
			$this->layoutDefault($this->getConfigView($data, 'tpl-default'));

			// Carga subrutinas de salida a pantalla
			// $this->expandWith($this->getConfigView($data, 'expand-with'));
		}
		else {
			miframe_error('Configuración no encontrada para la vista $1', $name);
		}
	}
}
