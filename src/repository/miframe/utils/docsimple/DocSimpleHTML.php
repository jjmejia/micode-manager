<?php
/**
 * Da formato HTML a documentación de código obtenida con la clase DocSimple.
 *
 * @micode-uses miframe/common/functions
 * @micode-uses miframe/utils/htmlsupport
 * @author John Mejia
 * @since Febrero 2023
 */

namespace miFrame\Utils\DocSimple;

/**
 * Da formato HTML a documentación de código obtenida con la clase DocSimple.
 * Las siguientes propiedades públicas pueden ser usadas:
 *
 * - $parserTextFunction: Función a usar para interpretar el texto (asumiendo formato Markdown). Retorna texto HTML. Ej:
 * 		function (text) { ... return $html; }
 * - $clickable: boolean. TRUE para hacer el documento navegable.
 * - $showErrors: boolean. TRUE para mostrar en pantalla los errores/avisos encontrados.
 */
class DocSimpleHTML extends DocSimple {

	private $tags_html_fun = array();	// Funciones asociadas a tags de documentación
	private $html = false;				// Objeto Utils/UI/HTMLSupport

	public $parserTextFunction = false;
	public $clickable = false;
	public $showErrors = true;
	public $showAllFunctions = true; 	// FALSE muestra solamente métodos/funciones públicas

	public function __construct() {
		// Ejecuta __construct() de la clase padre (DocSimple)
		parent::__construct();
		// Para uso en HTMLSupport
		$this->html = new \miFrame\Utils\UI\HTMLSupport();
		$this->html->setFilenameCSS(__DIR__ . '/docsimple-styles.css');
	}

	/**
	 * Retorna la documentación encontrada en formato HTML.
	 * Si se usa con $this->clickable = TRUE habilida las funciones como enlace usando el nombre "docfunction" para indicar
	 * el nombre de la función invocada.
	 * Intenta interpretar los textos asumiendo formato "Markdown" para generar un texto HTML equivalente. Si no se
	 * define una función externa para este fin ($this->parserTextFunction) hace uso de la función interna
	 * parserLocalMarkdown().
	 *
	 * @param string $filename
	 * @return string Texto HTML.
	 */
	public function render(string $filename) {

		$funcion = '';
		$titulo = htmlspecialchars(basename($filename));
		if ($this->clickable && isset($_REQUEST['docfunction']) && $_REQUEST['docfunction'] != '') {
			$funcion = trim($_REQUEST['docfunction']);
			// Enlace de retorno
			$titulo .= ' ' . $this->parserLink(miframe_text('Regresar a Descripción general'), '');
		}

		$documento = $this->getDocumentation($filename, $funcion);

		$salida = $this->html->getStylesCSS(true);

		$salida .= '<div class="docblock"><div class="docfile">' . $titulo . '</div>' . PHP_EOL;

		// Errores encontrados
		if (count($documento['errors']) > 0 && $funcion == '' && $this->showErrors) {
			$salida .= '<div class="docerrors"><ul><li>' .
						implode('</li><li>', $documento['errors']) .
						'</li></ul></div>' . PHP_EOL;
		}

		// Bloque principal
		if (isset($documento['main']) && $funcion == '') {
			$main = $documento['main'];
			$main['last-modified'] = date('Y-m-d', filemtime($filename));
			$salida .= $this->evalHTMLDoc($main, $documento['docs']) . PHP_EOL;
		}
		elseif (isset($documento['docfunction'])) {
			// Publica descripción de función
			$salida .= $this->evalHTMLDoc($documento['docfunction'], array()) . PHP_EOL;
		}

		if (!$this->clickable && count($documento['docs']) > 0) {
			// Muestra todas las funciones en la misma vista
			$salida .= '<div class="docnonav"><h1>' . miframe_text('Contenido') . '</h1>' . PHP_EOL;
			$conteo = 0;
			foreach ($documento['docs'] as $k => $info) {
				if ($info['type'] != 'namespace') {
					if ($conteo > 0) {
						$salida .= '<div class="docseparator">&bull; &bull; &bull;</div>' . PHP_EOL;
					}
					$salida .= $this->evalHTMLDoc($info, array())  . PHP_EOL;
					$conteo ++;
				}
			}
			$salida .= '</div>' . PHP_EOL;
		}

		$salida .= '</div>' . PHP_EOL;

		return $salida;
	}

