<?php
/**
 * Generador de documentación de código a partir de los bloques de comentarios
 * incluidos en el código.
 *
 * Funciona con base en el modelo Javadoc adaptado según se describe en phpDocumentor
 * (https://docs.phpdoc.org/guide/guides/docblocks.html), donde se documenta en bloques de comentario dentro del script,
 * las clases y/o las funciones contenidas en el mismo.
 * Algunos tags a tener en cuenta:
 * (Referido de https://docs.phpdoc.org/guide/guides/docblocks.html)
 *
 * - @author: Nombre del autor del elemento asociado.
 * - @link: Relación entre el elemento asociado y una página web (referencias).
 * - @param: (Sólo para funciones, métodos) Cada argumento de una función o método, uno por tag.
 * - @return: (Sólo para funciones, métodos) Valor retornado por una función o método.
 * - @since: Indica en qué versión el elemento asociado estuvo disponible.
 * - @todo: Actividades o mejoras por realizar al elemento asociado.
 * - @uses: Indica referencias a otros elementos.
 * - @version: Versión actual del elemento estructural (a nivel de script más que de funciones o métodos).
 *
 * Tener presente que En PHP el bloque documento va antes de la definición de la función/clase. En lenguajes como Python va después.
 *
 * En caso de encontrar documentación faltante, se reportan en el arreglo de salida agrupados bajo el item "errors".
 *
 * @micode-uses miframe-common-functions
 * @micode-uses miframe-file-serialize
 * @author John Mejia
 * @since Abril 2022
 */

namespace miFrame\Utils\DocSimple;

/**
 * Clase para obtener la documentación del código a partir de los bloques de comentarios.
 * Las siguientes propiedades públicas pueden ser usadas:
 *
 * - $tags:  array. Atributos para evaluar documentación. Se predefine en el __construct() de la clase para soportar el modelo PHP Javadoc.
 * - $debug: boolean. TRUE para incluir mensajes de depuración.
 * - $evalRequiredItems: boolean. TRUE para evaluar elementos mínimos requeridos. Se reportan en el arreglo de salida agrupados
 * 		bajo el item "errors".
 */
class DocSimple {

	private $tipodoc = '';
	private $interpreter = array();
	private $cache = array();

	protected $tags = array();
	private $tags_fun = array();

	public $debug = false;
	public $evalRequiredItems = true;
	public $serializeFunction = '';
	public $unserializeFunction = '';

	public function __construct() {

		// Elementos para detectar el bloque de documentación en el código. Por defecto se define para PHP.

		// $this->regex_comment = "/^(\/\/|#)(.*)/";
		// $this->regex_assoc = "/^(class|private function|public function|protected function|function)[\s\n](.*)/";
		// $this->regex_eval = array(
		// 	'class' => array("/^(\S+)?([\s\n].*)\{(.*)/", " $1"),
		// 	'*' => array("/^(\S+)[\s\n]*\([\s\n]{0,}(.*)[\s\n]{0,}\)[\s\n]{0,}\{(.*)/", " $1($2)")

		$this->tags = array(
			'code-start' 		=> '<?',
			'code-start-full'	=> '<?php',					// Alias del tag de inicio (deben empezar igual)
			'code-end' 			=> '?>',
			'comments-start'	=> array('//', '#'),
			'comments-end'		=> "\n",
			'comment-ml-start'	=> '/*',					// Inicio comentario multilinea
			'comment-ml-end'	=> '*/',
			'strings'			=> array('"', "'"),
			'strings-escape'	=> '\\',					// Ignora siguiente caracter dentro de una cadena
			'functions'			=> array('public function', 'private function', 'protected function', 'function', 'class', 'namespace', 'trait'),
															// Declaración de funciones/clases
			'separators-end'	=> array('{', '}', ';'),
			'no-spaces'			=> array('(', ')', ','),	// Remueve espacios antes de este caracter
			'args-start'		=> '(',						// Marca inicio de argumentos en declaración de funciones
			'args-end'			=> ')',
			'eval-args'			=> '\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)',
															// regexp para validar variables dentro de los argumentos de la función
			'ignore-functions'	=> array('__construct', '__isset', '__unset', '__call', '__get', '__put'),
															// Funciones a ignorar al validar elementos requeridos para documentar
			'ignore-types'		=> array('namespace', 'class', 'trait')
															// Tipos a ignorar al validar elementos requeridos para documentar
		);

		// Inicializa elemento "file" para prevenir error al validar la primera vez
		$this->cache = array('file' => '');
	}

