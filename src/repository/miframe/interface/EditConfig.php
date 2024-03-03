<?php
/**
 * Gestor de opciones de configuración para los módulos del sistema miFrame.
 * Las opciones se configuran mediante un archivo .ini con la siguiente estructura
 * (el nombre del grupo corresponde al nombre del parámetro, que debe ser único en
 * todo el sistema):
 *
 *     [php-errorlog-size]
 *
 *     ; Modulo que lo requiere, vacio para todos los modulos (por defecto)
 *     module = miframe/common/phpsettings
 *
 *     ; Titulo del control
 *     title = "Tamaño de PHP Error log"
 *
 *     ; Mensaje de ayuda en pantalla
 *     help = Ejemplo: 2M
 *
 *     ; Tipo de dato. Opciones:
 *     ; - text (por defecto)
 *     ; - textarea
 *     ; - readonly[:fun]. fun($value) representa una función a aplicar sobre el valor a retornar.
 *     ;                   Ej: "strtoupper" equivale a strtoupper($value) y retorna valor en mayusculas.
 *     ; - select:fun. fun() Retorna opciones validas (sin parámetros).
 *     ; - checkbox[:fun]. fun() Retorna opciones validas para listados. Si no se indica, usa un checkbox sencillo.
 *     ; - radio:fun. fun() Retorna opciones validas.
 *     ; - password
 *     ; - email
 *     ; - numeric
 *     ; - fun:function. function($name, $value) es la función a usar para generar el HTML del control deseado.
 * 	   ; - file: Los datos se guardan externamente en el mismo path del .ini (documentar...)
 * 	   ; - textarray: Es un textarea que se lee/guarda como un arreglo. Por ejemplo, m[]=a,m[]=b se edita en un
 *     ;   textarea como "a\nb" y al recibir se interpreta de nuevo como arreglo. (PENDIENTE)
 *     type=text
 *
 *     ; Función para validar valor ingresado. Retorna mensaje si ocurre error. Ej: fun(&$value) { ... }
 *     fun-validate=
 *
 *     ; Valor por defecto (sólo para la creación del .ini)
 *     default=
 *
 *     ; Comentario adicional a incluir en el archivo INI (también adicionan como comentario "title" y "help")
 *     ini-comment =
 *
 *     ; Grupo a mostrar en edición
 *     group =
 *
 * @micode-uses miframe-common-functions
 * @author John Mejia
 * @since Mayo 2022.
 */

namespace miFrame\Interface;

/**
 * Las siguientes propiedades públicas pueden ser usadas:
 * - $createDirbase; boolean. TRUE para crear el directorio asociado al archivo .ini.
 * - $debug: boolean. TRUE para incluir mensajes de depuración.
 */

class EditConfig {

	private $file_data = array();
	private $config = array();
	// private $ignorar_actualizaciones = false;
	private $comentario = array();
	private $orden = 0;
	private $help_args = array();
	private $validators = array();
	private $config_files = array();
	private $mensajes = array();
	private $total_guardados = 0;

	public $createDirbase = false;
	public $debug = false;

	// public function __construct() {}

	private function groupData(string $name) {

		return $this->getConfigAttrib($name, '#namecfg', '');
	}

	/**
	 * Adiciona constantes a usar para validaciones al cargar datos de .inis.
	 */
	public function addValidator(string $name, bool $value) {

		$this->validators[$name] = $value;
	}

	/**
	 * Adiciona constantes a usar en los mensajes de ayuda.
	 */
	public function addHelper(string $name, string $value) {

		$this->help_args[$name] = $value;
	}