	/**
	 * Procesa contenido y genera texto HTML equivalente.
	 *
	 * @param array $main Bloque principal de documentación (summary, descripción, params, etc.).
	 * @param array $contents Funciones/métodos asociadas.
	 * @return string Texto HTML.
	 */
	private function evalHTMLDoc(array $main, array $contents = array()) {

		$salida = '';

		$sintaxis = '';

		// Solo para funciones
		if (isset($main['name']) && $main['name'] != '') { // function-name
			$salida .= '<p class="docfunction">' . htmlspecialchars($main['name']) . '</p>'; // function-name
			$sintaxis = '<pre class="docsintaxis">' . $main['type'] . ' <b>' . $main['name'] . '</b>'; // function-name
			if (!in_array($main['type'], $this->tags['ignore-types'])) { // 'class'
				$sintaxis .= '(' . $main['args'] . ')' ; // function-args
				if (isset($main['return'])) {
					$sintaxis .= ': <i>' . $main['return']['type'] . '</i>';
				}
			}
			elseif (isset($main['args'])) {
				$sintaxis .= ' ' . $main['args'];
			}
			$sintaxis .= '</pre>';
		}

		if (isset($main['summary']) && $main['summary'] != '') {
			$salida .= '<div class="docsummary">' . $this->parserText($main['summary']) . '</div>';
		}

		$salida .= $sintaxis;

		if (isset($main['description']) && $main['description'] != '') {
			$salida .= '<div class="docdesc">' . $this->parserText($main['description']) . '</div>' . PHP_EOL;
		}

		if (count($contents) > 0) {
			// Funciones y/o metodos
			$arreglo = array();
			$titulo = miframe_text('Funciones');
			$summary = '';
			$namespace = '';

			foreach ($contents as $k => $info) {
				if (isset($info['type']) && $info['type'] == 'namespace') {
					$namespace = $info['name'] . '\\'; // function-name
				}
				elseif (isset($info['type']) &&
					($info['type'] == 'class' || $info['type'] == 'trait')
					) {
					// Información de clase
					// Si hay datos previos en $arreglo, exporta antes de continuar
					if (count($arreglo) > 0) {
						ksort($arreglo);
						$salida .= '<div class="docfun"><p><b>' . $titulo . '</b></p>' .
									$summary .
									'<ul><li>' . implode('</li><li>', $arreglo) . '</li></ul>' .
									'</div>' . PHP_EOL;
						$arreglo = array();
					}

					$titulo = ucfirst($info['type']) . ' ' . $namespace . $info['name']; // function-name
					if (isset($info['summary']) && $info['summary'] != '') {
						$summary = $this->parserText($info['summary']);
					}
					if ($this->clickable && isset($info['description']) && $info['description'] != '') {
						$summary .= '<p>' . $this->parserLink(miframe_text('Ver detalles'), $info['name']) . '</p>'; // function-name
					}
				}
				elseif (isset($info['name'])) { // function-name
					$function = strtolower($info['name']); // function-name
					$incluir = true;
					$info_function = '';
					if (!$this->clickable) {
						$info_function = '<b>' . htmlspecialchars($info['name']) . '</b>'; // function-name
					}
					else {
						// Determinar si llega por GET o POST la data principal?
						$info_function = $this->parserLink($info['name'], $info['name']); // function-name
					}
					if ($info['type'] != 'public function' && $info['type'] != 'function') {
						$info_function .= ' (' . $info['type'] . ')';
						$incluir = $this->showAllFunctions;
					}
					if (isset($info['summary']) && $info['summary'] != '') {
						$info_function .= ' -- ' . $this->parserText($info['summary'], true);
					}
					if ($incluir) {
						$arreglo[$function] = $info_function;
					}
				}
			}

			if (count($arreglo) > 0) {
				ksort($arreglo);
				$salida .= '<div class="docfun"><h2>' . $titulo . '</h2>' .
							$summary .
							'<p class="docfuntabla">' . miframe_text('Tabla de contenidos') . '</p>' .
							'<ul><li>' . implode('</li><li>', $arreglo) . '</li></ul>' .
							'</div>' . PHP_EOL;
			}
		}

		// Valida definiciones manuales
		foreach ($this->tags_html_fun as $tag_doc => $fun) {
			if (isset($main[$tag_doc])) {
				$respuesta = $fun($main[$tag_doc], $this->clickable);
				if (is_array($respuesta) && isset($respuesta['title']) && isset($respuesta['html'])) {
					$salida .= '<div class="docuses"><h2>' . $respuesta['title'] . '</h2>' .
					$respuesta['html'] .
					'</div>' . PHP_EOL;
				}
				elseif ($respuesta != '') {
					$salida .= '<p class="docinfo">' . $respuesta . '</p>';
				}
			}
		}

		if (isset($main['param'])) {
			foreach ($main['param'] as $param => $info) {
				$main['param'][$param] = '<b>' . $param . '</b> (' . $info['type'] . ') ' .
										$this->parserText($info['description'], true);
			}
			$salida .= '<div class="docparam"><h2>' . miframe_text('Parámetros') . '</h2>' .
						'<ul><li>' . implode('</li><li>', $main['param']) . '</li></ul>' .
						'</div>' . PHP_EOL;
		}

		if (isset($main['return'])) {
			$salida .= '<div class="docreturn"><h2>' . miframe_text('Valores retornados') . '</h2>' .
						'<ul><li>' . $this->parserText($main['return']['description'], true) . '</li></ul>' .
						'</div>' . PHP_EOL;
		}

		$comunes = array(
						'version' => miframe_text('Versión'),
						'author' => miframe_text('Autor'),
						'since' => miframe_text('Desde'),
						'last-modified' => miframe_text('Modificado en')
					);

		foreach ($comunes as $llave => $titulo) {
			if (isset($main[$llave])) {
				if (is_array($main[$llave])) {
					$main[$llave] = implode(', ', $main[$llave]);
				}
				$salida .= '<p class="docinfo"><b>' . $titulo . ':</b> ' .
							nl2br(htmlspecialchars($main[$llave])) .
							'</p>';
			}
		}

		return $salida;
	}

