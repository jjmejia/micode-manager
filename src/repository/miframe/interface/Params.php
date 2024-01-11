<?php
/**
 * Librería de funciones requeridas para manejo de variables en templates.
 *
 * @micode-uses miframe/interface/request
 * @micode-uses miframe/common/functions
 * @author John Mejia
 * @since Noviembre 2023.
 */

namespace miFrame\Interface;

class Params { // extends \miFrame\Interface\Shared\BaseClass

	private $params = array();
	private $aliases = array();
	private $operadores = array();

	public function __construct() {

		// Define operadores básicos
		// tipo:funcion simple:valor por defecto
		// tipo = local, funcion corresponde a un método local
		$this->operadores = array();

		$this->addParamOperator('escape', 	 'local', '_paramEscape');
		$this->addParamOperator('bool',		 'local', '_paramBool');
		$this->addParamOperator('date',		 'local', '_paramDate');
		$this->addParamOperator('empty',	 'local', '_paramEmpty', false);
		$this->addParamOperator('file',		 'local', '_paramFile', false);

		$this->addParamOperator('uppercase', 'string', 'strtoupper');
		$this->addParamOperator('lowercase', 'string', 'strtolower');
		$this->addParamOperator('len',		 'string', 'strlen', 0);

		$this->addParamOperator('count',	  'array',  'count', 0);

		$this->addParamOperator('bytes',	  'numeric', 'miframe_bytes2text');

		// Alias de funciones básicas
		$this->addAliases('u', 'uppercase');
		$this->addAliases('l', 'lowercase');
		$this->addAliases('e', 'escape');
	}

	// No valida operador pues puede estar definido directamente
	public function addAliases(string $name, string $operator) {

		$name = strtolower(trim($name));
		$operator = strtolower(trim($operator));
		if ($name != '' && $operator != '') {
			$this->aliases[$name] = $operator;
		}
	}

	public function addParamOperator(string $name, string $type, string $fun, mixed $default = '') {

		$name = strtolower(trim($name));
		$type = strtolower(trim($type));
		$fun = trim($fun);

		if ($name == '' || $fun == '') {
			miframe_error('No pudo adicionar Operador: Requiere se asigne nombre y función validos.');
		}

		if ($type == 'local') {
			if (!method_exists($this, $fun)) {
				miframe_error('No pudo adicionar Operador **$1**: Método **$2** no soportado', $name, $fun);
			}
		}
		else {
			if ($type !== '') {
				$fun_tipo = 'is_' . $type;
				if (!function_exists($fun_tipo)) {
					miframe_error('No pudo adicionar Operador **$1**: Tipo **$2** no soportado', $name, $type);
				}
			}
			if (!function_exists($fun)) {
				miframe_error('No pudo adicionar Operador **$1**: Función **$2** no soportado', $name, $fun);
			}
		}

		$this->operadores[$name] = array('type' => $type, 'fun' => $fun, 'default' => $default);
	}

	public function clear() {
		$this->params = array();
	}

	/**
	 * Define valores por defecto.
	 *
	 * @param array $defaults
	 */
	public function append(array $data) {

		// Copia datos sin remplazar los actuales
		// $this->params = $this->params + $data;

		// Copia datos remplazando los actuales si existen
		$this->params = $data + $this->params;
	}

	public function replace(array $data) {
		// Copia datos remplazando los actuales si existen
		$this->params = $data;
	}

	public function set(string $name, mixed $value) {
		$this->params[$name] = $value;
	}

	public function show(string $title = '', bool $limited = false) {

		if ($title == '') { $title = 'ViewParams'; }
		$salida = '<pre>' .
			htmlspecialchars(miframe_debug_dump($this->params)) .
			'</pre>';

		return miframe_box($title, $salida, 'mute', '', $limited);
	}

	public function get(string $name, mixed $default = '', mixed $options = '') {

		$name = trim($name);

		// if ($name == '') { return $default; } <-- Puede querer retornar por ej. la fecha de hoy

		$negar = (strpos($name, '!') !== false);
		if ($negar) {
			$name = str_replace('!', '', $name);
		}

		$operador = $this->getParamOperador($name);
		// Recupera valor. Retorna TRUE si lo encuentra en $this->params
		$valor = $this->getParamValue($name, $default);

		/*if (!$continuar) {
			// En modo DEBUG presenta mensaje
			$this->printDebug(miframe_text('Parámetro/variable "$1" no declarada previamente', $name), miframe_debug_backtrace_info());
		}*/

		// Valida operadores simples
		if ($operador != '') {
			if (isset($this->operadores[$operador])) {
				$funcion = $this->operadores[$operador]['fun'];
				$tipo = $this->operadores[$operador]['type'];
				// Usa valor por defecto asociado al operador solamente si no ha especificado uno global
				$pordefecto = $this->operadores[$operador]['default'];
				if ($default !== '') { $pordefecto = $default; }
				if ($tipo == 'local') {
					// El operador es un método de esta clase
					$valor = $this->$funcion($valor);
				}
				elseif ($tipo !== '') {
					$funtipo = 'is_' . $tipo;
					// Aplica validador antes de proceder
					if ($funtipo($valor)) {
						$valor = $funcion($valor);
					}
					elseif ($pordefecto !== '') {
						// Asigna valor por defecto asociado
						$valor = $pordefecto;
					}
				}
				else {
					// No ejecuta validador, va directamente a la función
					$valor = $funcion($valor);
				}
				// echo $operador . ': '; print_r($this->operadores[$operador]); echo ' = ' . $valor . '<hr>';
			}
			else {
				$valor = $name . ' [' . get_class($this) . ': ' . miframe_text('Operador "$1" no soportado', $operador) . ']';
				echo $valor; exit;
			}
		}

		if ($negar) {
			// Forza al resultado de tipo boolean
			$valor = !$this->_paramBool($valor);
		}

		return $valor;
	}