	/**
	 * Adiciona archivo .ini con las opciones de configuración.
	 * Puede adicionarse más de un archivo de ser necesario, para crear el .ini con datos para el usuario.
	 * Se recomienda definir estos elementos antes de realizar el cargue de datos del usuario.
	 *
	 * @param string $namecfg Nombre asociado. Varios archivos de configuración pueden tener asociado el
	 *        mismo nombre, de forma que los datos se guarden en un único archivo .ini.
	 * @param string $filename Path del archivo .ini.
	 * @return bool TRUE si el archivo existe y pudo ser adicionado, FALSE en otro caso.
	 */
	public function addConfigFile(string $namecfg, string $filename, bool $force_readonly = false) {

		$namecfg = strtolower(trim($namecfg));
		$filename = trim($filename);
		$resultado = false;

		if ($namecfg != '' && $filename != '' && file_exists($filename)) {
			// Guarda datos de llaves en minusculas
			$data = parse_ini_file($filename, true, INI_SCANNER_TYPED);
			foreach ($data as $name => $info) {
				// $info DEBE ser un arreglo
				if (is_array($info)) {
					if ($this->setConfig($namecfg, $name, $info)) {
						if ($force_readonly) {
							$this->setConfigParam($name, 'type', 'readonly');
						}
					}
				}
			}
			// Registra archivo de configuración (el arreglo se declara en $this->setConfig() si hay
			// parámetros validos para registrar)
			$this->config_files[] = array('name' => $namecfg, 'path' => $filename, 'params' => array_keys($data));
			$resultado = true;
		}

		return $resultado;
	}

	public function getConfigFiles(bool $full = false) {

		if (!$full) {
			$archivos = array();
			foreach ($this->config_files as $info) {
				$archivos[] = $info['path'];
			}
			return $archivos;
		}

		// Retorna toda la info de config_files
		return $this->config_files;
	}

	/**
	 * Asigna valor a un elemento de configuración.
	 * Si el elemento ya existe, solamente lo modifica si pertenece al mismo grupo de configuración.
	 *
	 * @param string $name Nombre del elemento.
	 * @param array $data Arreglo con los datos (title, help, ini-comment, ...)
	 * @return bool TRUE si pudo asignar el valor, FALSE en otro caso.
	 */
	public function setConfig(string $namecfg, string $name, array $data) {

		$resultado = false;
		$name = strtolower(trim($name));
		$namecfg = strtolower(trim($namecfg));
		$grupo = $this->groupData($name);
		// Si ya existe, solo la modifica si coincide con el valor previo de $namecfg.
		// Caso contrario ignora el cambio y genera error.
		if ($grupo != '' && $grupo != $namecfg) {
			miframe_error('El parámetro "$1/$2" fue ya declarado en el grupo "$3"', $namecfg, $name, $grupo);
		}
		if ($namecfg != '') {
			// if (!isset($data['type'])) { $data['type'] = 'text'; }
			foreach ($data as $k => $v) {
				$k = strtolower(trim($k));
				// Si $k contiene "?" es del tipo [validator]?[$k] y se asigna como "k"
				// si existe $validators[$k] != false.
				// Importante: Los $name con validator deben declararse después del $name sin validator (u omitirse
				// este último).
				$pos = strpos($k, '?');
				if ($pos !== false) {
					$valida = trim(substr($k, 0, $pos));
					if (array_key_exists($valida, $this->validators)
						&& $this->validators[$valida] !== false
						) {
						$k = trim(substr($k, $pos + 1));
					}
					else {
						// No procede, ignora este elemento
						continue;
					}
				}
				if ($k != '') {
					$this->setConfigParam($name, $k, $v);
				}
			}
			// Registra grupo asociado a $name
			$this->config[$name]['#namecfg'] = $namecfg;
			// Valor por defecto si no ha cargado datos
			if (!isset($this->config[$name]['#value'])
				&& !isset($this->file_data[$namecfg])
				&& isset($this->config[$name]['default'])
				) {
				$valor = $this->config[$name]['default'];
				// Casos especiales
				if ($valor == 'now()') {
					$valor = date('Y-m-d');
				}
				$this->config[$name]['#value'] = $valor;
			}
			// Valida si existen tipo archivo
			if (isset($this->config[$name]['type'])
				&& $this->config[$name]['type'] == 'file'
				&& isset($this->file_data[$namecfg])
				) {
				// Si hay datos tipo "file" procede a cargar la información
				$filename = miframe_path(dirname($this->file_data[$namecfg]['file']), $name . '.ini');
				if (file_exists($filename)) {
					$contenido = file_get_contents($filename);
					// Remueve comentarios al inicio y final si aplican?
					$this->config[$name]['#value'] = trim($contenido);
				}
			}

			$this->config[$name]['#orden'] = $this->orden;
			$this->orden ++;

			$resultado = true;
		}

		return $resultado;
	}