	/**
	 * Retorna el nombre del último archivo procesado.
	 *
	 * @return string Path completo del archivo procesado.
	 */
	public function filename() {
		return $this->cache['file'];
	}

	/**
	 * Asigna valores a los tags usados.
	 * El valor puede ser texto o arreglo, dependiendo de cómo haya sido originalmente definido.
	 */
	public function setTagValue(string $name, mixed $value) {

		$name = trim(strtolower($name));
		if ($name != '' && isset($this->tags[$name])) {
			if (is_array($this->tags[$name]) && !is_array($value)) {
				if (strpos($value, ',') !== false) {
					// Asume son campos separados por comas
					$value = explode(',', $value);
				}
				else {
					// convierte en arreglo
					$value = array($value);
				}
				// Actualiza valor
				$this->tags[$name] = $value;
			}
		}
	}

	/**
	 * Define función a usar para evaluar un tag particular.
	 * Ignora esta definición si el tag corresponde a alguno de los que son evaluados por defecto
	 * (author, param, return, etc.).
	 * La función recibe como argumentos el arreglo donde registra el tag (por referencia) y el valor
	 * capturado para el tag actual (puede haber más de uno según sea el caso),
	 * por ejemplo: function (&$bloquedoc, $linea) { ... $bloquedoc = $linea; }
	 *
	 * La función debe asegurarse de actualizar el valor registrado en $bloquedoc
	 *
	 * @param string $tag
	 * @param callable $fun Función a ejecutar.
	 */
	public function setTagFunEval(string $tag, callable $fun) {

		$tag = strtolower(trim($tag));
		if ($tag != '') {
			$this->tags_fun[$tag] = $fun;
		}
	}

	/**
	 * Descripción básica del elemento asociado.
	 *
	 * @param string $filename Nombre del archivo.
	 * @return array Arreglo con los items de documentación.
	 */
	public function getSummary(string $filename) {

		$documento = $this->getDocumentationScript($filename, '', true);

		return $documento['main']['summary'];
	}

	/**
	 * Guarda información de caché en disco.
	 * Requiere se haya definido una función del tipo fun($filename, $data).
	 *
	 * @param string $filename Nombre del script del que se va a recuperar la documentación.
	 * @return bool TRUE si pudo crear el caché en disco. FALSE en otro caso.
	 */
	public function serialize(array $data) {
		// Guarda en disco
		$resultado = false;

		if (is_callable($this->serializeFunction) && isset($data['file'])) {
			$data['docmtime'] = filemtime(__FILE__);
			$resultado = call_user_func($this->serializeFunction, $data['file'], $data);
		}

		return $resultado;
	}

	/**
	 * Recupera información de caché en disco.
	 * El archivo en disco debe tener fecha mayor o a la del original.
	 * Debe definirse una función para deserializar del tipo $data_cache = fun($filename)
	 * donde $data_cache es un arreglo con los datos esperados.
	 *
	 * @param string $filename Nombre del script del que se va a recuperar la documentación.
	 * @param mixed $info Arreglo a recibir la información recuperada.
	 * @return bool TRUE si recupera con éxito de caché en disco. FALSE en otro caso.
	 */
	public function unserialize(string $filename, mixed &$info) {

		$resultado = false;

		if (is_callable($this->unserializeFunction)) {
			$encache = call_user_func($this->unserializeFunction, $filename);
			if (is_array($encache)
				&& strtolower($encache['file']) === strtolower($filename)
				&& isset($encache['docmtime'])
				&& $encache['docmtime'] == filemtime(__FILE__)
			) {
				$info = $encache;
				$resultado = true;
			}
		}

		return $resultado;
	}

	/**
	 * Valida si el caché en memoria corresponde al archivo actual.
	 *
	 * @param string $filename Nombre del script del que se va a recuperar la documentación.
	 * @return bool TRUE si el caché actual en memoria corresponde a $filename. FALSE en otro caso.
	 */
	private function enCache(string $filename) {

		return (strtolower($this->cache['file']) === strtolower($filename));
	}