	/**
	 * Genera enlace para navegación de funciones en documentación HTML.
	 *
	 * @param string $titulo Título del enlace.
	 * @param string $function Nombre de la función a buscar.
	 * @param string $param Nombre del parámetro que indica el nombre de la función a buscar. En blanco asume "docfunction".
	 * @return string Enlace en formato HTML.
	 */
	public function parserLink(string $titulo, string $function, string $param = '') {

		$data = array();
		if (count($_GET) > 0) {
			$data = $_GET;
		}
		elseif (count($_POST) > 0) {
			$data = $_POST;
		}
		if ($param == '') { $param = 'docfunction'; }
		$function = trim($function);
		if ($function == '' && isset($data[$param])) {
			unset($data[$param]);
		}
		else {
			$data[$param] = strtolower($function);
		}
		$url = '?' . http_build_query($data);
		$enlace = '<a href="' . $url . '">' . htmlspecialchars($titulo) . '</a>';

		return $enlace;
	}

	/**
	 * Interprete externo de texto Markdown.
	 * Puede usar una función externa para interpretar el texto (se asume tiene formato "Markdown"). Si no se ha definido una función externa,
	 * genera un texto HTML básico usando la función interna parserLocalMarkdown().
	 *
	 * @param  string $text Texto a formatear.
	 * @param  bool   $remove_tag_p Remueve tag "<p>" incluido usualmente como apertura del texto HTML.
	 * @return string Texto HTML equivalente.
	 */
	public function parserText(string $text, bool $remove_tag_p = false) {

		$text = trim($text);
		if ($text != '') {
			if (is_callable($this->parserTextFunction)) {
				$text = call_user_func($this->parserTextFunction, $text);
			}
			else {
				$text = $this->parserLocalMarkdown($text);
			}
			// Protege enlaces, los abre en una pestaña nueva
			$text = str_replace('<a href="', '<a target="doclink" href="', trim($text));
			if ($remove_tag_p) {
				// No lo hace si hay "<p>" en medio del texto, para prevenir tags incompletos.
				if (substr($text, 0, 3) == '<p>' && strpos($text, '<p>', 3) === false) {
					$text = substr($text, 3);
				}
				if (strpos($text, '<p>') === false && substr($text, -4, 4) == '</p>') {
					$text = substr($text, 0, -4);
				}
			}
		}

		return $text;
	}