	public function setConfigParam(string $name, string $param, string $value) {

		$name = strtolower(trim($name));
		$param = strtolower(trim($param));
		if ($name != '' && $param != '') {
			$this->config[$name][$param] = trim($value);
		}
	}

	public function removeConfig(string $name) {

		$resultado = false;
		$name = strtolower(trim($name));
		if (isset($this->config[$name])) {
			unset($this->config[$name]);
			$resultado = true;
		}

		return $resultado;
	}

	/**
	 * Recupera título de un elemento de configuración.
	 *
	 * @param string $name Nombre del elemento.
	 * @return string Título (si no existe retorna el mismo nombre del elemento).
	 */
	public function getTitle(string $name) {

		$titulo = $this->getConfigAttrib($name, 'title', '');
		if ($titulo == '') {
			$titulo = ucfirst($name);
		}

		return $titulo;
	}

	public function getConfigAttrib(string $name, string $attrib, mixed $default = false) {

		if (isset($this->config[$name])
			&& array_key_exists($attrib, $this->config[$name])
			&& !is_null($this->config[$name][$attrib])
			) {
			$default = $this->config[$name][$attrib];
		}

		return $default;
	}

	/**
	 * Reporta cambios a los datos del usuario.
	 *
	 * @return bool TRUE si hay cambios pendientes por guardar.
	 */
	public function unsaved(string $namecfg) {

		$retornar = false;
		$namecfg = strtolower(trim($namecfg));
		// print_r($this->file_data[$namecfg]); echo " -- filedata<hr>"; print_r($this->config); exit;
		// Valida si existen datos capturados
		if (count($this->config) > 0 && isset($this->file_data[$namecfg])) {
			$retornar = (count($this->file_data[$namecfg]['modified']) > 0);
		}

		return $retornar;
	}

	/**
	 * Adiciona comentario general para el archivo .ini del usuario.
	 * @param string $text Comentario.
	 */
	public function commentData(string $namecfg, string $text) {

		$namecfg = strtolower(trim($namecfg));
		$this->comentario[$namecfg] = $text;
	}

	public function clearData() {

		foreach ($this->config as $name => $info) {
			if (isset($info['#value'])) {
				unset($this->config[$name]['#value']);
			}
		}
	}

	/**
	 * Carga archivo .ini con los datos previamente registrados por el usuario.
	 * Si el archivo .ini no existe, asume es un archivo de datos a ser creado como nuevo. En este caso,
	 * automáticamente adiciona los valores por defecto definidos en los elementos de configuración.
	 *
	 * @param string $filename Path del archivo .ini.
	 * @return bool TRUE si el archivo existe y pudo ser adicionado, FALSE en otro caso.
	 */
	public function loadData(string $namecfg, string $filename) {

		$namecfg = strtolower(trim($namecfg));
		$this->file_data[$namecfg] = array('file' => '', 'modified' => array());

		$filename = trim($filename);

		if ($namecfg != '' && $filename != '') {
			if (file_exists($filename)) {
				$this->file_data[$namecfg]['file'] = $filename;
				$data = parse_ini_file($filename, true, INI_SCANNER_TYPED);
				// No registra esta carga como cambios en los datos
				// Guarda datos de llaves
				$this->setDataValues($data, true);
			}
			else {
				// Puede ser un archivo nuevo, valida que el directorio destino exista
				$inidir = dirname($filename);
				if (miframe_mkdir($inidir, $this->createDirbase)) {
					$this->file_data[$namecfg]['file'] = $filename;
				}
			}
		}

		// debug_box($this->data, 'PRE ' . $filename);

		return ($this->file_data[$namecfg]['file'] != '');
	}

	/**
	 * Retorna los datos del usuario.
	 *
	 * @return array Arreglo de datos.
	 */
	public function getValues(string $namecfg = '', mixed $ignore_names = false) {

		$arreglo = array();
		foreach ($this->config as $name => $info) {
			// Valida si define items a ignorar
			if (is_array($ignore_names) && in_array($name, $ignore_names)) { continue; }
			// Solamente guarda los datos asociados a este grupo
			if ($namecfg != '' && $this->groupData($name) !== $namecfg) { continue; }
			$arreglo[$name] = $this->getSingleValue($name);
		}
		return $arreglo;
	}