	/**
	 * Recupera los bloques de documentación del archivo $filename.
	 *
	 * @param string $filename Nombre del script del que se va a recuperar la documentación.
	 * @param string $name_fun Nombre de la función a buscar en la documentación.
	 * @param bool $only_summary TRUE retorna solamente el bloque de resumen (summary). FALSE retorna toda la documentación.
	 * @return array Arreglo con todos los documentos recuperados.
	 */
	public function getDocumentation(string $filename, string $name_fun = '') {

		return $this->getDocumentationScript($filename, $name_fun, false);
	}

	/**
	 * Recupera los bloques de documentación del archivo $filename.
	 * Retorna un arreglo con la siguiente estructura:
	 * - file: Nombre del archivo ($filename).
	 * - main: Documentación del script (corresponde al primer bloque de documentación encontrado).
	 * - docs: Elementos asociados (funciones, clases, métodos).
	 * - errors: Errores encontrados.
	 * - index: Indice de funciones y su ubicación en el arreglo "docs", para agilizar busquedas.
	 * - debug: Mensajes de depuración (solamente cuando modo debug es TRUE).
	 *
	 * @param string $filename Nombre del script del que se va a recuperar la documentación.
	 * @param string $name_fun Nombre de la función a buscar en la documentación.
	 * @param bool $only_summary TRUE retorna solamente el bloque de resumen (summary). FALSE retorna toda la documentación.
	 * @return array Arreglo con todos los documentos recuperados.
	 */
	private function getDocumentationScript(string $filename, string $name_fun, bool $only_summary) {

		$documento = array(
			'file' 		=> $filename,
			'main' 		=> array('summary' => ''),
			'docs'  	=> array(),
			'errors'	=> array(),
			'index' 	=> array(),
			'debug'		=> array()
			);

		if (!file_exists($filename)) {
			$documento['errors'][] = miframe_text('Script no existe');
			return $documento;
		}

		// Valida si coincide con la más reciente captura.
		// (no valida $only_summary porque puede ser invocada la clase durante getDocumentation() para
		// recuperar resumenes de otros archivos, ejemplo al documentar tag "uses").
		// if ($this->enCache($filename) && !$only_summary) {
		if ($this->enCache($filename)) {
			$documento = $this->cache;
			$documento['cache'] = 'memory'; // Recuperado de caché en memoria
		}
		// Recupera de caché en disco
		elseif ($this->unserialize($filename, $documento)) {
			$documento['cache'] = 'disk'; // Recuperado de caché en disco
		}
		// Recupera documentación directamente del archivo origen
		else {
			$documento['cache'] = 'no-cache';

			$contenido = '';

			// Valida que el documento contenga alguno de los tags de inicio
			$contenido = $this->docBlockCodeStart($filename, $documento['errors']);
			if ($contenido == '') {
				// Nada que procesar
				return $documento;
			}

			// Contenedores
			$nuevo = array();
			$acum = '';
			$bloquedoc = array();

			// Elimina lineas de comentario, comillas y comentarios en bloque
			// asegurandose que un comentario no este en una cadena, etc.
			// Como pueden haber cadenas de texto inicializando parametros en funciones, no se pueden ignorar.
			// El proceso cumple varios objetivos:
			// * Elimina lineas en blanco (excepto aquellas dentro de un bloque de documentación).
			// * Elimina múltiples espacios en blanco contiguos (excepto aquellas dentro de un bloque de documentación).

			// Inicialización de variables de control
			$len = strlen($contenido);
			$es_codigo = ($this->tags['code-start'] == ''); // Si no hay tag de inicio, todo es codigo
			$esta_ignorando = false;
			$en_cadena = false;
			$es_documentacion = false;
			$tag_cierre = '';				// Contenedor para tags de cierre. Ej: "*/" para cierre de comentarios.
			$len_inicio = 2; 				// Tamaño mínimo para evaluar "<?", "//", ...
			if (!$es_codigo) {
				$len_inicio = strlen($this->tags['code-start']);
			}
			$len_car = $len_inicio;
			$len_full = strlen($this->tags['code-start-full']);
			$total_functions = 0;

			for ($i = 0; $i < $len; $i++) {
				$car = substr($contenido, $i, $len_car); // Lee de a dos carácteres
				if (!$es_codigo) {
					// Ignora todo hasta encontrar "<?"
					if ($car == $this->tags['code-start']) {
						$es_codigo = true;
						if ($this->tags['code-start-full'] != ''
							&& (substr($contenido, $i, $len_full) == $this->tags['code-start-full'])
							) {
								$i += $len_full - 1;
						}
						else { $i ++; }
					}
					// else { $acum .= '.'; } // Para debug...
				}
				else {
					// Dentro de código
					if (!$esta_ignorando && !$en_cadena) {
						if ($car === $this->tags['code-end']) {
							$acum .= $car;
							$es_codigo = false;
							$i ++; // Hasta el siguiente bloque luego del inicio
						}
						else {
							if (in_array($car[0], $this->tags['strings'])) {
								// Inicia comillas simples y dobles (no lo ignora pero lo identifica
								// para evitar interpretaciones erroneas de tags de control dentro de cadenas
								// de texto)-
								$tag_cierre = $car[0];
								$en_cadena = true;
							}
							elseif (in_array($car, $this->tags['comments-start'])
								|| in_array($car[0], $this->tags['comments-start'])
								) {
								// Inicia comentario sencillo
								$tag_cierre = $this->tags['comments-end'];
								$esta_ignorando = true;
								$i ++; // Lee siguiente bloque luego de apertura de comentario
							}
							elseif ($car === $this->tags['comment-ml-start']) {
								// Inicia bloque de comentario
								// Valida si es un bloque de documentación "/**\n"
								$esta_ignorando = true;
								$tag_cierre = $this->tags['comment-ml-end'];
								$es_documentacion = (
													$this->tags['comment-ml-start'] === '/*'
													// Ignora los fin de linea antes y después
													&& trim(substr($contenido, $i - 1, 5)) === '/**'
													);
								if (!$es_documentacion) {
									$i ++; // Lee siguiente bloque luego de apertura de comentario
								}
								else {
									if ($this->evalCodeBlock($acum, $bloquedoc, $nuevo)) {
										$total_functions ++;
									}
									// Redefine llave
									$acum = '';
									$i += 2;
								}
							}
							elseif (in_array($car[0], $this->tags['no-spaces'])) {
								$acum = rtrim($acum);
							}
						}
						if ($esta_ignorando || $en_cadena) {
							// Detectó combinación para ignorar
							$len_car = strlen($tag_cierre);
							if ($en_cadena) {
								$acum .= $car[0];
							}
							// else { $acum .= '.'; }
						}
						elseif ((!$es_documentacion && $car[0] === ' ') ||
							(!$es_documentacion && ($car[0] === ' ' || $car[0] === "\t"))
							) {
							// El ultimo elemento en $nuevo no debe ser un espacio
							if ($acum !== '' && trim(substr($acum, -1, 1)) !== '') {
								$acum .= ' ';
							}
						}
						elseif (in_array($car[0], $this->tags['separators-end'])) {
							$acum .= "\n";
						}
						elseif ($car[0] === "\n") {
							// El ultimo elemento en $nuevo no debe ser un espacio

							if ($acum != '') {
								if ($es_documentacion) {
									$acum .= $car[0];
								}
								else {
									// Ignora "\n" y lo cambia por un espacio
									if ($acum != '' && substr($acum, -1, 1) !== " ") {
										$acum .= " ";
									}
								}
							}
						}
						elseif ($car[0] !== "\r") {
							$acum .= $car[0];
						}
					}
					else {
						// Ignorando contenido
						if ($en_cadena && $car[0] === $this->tags['strings-escape']) {
							// Ignora hasta el siguiente bloque
							$i++;
							// Anexa el siguiente caracter
							$acum .= $car . substr($contenido, $i, 1);
						}
						elseif ($car === $tag_cierre) {
							// Encontró el tag de cierre
							$i += ($len_car - 1);
							$len_car = $len_inicio;

							if ($es_documentacion) {
								$bloquedoc = $this->evalDocBlock($acum, $filename);
								if ($total_functions <= 0) {
									$nuevo['main'] = $bloquedoc;
									$bloquedoc = array();
									$total_functions ++;
									// Si solo requiere sumario, abandona el resto
									if ($only_summary) { break; }
								}
							}
							elseif ($en_cadena) {
								$acum .= $car;
							}
							elseif ($tag_cierre === "\n" && !in_array($tag_cierre, $this->tags['separators-end'])) {
								// Ignora el "\n" y lo cambia por un espacio
								if ($acum != '' && substr($acum, -1, 1) !== " ") {
									$acum .= " ";
								}
							}
							elseif (in_array($tag_cierre, $this->tags['separators-end'])) {
									$acum .= "\n";
							}
							// else { $acum .= '.'; } // Para debug...

							$esta_ignorando = false;
							$en_cadena = false;
							$es_documentacion = false;
						}
						elseif ($es_documentacion || $en_cadena) {
							// Preserva contenido (documentación)
							$acum .= $car[0];
						}
						// else { $acum .= '.'; }  // Para debug...
					}
				}
			}

			$this->evalCodeBlock($acum, $bloquedoc, $nuevo);

			/*if (isset($nuevo['namespace'])) {
				$documento['namespace'] = $nuevo['namespace'];
				unset($nuevo['namespace']);
			}*/

			if (isset($nuevo['main'])) {
				// Evalua requeridos
				if (!$only_summary) {
					$this->docBlockRequiredItems($documento['errors'], $nuevo['main'], true);
				}
				// Reasigna main
				$documento['main'] = $nuevo['main'];
				unset($nuevo['main']);
			}

			if (!$only_summary) {
				$documento['docs'] = $nuevo;

				// Construye indice de funciones
				$documento['index'] = array();
				foreach ($nuevo as $k => $info) {
					if (isset($info['name'])) { // function-name
						$documento['index'][strtolower($info['name'])] = $k; // function-name
						// Revisa requeridos
						$this->docBlockRequiredItems($documento['errors'], $info);
					}
				}

				// Asegura existencia del "main" y "summary"
				if (!isset($documento['main'])) { $documento['main'] = array(); }
				if (!isset($documento['main']['summary'])) { $documento['main']['summary'] = ''; }

				// Guarda en disco
				$this->serialize($documento);
			}
		}

		// Preserva captura actual (si se invoca desde otro llamado a getDocumentation() al
		// final preservará la del primer archivo evaluado).
		if (!$this->enCache($filename)) {
			$this->cache = $documento;
		}

		// Busca función indicada
		if ($name_fun != '') {
			if (isset($documento['index'][$name_fun])) {
				// Elimina todos los docs y deja solamente uno
				$documento['docfunction'] = $documento['docs'][$documento['index'][$name_fun]];
				$documento['docs'] = array();
			}
			else {
				$documento['errors'][] = miframe_text('No hay coincidencias para la busqueda realizada ($1)', htmlspecialchars($name_fun));
			}
		}

		return $documento;
	}