	/**
	 * Define función a usar para evaluar un tag particular y generar el HTML respectivo.
	 * Ignora esta definición si el tag corresponde a alguno de los que son evaluados por defecto
	 * (author, param, return, etc.).
	 * La función recibe como argumentos el valor del tag, $clickable (TRUE/FALSE según el HTML sea navegable o no)
	 * y debe retornar un arreglo con dos elementos:
	 * "title" (título asociado al tag) y "html" (código HTML a mostrar). Es valido también que retorne
	 * solamente una cadena texto con el HTML a mostrar. Por ejemplo:
	 * function ($valor) { ... return array('title' => 'xxxx', 'html' => 'xxxx'); } o
	 * function ($valor) { ... $html = 'xxxx'; return $html; }
	 *
	 * @param string $tag
	 * @param callable $fun Función a ejecutar.
	 */
	public function setTagFunHTML(string $tag, callable $fun) {

		$tag = strtolower(trim($tag));
		if ($tag != '') {
			$this->tags_html_fun[$tag] = $fun;
		}
	}

	/**
	 * Interprete de texto Markdown básico.
	 * Esta función intenta generar un texto HTML compatible con las siguientes guías:
	 *
	 * - Genera un título si la línea empieza con "#", "##", "###", etc. (debe ir seguido de un espacio en blanco).
	 * - Genera listas si la línea inicia con "-" o "*".
	 * - Genera un bloque preformateado si lo empieza y termina con la línea "```".
	 * - Detecta el fin de párrafo si una línea termina en "." o ":" o va seguida de una línea en blanco.
	 *
	 * @param string $text Texto a formatear.
	 * @return string Texto HTML equivalente.
	 */
	private function parserLocalMarkdown(string $text) {

		$lineas = explode("\n", $text);
		$text = '';
		$es_pre = false;
		$tags_acum = array();
		$total_lineas = count($lineas);

		for ($k = 0; $k < $total_lineas; $k ++) {

			$linea = $lineas[$k];
			if ($es_pre) {
				// Está capturando texto preformateado. Continua mas adelante evaluación para
				// detectar fin del bloque.
				$text .= htmlspecialchars($linea) . PHP_EOL;
				if ($linea === '```') {
					// Toogle valor
					$es_pre = !$es_pre;
					$text .= $this->parserLocalCloseTags($tags_acum, 'pre') . PHP_EOL;
					continue;
				}
			}

			$linea = trim($linea);
			if ($linea === '') { continue; }

			if (isset($lineas[$k +  1]) && $linea !== '```') {
				// Lee siguiente linea
				while (isset($lineas[$k + 1]) && !in_array(substr($linea, -1, 1), [ '.', ':' ])) {
					$sgte = trim($lineas[$k + 1]);
					if ($sgte == '') {
						// Fin de parrafo (ignora la linea en blanco)
						$k ++;
						break;
					}
					elseif ($sgte == '```' || in_array($sgte[0], [ '-', '*', '>', '#'])) {
						// Empieza item especial
						break;
					}

					$k ++;
					$linea .= ' ' . $sgte;
				}
			}

			// Texto preformateado
			if ($linea === '```') {
				// Toogle valor
				$es_pre = !$es_pre;
				$text .= $this->parserLocalOpenTags($tags_acum, 'pre');
				$linea = '';
			}
			// Listas
			elseif ($linea[0] == '-' || $linea[0] == '*') {
				$text .= $this->parserLocalOpenTags($tags_acum, 'ul');
				$text .= '<li>' . htmlspecialchars(trim(substr($linea, 1))). '</li>';
				$linea = '';
			}
			// Blockquote
			elseif ($linea[0] == '>') {
				$text .= $this->parserLocalOpenTags($tags_acum, 'blockquote');
				$text .= htmlspecialchars(trim(substr($linea, 1)));
				$linea = '';
			}
			// Titulos
			elseif ($linea[0] == '#') {
				$pos = strpos($linea, ' ');
				$tam = substr($linea, 0, $pos);
				if (str_replace('#', '', $tam) == '') {
					// Todos los items previos son "#"
					$hx = 'h' . strlen($tam);
					$text .= "<$hx>" . htmlspecialchars(trim(substr($linea, $pos + 1))) . "</$hx>" . PHP_EOL;
					$linea = '';
				}
			}
			else {
				$text .= $this->parserLocalCloseTags($tags_acum, '');
			}

			if ($linea != '') {
				$text .= '<p>' . htmlspecialchars($linea) . '</p>' . PHP_EOL;
			}
		}

		// Valida si terminó con un tabulado abierto
		$text .= $this->parserLocalCloseTags($tags_acum, '');

		return $text;
	}