	public function getSingleValue(string $name) {

		return $this->getConfigAttrib($name, '#value', '');
	}

	// return true/false/texto
	// false - significa que no fue modificado,, true - fue modificado, texto - mensaje de error
	public function setDataValue(string $name, mixed $value, bool $ignore_changes = false) {

		$name = strtolower(trim($name));
		if (!is_array($value) && !is_null($value)) {
			$value = trim($value);
		}

		$pre = $this->getSingleValue($name);
		$actualizar = ($pre !== $value);
		if ($actualizar) {
			// Que no exista $this->config[$name] no impide que asigne el valor y lo guarde en el INI.

			if (isset($this->config[$name]) && isset($this->config[$name]['fun-validate'])) {
				$fun = $this->config[$name]['fun-validate'];
				$resultado = '';
				if ($fun != '') {
					if (!function_exists($fun)) {
						miframe_error('La función "$2" indicada para validar "$1" no existe', $name, $fun);
					}
					$resultado = $fun($value);
					if ($resultado != '') {
						// Ocurrió un error
						return $resultado;
					}
				}
			}

			// if (!$ignore_changes) { echo "$name : <pre>---\n$pre\n---\n$value\n---</pre><hr>";
			// 	var_dump($pre); var_dump($value);
			// }

			$this->config[$name]['#value'] = $value;
			// if (!$this->ignorar_actualizaciones && !$ignore_changes) {
			if (!$ignore_changes) {
				// $this->config[$name]['#modified'] = true;
				// Actualiza marca global
				$namecfg = $this->config[$name]['#namecfg'];
				$this->file_data[$namecfg]['modified'][$name] = true;
			}
		}

		return true;
	}

	public function setDataValues(array $data, bool $ignore_changes = false) {
		// Guarda datos de llaves en minusculas
		foreach ($data as $k => $v) {
			$this->setDataValue($k, $v, $ignore_changes);
		}

	}

	public function getNames(string $namecfg = '', mixed $ignore_names = false) {

		$arreglo = array();
		if ($namecfg == '' && !is_array($ignore_names)) {
			// Retorna todas las llaves
			$arreglo = array_keys($this->config);
		}
		else {
			// Debe retornar llaves filtradas
			foreach ($this->config as $name => $info) {
				// Valida si define items a ignorar
				if (is_array($ignore_names) && in_array($name, $ignore_names)) { continue; }
				// Solamente guarda los datos asociados a este grupo
				if ($this->groupData($name) !== $namecfg) { continue; }
				// Preserva nombre
				$arreglo[] = $name;
			}
		}

		return $arreglo;
	}