	/**
	 * Valida que existan tags de inicio de documentación en el contenido del archivo.
	 * Retorna vacio también si el tipo de archivo no tiene definidas tags de inicio.
	 *
	 * @param string $filename Nombre del script del que se va a recuperar la documentación.
	 * @param array $errors Arreglo editable dónde serán registrados los mensajes de error.
	 * @return string Contenido del archivo o vacio si no encuentra los tags de inicio.
	 */
	private function docBlockCodeStart(string $filename, array &$errors) {

		$contenido = '';

		if ($this->tags['code-start-full'] != '' || $this->tags['code-start'] != '') {
			// Al menos uno de los tags de inicio no está en blanco
			$contenido = iso2utf8(file_get_contents($filename));
			// Valida que el contenido tenga alguno de los inicios declarados (no es estrictamente)
			$inicio_full = ($this->tags['code-start-full'] != '' && strpos($contenido, $this->tags['code-start-full']) !== false);
			$inicio_simple = ($this->tags['code-start'] != '' && strpos($contenido, $this->tags['code-start']) !== false);

			if (!$inicio_full && !$inicio_simple) {
				// No hay ninguno de los inicios reportados
				$errors[] = miframe_text('No puede documentar el tipo de archivo indicado ($1). No se encuentra ninguno de los tags de inicio a buscar ($2)',
					basename($filename),
					htmlspecialchars(implode(',', [ $this->tags['code-start-full'],  $this->tags['code-start'] ]))
					);

				$contenido = '';
			}
		}
		else {
			// No hay tags de inicio, no es error pero no puede garantizar documentación tampoco
			$errors[] = miframe_text('No puede documentar el tipo de archivo indicado ($1). No se definieron tags de inicio a buscar.',
				basename($filename)
				);
		}

		return $contenido;
	}