	private function getParamOperador(string &$name) {

		$operador = '';
		$pos = strpos($name, ':');
		if ($pos !== false) {
			$operador = strtolower(trim(substr($name, $pos + 1)));
			$name = trim(substr($name, 0, $pos));

			if (isset($this->aliases[$operador])) {
				$operador = $this->aliases[$operador];
			}
		}

		return $operador;
	}

	private function getParamValue(string $name, mixed $default = '', bool $just_validate = false) {

		$valor = $default;
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

		// Si solo valida, retorna TRUE si la variable existe
		if ($just_validate) { $valor = $continuar; }

		return $valor;
	}

	public function exists(string $param) {

		return $this->getParamValue($param, '', true);
	}

	private function _paramEscape(mixed $valor) {

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

		return $valor;
	}

	private function _paramBool(mixed $valor) {

		return ($valor === true
			|| (is_numeric($valor) && $valor > 0)
			|| (is_string($valor) && $valor !== '')
			|| (is_array($valor) && count($valor) > 0)
			);
	}

	private function _paramDate(mixed $valor) {

		if (is_numeric($valor) && $valor > 0) {
			// Fecha asociada al valor dado (segundos)
			$valor = date('Y/m/d', $valor);
		}
		elseif ($valor === '') {
			// Fecha actual si no hay valor asociado
			$valor = date('Y/m/d');
		}

		return $valor;
	}

	/**
	 * Ejemplos:
	 * <ul>{{ <li>$1</li> }}</ul>
	 * <a href="$2">$1</a>
	 */
	public function implode(string $param, string $template, string $default = '') {

		$text = '';
		$container = '';
		$values = $this->get($param);

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
			// Contenedor de los valores a conectar
			if ($pre != '' || $pos != '') {
				$container = "{$pre}$1{$pos}";
			}

			$text = '';

			if ($template != '') {
				foreach ($values as $key => $valor) {
					if ($valor != '') {
						$text .= trim(str_replace(array('$1', '$2'), array($valor, $key), $template));
					}
				}
			}
			else {
				// No hay template para los elementos, simplemente los conecta
				$text = trim(implode('', $values));
			}
		}

		return $this->addContainer($text, $container, $default);
	}

	public function extract(array $data, string $container = '', string $empty_text = '') {

		$text = '';

		foreach ($data as $param => $template) {
			$valor = $this->get($param);
			if (is_array($valor) && count($valor) > 0) {
				// En este caso, $template es el usado por "implode"
				$text .= $this->implode($valor, $template);
			}
			elseif ($valor != '') {
				$text .= trim(str_replace(array('$1', '$2'), array($valor, $param), $template));
			}
		}

		return $this->addContainer($text, $container, $empty_text);
	}

	private function addContainer(string $text, string $container, string $empty_text) {

		if ($text != '') {
			// Complementa salida con textos pre/pos
			if ($container != '') {
				$text = str_replace('$1', $text, $container);
			}
		}
		else {
			// Texto alternativo a usar si no hay nada que mostrar
			$text = $empty_text;
		}

		return $text;
	}

	private function _paramEmpty(mixed $valor) {

		$condicion = false;
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

		return $condicion;
	}

	private function _paramFile(mixed $valor) {

		$condicion = false;
		if (is_string($valor) && $valor != '') {
			$condicion = file_exists($valor);
		}

		return $condicion;
	}

		// Operadores soportados para condicion: :numeric, :text, :count, :bool
	// Puede indicar valor a mostrar con {{ xxxx }} para evitar recargar la memoria
	public function iif(mixed $condicion, string $true_text, string $false_text = '') {

		// Si la confición es un string, valida solicitud
		if (is_string($condicion)) {

			// Recupera valor
			$valor = $this->get($condicion);
			// Valida sea un boolean
			$condicion = $this->_paramBool($valor);
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
					// $matches[0] contiene los corchetes, los remueve para buscar valor
					$param = str_replace(array('{{', '}}'), '', $matches[0]);
					return $this->get($param, $matches[0]);
				};
			}
			$regexp = "/\{\{.*?\}\}/";
			$template = preg_replace_callback($regexp, $fun, $template);
		}

		return $template;
	}

}