	public function putData(string $namecfg, mixed $ignore_names = false, bool $nosave = false) {

		$namecfg = strtolower(trim($namecfg));
		// Valida si existen datos capturados
		if (!$this->unsaved($namecfg) || $this->getFilename($namecfg) == '') {
			return false;
		}

		$arreglo = array();
		// Adiciona comentario global
		if (isset($this->comentario[$namecfg])) {
			$comentario = trim($this->comentario[$namecfg]);
			if ($comentario != '') {
				$arreglo[';'] = $comentario;
			}
		}

		$names = $this->getNames($namecfg, $ignore_names);
		$archivos = array();

		foreach ($names as $name) {

			$name = trim(strtolower($name));
			$ctlname = $this->formName($name);
			$info = $this->config[$name];

			// Define tipo a usar por defecto
			$arreglotipo = $this->getTypeData($info);
			$tipo = $arreglotipo['type']; // Tipo

			// Comentarios asociados al elemento
			$comentario = '';
			if (isset($info['title'])) {
				$comentario = $info['title'];
			}
			if (isset($info['help'])) {
				if ($comentario != '') { $comentario .= PHP_EOL; }
				$ayuda = miframe_text($info['help'], ...$this->help_args);
				$comentario .= str_replace("\\n", PHP_EOL, $ayuda);
			}
			if (isset($info['ini-comment'])) {
				if ($comentario != '') { $comentario .= PHP_EOL; }
				$comentario .= str_replace("\\n", PHP_EOL, $info['ini-comment']);
			}

			if ($tipo == 'file') {
				/*
				if ($comentario != '') {
					$comentario = '; ' . miframe_inifiles_comment_format($comentario) . PHP_EOL . PHP_EOL;
				}
				$archivos[$name] = $comentario .
					$this->dataValue($name) . PHP_EOL . PHP_EOL .
					// Adiciona comentarios de control
					'; Creado en ' . date('Y/m/d H:i:s') . PHP_EOL;
				*/
				$archivos[$name] = $this->getSingleValue($name);
			}
			else {
				if ($comentario != '') {
					$arreglo['; ' . $name] = $comentario;
				}
				// Valor del elemento
				$arreglo[$name] = $this->getSingleValue($name);
			}
		}

		// Guarda arreglos en data que no hayan sido guardados aun
		// y que corresponden al grupo indicado
		/* Habilitar si se deben guardar o capturar al cargar datos su origen
		$otros_marca = false;
		foreach ($this->data as $name => $valor) {
			$name = trim(strtolower($name));
			// Valida si define items a ignorar
			if (is_array($ignore_names) && in_array($name, $ignore_names)) { continue; }
			if (!array_key_exists($name, $arreglo)) {
				if (!$otros_marca) {
					$arreglo[';'.$name] = miframe_text('Valores adicionados por el usuario') . PHP_EOL;
					$otros_marca = true;
				}
				$arreglo[$name] = $valor;
			}
		}
		*/

		$resultado = true;

		if (count($archivos) > 0) {
			$path = dirname($this->file_data[$namecfg]['file']);
			if ($nosave) {
				miframe_debug_box($archivos, 'INIFILE/NOSAVE:FILES ' . $path);
			}
			else {
				foreach ($archivos as $name => $contenido) {
					$filename = miframe_path($path, $name . '.ini');
					if (strlen($contenido) > 0) {
						// Hay algo para guardar
						if (!@file_put_contents($filename, $contenido)) {
							return false;
						}
					}
					elseif (file_exists($filename)) {
						// El archivo existe, lo elimina (remueve posibles valores previos)
						if (!@unlink($filename)) {
							return false;
						}
					}
				}
			}
		}

		$contenido = miframe_inifiles_format_data($arreglo);
		if ($nosave) {
			miframe_debug_box($contenido, 'INIFILE/NOSAVE ' . $this->file_data[$namecfg]['file']);
		}
		elseif (miframe_inifiles_save_data_raw($this->file_data[$namecfg]['file'], $contenido)) {
			// Remueve los valores ya actualizados
			$this->file_data[$namecfg]['modified'] = array();
			// Totaliza archivos guardados
			$this->total_guardados ++;
			if ($this->debug) {
				// Muestra en pantalla valores guardados
				miframe_debug_box($contenido, 'INIFILE ' . $this->file_data[$namecfg]['file']);
			}
		}
		else {
			// Falló al guardar datos
			$resultado = false;
		}

		return $resultado;
	}

	public function putAllData() {

		$resultado = true;
		foreach ($this->file_data as $namecfg => $filename) {
			if (!$this->putData($namecfg)) {
				$resultado = false;
			}
		}

		return $resultado;
	}

	public function getFilename(string $namecfg) {

		$filename = '';
		$namecfg = strtolower(trim($namecfg));
		if (isset($this->file_data[$namecfg]['file'])) {
			$filename = $this->file_data[$namecfg]['file'];
		}

		return $filename;
	}

	/**
	 * Genera nombre a usar en formularios para edición de un elemento.
	 * El nombre se enmascara para prevenir sea revelado a terceros en los formularios.
	 *
	 * @param string $name Nombre del elemento.
	 * @return string Nombre.
	 */
	public function formName(string $name) {

		$name = strtolower(trim($name));
		return miframe_mask($name, 'cfg');
	}