	/**
	 * Evalua elementos requeridos y genera mensajes de error.
	 * Si $this->evalRequiredItems es false no realiza esta validación.
	 *
	 * @param array $errors Arreglo editable dónde serán registrados los mensajes de error.
	 * @param array $info Bloque de documentación a revisar.
	 * @param bool  $is_main TRUE para indicar que $info corresponde al bloque principal (descripción del script).
	 */
	private function docBlockRequiredItems(array &$errors, array $info, bool $is_main = false) {

		if (!$this->evalRequiredItems) { return; }

		$ignore_summary = false;
		$origen = '';
		if (isset($info['name'])) { // function-name
			$origen = miframe_text('en la función **$1**', $info['name']); // function-name
			$ignore_summary = (
					// Ignora métodos "mágicos"
					in_array($info['name'], $this->tags['ignore-functions']) // function-name
					|| in_array($info['type'], $this->tags['ignore-types'])
					);
		}

		if ((!isset($info['summary']) || $info['summary'] == '') && !$ignore_summary) {
			$errors[] = miframe_text('No se ha documentado resumen $1', $origen);
		}

		if (!isset($info['author']) && $is_main) {
			$errors[] = miframe_text('No se ha documentado el autor del script');
		}

		if ((isset($info['args']) && $info['args'] != '') && !$ignore_summary) { // function-args
			if (!isset($info['param'])) {
				$errors[] = miframe_text('No se ha documentado @param $1', $origen);
			}
			elseif ($info['args'] != '') { // function-args
				$args = $info['args']; // function-args
				// Tomado de https://stackoverflow.com/questions/19562936/find-all-php-variables-with-preg-match
				$pattern = $this->tags['eval-args'];
				if ($pattern != '') {
					// Los resultados quedan en $matches[0]
					$result = preg_match_all("/$pattern/", $args, $matches);
					if ($result > 0) {
						foreach ($matches[1] as $k => $param) {
							if (!isset($info['param'][$param])) {
								$info['param'][$param] = '?';
							}
							else {
								unset($info['param'][$param]);
							}
						}
						if (count($info['param']) > 0) {
							$errors[] = miframe_text('Hay argumentos @param no documentados $1 ($2)', $origen, implode(', ', array_filter(array_keys($info['param']))));
						}
					}
				}
			}
		}
	}