	/**
	 * Soporte para parserLocalMarkdown: Realiza apertura de tags HTML.
	 * Si se ejecutan aperturas consecutivas del mismo tag, solo aplica la primera.
	 *
	 * @param array $tags_acum Arreglo editable con los tags abiertos (ul, pre, blockquote).
	 * @param string $tag Tag a evaluar.
	 * @return string texto HTML con los tags abiertos (si alguno). Ej: "<ul>".
	 */
	private function parserLocalOpenTags(array &$tags_acum, string $tag) {

		$acum = '';
		$i = count($tags_acum) - 1;
		if ($i < 0 || (isset($tags_acum[$i]) && $tags_acum[$i] != $tag)) {
			while ($i >= 0 && isset($tags_acum[$i]) && $tags_acum[$i] != $tag) {
				// Cierra todo hasta encontrar uno igual o llegar a ceros
				$acum .= '</' . $tags_acum[$i] . '>';
				unset($tags_acum[$i]);
				$i --;
			}
			$i++;
			$tags_acum[$i] = $tag;
			$acum .= '<' . $tag . '>';
		}

		return $acum;
	}

	/**
	 * Soporte para parserLocalMarkdown: Realiza cierre de tags HTML al evaluar texto.
	 *
	 * @param array $tags_acum Arreglo editable con los tags abiertos (ul, blockquote).
	 * @param string $tag Tag a evaluar. En blanco cierra todo lo abierto.
	 * @return string texto HTML con los tags cerrados (si alguno). Ej: "</ul>".
	 */
	private function parserLocalCloseTags(array &$tags_acum, string $tag) {

		$acum = '';
		$i = count($tags_acum) - 1;
		while ($i >= 0 && isset($tags_acum[$i])) {
			$actual = $tags_acum[$i];
			unset($tags_acum[$i]);
			$acum .= '</' . $actual . '>';
			if ($actual == $tag) {
				break;
			}
			$i--;
		}

		return $acum;
	}
}