	private function getTypeData(array $info) {
		// Define tipo a usar por defecto
		$tipo = 'text';
		if (isset($info['type'])) {
			$tipo = $info['type'];
		}
		$arreglo = explode(':', $tipo . '::');
		$tipo = strtolower(trim($arreglo[0])); // Tipo
		$fun = trim($arreglo[1]); // Fun

		return array('type' => $tipo, 'fun' => $fun);
	}

	public function getFormData() {

		// Valida si existen datos capturados
		if (count($this->config) <= 0) { return false; }

		// debug_box($this->config);

		$form = array();

		// Organiza arreglo por grupos
		$listagrupos = array('-' => array());
		$llaves = array();

		foreach ($this->config as $name => $info) {
			// Ignora elementos sin título (pueden ser elementos sin un config asociado)
			if (!isset($info['title']) || $info['title'] == '') {
				continue;
			}
			// Ordena en el orden en que se ingresan los parámetros del config (#orden)
			$titulo = sprintf('%04d', $info['#orden']);
			if (isset($info['group']) && $info['group'] != '') {
				$grupo = $info['group'];
				$listagrupos[$grupo][$name] = $titulo;
			}
			else {
				$listagrupos['-'][$name] = $titulo;
			}
		}

		ksort($listagrupos);

		foreach ($listagrupos as $grupo => $infogrupo) {

			asort($infogrupo);

			foreach ($infogrupo as $name => $titulo) {

				$info = $this->config[$name];
				$name = trim(strtolower($name));
				$ctlname = $this->formName($name);
				$valor = $this->getSingleValue($name);
				$control = '';

				$form[$name] = array();
				$form[$name]['value'] = $valor;
				$form[$name]['title'] = $info['title'];

				if ($grupo != '-' && $grupo != '') {
					$form[$name]['group'] = $info['group'];
					$grupo = ''; // Previene lo registre de nuevo
				}

				// Define tipo a usar por defecto
				$arreglo = $this->getTypeData($info);
				$form[$name]['type'] = $arreglo['type']; // Tipo
				$fun = $arreglo['fun']; // Fun
				$form[$name]['optional'] = false;
				if (isset($info['optional'])) {
					$form[$name]['optional'] = $info['optional'];
				}

				switch ($form[$name]['type']) {

					case 'private':
						// No muestra en pantalla
						// debug_pause('Ajustar para guardar a nivel de "src" no editable');
						unset($form[$name]);
						// Warning: "continue" targeting switch is equivalent to "break".
						// Did you mean to use "continue 2"?
						continue 2;

					case 'local':
						// Este tipo de dato se registra en el directorio "data" al mismo nivel de "src"
						// en el proyecto, por ej. para el nombde a usar para el destino de los modulos
						// y que por defecto es "micode". Usado para personalizaciones.
						// Puede usar "local:nombrefuncion" como en "readonly"...
						// debug_pause('Ajustar para guardar a nivel de "src", editable');
						break;

					case 'readonly':
						// Solo permite editar si no existe
						if ($fun != '' && function_exists($fun)) {
							$valor = $fun($valor);
							if (is_array($valor)) {
								$valor = implode(', ', $valor);
							}
						}
						$control = '<div class="form-control"><b>' . htmlspecialchars($valor) . '</b></div>';
						break;

					case 'textarea':
					case 'file': // Al guardar/leer se almacena en config/[param].ini

						$control = '<textarea name="' . $ctlname . '" class="form-control" rows="5" wrap="soft">' . htmlspecialchars($valor) . '</textarea>';
						break;

					case 'boolean':

						$opciones = '';
						if ($fun == '') {
							$opciones = array(0 => 'No', 1 => 'Si');
						}

					case 'select':

						if (!isset($opciones)) { $opciones = ''; }
						$modulo = 'common';
						if ($fun != '') {
							if (!function_exists($fun) && isset($info['module']) && $info['module'] != '')  {
								// Busca archivo include esperado con las funciones para este elemento
								$modulo = $info['module'];
								$include = miframe_path(__DIR__, '..', '..', 'data', $modulo . '.php');
							}
							if (function_exists($fun)) {
								$opciones = $fun();
							}
						}
						// Campo obligatorio para "select"
						if (!is_array($opciones)) {
							// debug_box($opciones, $fun);
							miframe_error('Debe indicar una función valida que retorne las opciones a usar para "$1" ($2)', $name, $modulo);
						}

						$form[$name]['options'] = $opciones;
						// Consideración especial si el valor actual no existe en la lista
						if (!isset($opciones[$valor])) {
							$valor_info = $valor;
							if ($valor == '') { $valor_info = miframe_text('No asignado'); }
							$opciones[$valor] = '(' . $valor_info . ')';
						}

						$select = '';
						foreach ($opciones as $k => $v) {
							$checked = '';
							if ($k == $valor) { $checked = ' selected'; }
							$select .= '<option value="' . $k . '"' . $checked . '>' . htmlspecialchars($v) . '</option>';
						}

						if ($select != '') {
							$control = '<select name="' . $ctlname . '" class="form-control">' . $select . '</select>';
						}
						break;

					default:
						// Texto
						$control = '<input type="text" name="' . $ctlname . '" value="' . htmlspecialchars($valor) . '" class="form-control">';
				}

				if ($control != '') {
					$form[$name]['html'] = $control;
				}

				// Define tipo a usar por defecto
				if (isset($info['help']) && $info['help'] != '') {
					$ayuda = miframe_text($info['help'], ...$this->help_args);
					$form[$name]['help'] = nl2br($ayuda);
				}
			}
		}

		if ($this->debug) {
			miframe_debug_box($form, 'Formulario');
		}

		return $form;
	}