	/**
	 * Evalúa bloque de código encontrado entre bloques de documentación.
	 * Se supone que la primera definición de función/clase encontrada corresponde a aquel a
	 * que refiere el bloque de documentación previamente encontrado.
	 * Recomendaciones del texto a revisar:
	 * - Cada línea contiene bloques de código continuo, hasta encontrar un "{" (PHP).
	 * - Libre de más de un espacio en blanco entre palabras.
	 * - Libre de comentarios.
	 *
	 * @param string $text Texto con el código a procesar.
	 * @param array $docblock Bloque de documentación previamente encontrado.
	 * @param array $container Arreglo que acumula los descriptores.
	 * @return bool TRUE si pudo asignar el bloque de documentación a una función, FALSE en otro caso.
	 */
	private function evalCodeBlock(string $text, array &$docblock, array &$container) {

		$retornar = false;

		// Remueve acumulador actual si está en blanco
		if ($text != '') {
			// Rompe lineas y solo incluye las que tengan inicio con "functions-regexp"
			$text = trim($text);
			$lineas = explode("\n", $text);
			foreach ($lineas as $k => $linea) {
				$linea = trim($linea);
				$regexp = "/^(" . implode('|', $this->tags['functions']) . ")[\s\n](.*)/i";
				preg_match($regexp, $linea, $matches);
				// Busca items asociados. El elemento "0" es la palabra que hace match, el segundo el resto
				if (count($matches) > 1) {
					// [2] contiene la función y los argumentos
					$args = '';
					if ($matches[1] == 'class' || $matches[1] == 'trait') {
						$pos = strpos($matches[2], ' ');
						if ($pos !== false ) {
							$args = trim(substr($matches[2], $pos + 1));
							$matches[2] = substr($matches[2], 0, $pos);
						}
					}
					else {
						$pos = strpos($matches[2], $this->tags['args-start']);
						$fin = strrpos($matches[2], $this->tags['args-end']);
						if ($pos !== false && $fin !== false && $pos < $fin) {
							$args = trim(substr($matches[2], $pos + 1, $fin - $pos - 1));
							$matches[2] = substr($matches[2], 0, $pos);
						}
					}
					$container[] = array(
						'type' => $matches[1],
						'name' => $matches[2], // function-name
						'args' => $args	// function-args
						) + $docblock;
					$docblock = array();
					$retornar = true;
				}
				/*elseif ($this->tags['namespace'] != '') {
					// Este es un caso especial, donde asigna un nombre de espacio al sistema
					$regexp = "/^(" . $this->tags['namespace'] . ")[\s\n](.*)/";
					preg_match($regexp, $linea, $matches);
					if (count($matches) > 1) {
						$container['namespace'] = $matches[2];
					}
				}*/
			}
		}

		return $retornar;
	}

	/**
	 * Evalúa un bloque de código sanitizado previamente y recupera los atributos de documentación.
	 *
	 * A tener en cuenta algunas definiciones de texto Markdown a mantener:
	 *
	 * - Un parrafo es una o más líneas de texto consecutivas separadas por uno o más líneas en blanco. No indente parrafos
	 *   normales con espacios o tabs.
	 *   También se considera como fin de parrafo un punto o ":" al final de la línea.
	 * - Usar ">" para indicar un texto como "blockquote".
	 * - Cuatro espacios en blanco al inicio o un tab, indican un texto preformateado. De forma similar, texto enmarcado
	 *   por "```" también se considera como texto preformateado.
	 * - Listas empiezan con "*", "+" o "-". Un "*" precedido de cuatro espacios o un tab, indica que es una sublista de un
	 *   item de lista previo.
	 *
	 * @link https://bitbucket.org/tutorials/markdowndemo/src/master/
	 * @param string $text Texto con la documentación.
	 * @return array Arreglo con los datos del documento, (Ej. [ 'summary' => ..., 'description' => ..., etc. ])
	 */
	private function evalDocBlock(string $text) {

		$text = trim($text);
		if ($text == '') { return; }

		$lineas = explode("\n", $text);
		$text = ''; // Libera memoria

		$bloquedoc = array();
		$finlinea = array('.', ':');
		$es_pre = false;
		$es_tags = false;

		$total_lineas = count($lineas);
		$n = 0;

		// Purga las líneas buscando aquellas que sean documentación
		for ($i = 0; $i < $total_lineas; $i ++) {
			// Por defecto, todas las lineas de documentación validas empiezan con "* ".
			$linea = trim($lineas[$i]);
			$lineas[$i] = ''; // Limpia

			if ($linea === '*') {
				// Línea vacia
				$this->docBlockNewLine($lineas, $n);
				continue;
			}
			elseif (substr($linea, 0, 2) !== '* ') {
				// No es una línea valida para documentación
				continue;
			}
			else {
				// Remueve marca
				$linea = substr($linea, 2);
			}

			$slinea = trim($linea);

			// Remplaza cuatro espacios por un TAB
			$linea = str_replace('    ', "\t", $linea);

			if ($linea === '```') {
				$es_pre = !$es_pre;
			}

			// Texto preformateado convencional (no aplica si la línea actual es una lista)
			if (trim($linea[0]) === "@") {
				if ($lineas[$n] != '') { $n ++; }
				$lineas[$n] = $linea;
				$n ++;
				$es_tags = true;
			}
			/*elseif ($lineas[$n] !== '') {
				$lineas[$n] .= ' ' . $slinea;
			}*/
			elseif ($es_tags && $linea[0] == "\t") {
				// Documentando tags, asume es parte de la misma linea
				$lineas[$n - 1] .= "\n" . $linea;
			}
			else {
				$es_tags = false;
				// Fin de parrafo convencional
				if ($n == 1 && substr($lineas[0], -1, 1) != "\n") {
					// Parte del summary (primer linea)
					$linea = $lineas[0] . ' ' . trim($linea);
					$n = 0;
				}
				if (!$es_pre && $linea[0] !== "\t"
					&& $slinea[0] != '>'
					&& in_array(substr($linea, -1, 1), $finlinea)
					) {
					$lineas[$n] = $linea;
					$this->docBlockNewLine($lineas, $n);
				}
				else {
					$lineas[$n] = $linea;
					$n ++;
				}
			}
		}

		$lineas = array_filter($lineas);

		// Ahora si evalua cada línea
		$total_lineas = count($lineas);

		// Purga las líneas buscando aquellas que sean documentación
		for ($i = 0; $i < $total_lineas; $i ++) {
			$linea = trim($lineas[$i]);
			// echo "$i . " . htmlspecialchars($linea) . "<hr>";
			if (substr($linea, 0, 1) === '@') {
				// Es un tag de documentacion
				$pattern = '/\@([a-zA-Z0-9\-]{1,})(\s{0,})(((.*)(\n{0,})(.*)){0,})/';
				$result = preg_match_all($pattern, $linea, $matches);
				$tag_doc = '';
				$lineadata = '';
				if (isset($matches[1][0])) { $tag_doc = strtolower($matches[1][0]); }
				if (isset($matches[3][0])) { $lineadata = $matches[3][0]; }

				// Casos especiales:
				// @param (tipo) (variable) (descripcion)
				// @return (tipo) (descripcion)
				switch ($tag_doc) {
					case 'param':
						// $arreglo[1] contiene de "@param" en adelante
						$arreglo = array('', '', '');
						$pattern = '/(\w{1,})(\s{1,})' . $this->tags['eval-args'] . '(\s{0,})(((.*)(\n{0,})(.*)){0,})/';
						$result = preg_match_all($pattern, $lineadata, $matches);
						// echo $tag_doc . ' / ' . $lineadata . '<br>' . $pattern . '<br>'; print_r($matches); echo "<hr>";
						if (isset($matches[1][0])) { $arreglo[0] = strtolower($matches[1][0]); }
						if (isset($matches[3][0])) { $arreglo[1] = $matches[3][0]; }
						if (isset($matches[5][0])) { $arreglo[2] = $matches[5][0]; }

						$bloquedoc[$tag_doc][$arreglo[1]] = array(
								'type' => trim($arreglo[0]),
								'description' => $arreglo[2]
							);
						break;

					case 'return':
						$arreglo = explode(' ', $lineadata . ' ', 2);
						$bloquedoc[$tag_doc] = array('type' => $arreglo[0], 'description' => trim($arreglo[1]));
						break;

					case 'author':
					case 'since':
					case 'version':
					case 'todo':
					case 'link':
						// Los acumula directamente
						if (isset($bloquedoc[$tag_doc])) {
							if (!is_array($bloquedoc[$tag_doc]) && $bloquedoc[$tag_doc] != '') {
								$bloquedoc[$tag_doc] = array($bloquedoc[$tag_doc]);
							}
							$bloquedoc[$tag_doc][] = $lineadata;
						}
						else {
							$bloquedoc[$tag_doc] = $lineadata;
						}
						break;

					default:
						// Valida si está definido el tag manual
						if (isset($this->tags_fun[$tag_doc])) {
							if (!isset($bloquedoc[$tag_doc])) { $bloquedoc[$tag_doc] = ''; }
							$fun = $this->tags_fun[$tag_doc];
							$fun($bloquedoc[$tag_doc], $lineadata);
						}
						// Los agrupa bajo "others"
						elseif (isset($bloquedoc['others'][$tag_doc])) {
							if (!is_array($bloquedoc['others'][$tag_doc])) {
								if ($bloquedoc['others'][$tag_doc] != '') {
									$bloquedoc['others'][$tag_doc] = array($bloquedoc['others'][$tag_doc]);
								}
								else {
									$bloquedoc['others'][$tag_doc] = array();
								}
							}
							$bloquedoc['others'][$tag_doc][] = $lineadata;
						}
						else {
							$bloquedoc['others'][$tag_doc] = $lineadata;
						}
					}
			}
			elseif (!isset($bloquedoc['summary']) || $bloquedoc['summary'] == '') {
				$bloquedoc['summary'] = trim($lineas[$i]);
				}
			elseif (!isset($bloquedoc['description']) || $bloquedoc['description'] == '') {
					$bloquedoc['description'] = $lineas[$i];
				}
			else {
				$bloquedoc['description'] .= "\n" . $lineas[$i];
			}
		}

		return $bloquedoc;
	}

	/**
	 * Adiciona fin de línea para que el interpretador lo tome correctamente.
	 *
	 * @param array $lines Arreglo editable de líneas.
	 * @param int $index Indice editable con la línea del arreglo $lines actualmente en revisión.
	 */
	private function docBlockNewLine(array &$lines, int &$index) {

		if ($lines[$index] != '') {
			$lines[$index] .= "\n";
			$index ++;
		}
		elseif ($index > 0 && $lines[$index - 1] != '' && substr($lines[$index - 1], -1, 1) != "\n") {
			$lines[$index - 1] .= "\n";
		}
	}

}