	public function formSubmitted(string $post_param) {

		return (isset($_REQUEST[$post_param]) && $_REQUEST[$post_param] !== '');
	}

	// true hay cambios, false no hay cambios, otro errores
	public function checkformRequest(string $post_param) {

		// TRUE si existe el parámetro de control (usualmente asociado al botón "Guardar" o similar)
		$resultado = $this->formSubmitted($post_param);

		if ($resultado) {
			foreach ($this->config as $name => $info) {
				// if (is_array($ignore) && in_array($name, $ignore)) { continue; }
				$cfgname = $this->formName($name);
				if (isset($_REQUEST[$cfgname])) {
					// Limpia valores recibidos
					$request_value = $_REQUEST[$cfgname];
					if (!is_array($request_value)) {
						// Se asegura que las cadenas de texto se almacenen correctamente para evitar
						// que se detecten cambios donde no los hay (especialmente en el manejo de los
						// EOL).
						$request_value = trim(str_replace(
							array("\r", "\n"),
							array('', PHP_EOL),
							$request_value
							));
					}
					$res = $this->setDataValue($name, $request_value);
					// false - significa que no fue modificado, true - fue modificado, texto - mensaje de error
					if ($res === true) {
						// Mantiene el valor actual de $resultado
						// $resultado = true;
					}
					elseif ($res !== false) {
						// Ocurrió un error, $resultado contiene el mensaje con detalles del mismo.
						// Fija $resultado a false para que no procese los datos.
						$titulo = $this->getTitle($name);
						$this->setMessage($titulo . ': ' . $res);
						$resultado = false;
					}
				}
				// Valida campos requeridos
				$valor = $this->getSingleValue($name);
				// print_r($this->config[$name]); echo "<hr>";
				// Por defecto todos los campos son opcionales
				if ((array_key_exists('optional', $info) && intval($info['optional']) <= 0)
					&& ($valor == '' || $valor == false)
					) {
					if (isset($info['title']) && $info['title'] != '') {
						$this->setMessage(miframe_text('Valor requerido para **$1**', $info['title']), $name);
					}
					else {
						$this->setMessage(miframe_text('Valor requerido para atributo **$1** (sín titulo asociado)', $name), $name);
					}
					$resultado = false;
				}
			}
		}

		return $resultado;
	}

	public function setMessage(string $text, string $index = '') {

		$text = trim($text);
		if ($text != '') {
			if ($index !== '') {
				// Indica llave a usar para mensaje (permite modificarlo posteriormente)
				$this->mensajes[$index] = $text;
			}
			else {
				$this->mensajes[] = $text;
			}
		}
	}

	public function getMessages() {

		return $this->mensajes;
	}

	public function existsMessages() {

		return (count($this->mensajes) > 0);
	}

	public function nothingSaved() {

		return ($this->total_guardados <= 0);
	}
}