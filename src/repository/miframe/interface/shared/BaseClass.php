<?php
/**
 * Clase base usada como soporte para la creación de clases similares.
 * Las características que esta clase aporta son:
 * - Manejo de arreglo de valores.
 * - Manejo de directorio y archivo principal.
 * - Permite manejo dinámico de métodos y propiedades.
 *
 * @author John Mejia
 * @since Mayo 2022
 */

namespace miFrame\Interface\Shared;

/**
 * Propiedades públicas:
 * - $debug: boolean. TRUE para incluir mensajes de depuración.
 */
class BaseClass {

	private $dir_base = '';
	private $indexfile = '';
	private $include_fun = false;
	private $secuencia = 0;

	protected $path_files = '';
	protected $uri_base = '';
	protected $params = array();
	protected $color_debug = '';
	protected $dir_temp = '';

	public $force_json = false;
	public $debug = false;

	/**
	 * Inicializa valores asociados a archivo y URI.
	 * Los valores de archivo y directorio base son tomados del script que invoca la función __construct() de la
	 * clase hija.
	 */
	public function initialize() {

		$this->setURIbase();

		$script = miframe_server_get('SCRIPT_FILENAME');
		$this->indexfile = basename($script);
		$this->dir_base = realpath(dirname($script));

		// El path para ubicar los script es por defecto el mismo del script principal
		$this->path_files = $this->dir_base;

		$this->dir_temp = miframe_temp_dir();
	}

	/**
	 * Directorio físico que contiene el script principal.
	 *
	 * @return string Path
	 */
	public function getDirbase() {

		return $this->dir_base;
	}

	/**
	 * Modifica manualmente el valor del directorio base.
	 *
	 * @param string $path Path
	 */
	public function setDirbase(string $path) {

		$this->dir_base = $path;
	}

	/**
	 * Path físico del script principal.
	 *
	 * @return string Path
	 */
	public function scriptName() {

		return $this->createPath($this->indexfile);
	}

	/**
	 * Path físico real esperado para el path relativo indicado.
	 *
	 * @param string $path Nombre del script o path a adicionar al directorio
	 *   base. Si no se indica, usa el asociado al script invocado.
	 * @return string Path
	 */
	public function createPath(string $script_name) {

		return miframe_path($this->dir_base, $script_name);
	}

	public function setDirTemp($path) {

		if (is_dir($path)) {
			$this->dir_temp = $path;
		}
	}

	/**
	 * Retorna la URL base identificada.
	 *
	 * @return string URL
	 */
	public function getURIbase() {

		return $this->uri_base;
	}

	/**
	 * Retorna la URL indicada SIN el segmento base (si aplica).
	 * Si la URL no contiene la URI base, retorna vacio.
	 *
	 * @return string URL
	 */
	public function removeURIbase(string $request_uri) {

		$retornar = '';
		$len_base = strlen($this->uri_base);
		if (substr($request_uri . '/', 0, $len_base) == $this->uri_base) {
			$retornar = substr($request_uri, $len_base); // No incluye primer "/"
		}

		return $retornar;
	}

	/**
	 * Asigna valor al URL base.
	 */
	private function setURIbase() {

		$this->uri_base = '/';
		// Nombre de quien se invoca como el script base.
		$script_name = strtolower(miframe_server_get('SCRIPT_NAME'));
		$dirbase = dirname($script_name);
		// Si no hay subdirectorios (ej. '/index.php'), dirbase() retorna "\". Lo ignora en ese caso.
		if ($dirbase !== '\\') { $this->uri_base = $dirbase . '/'; }
		// Asegura formato
		if (substr($this->uri_base, 0, 1) != '/') { $this->uri_base = '/' . $this->uri_base; }
	}

	/**
	 * Retorna nombre del URL principal.
	 *
	 * @param string $basename Nombre de archivo a usar como alternativa a $this->indexfile.
	 * @return string URL
	 */
	public function documentRoot() {

		return $this->createURL($this->indexfile);
	}

	/**
	 * https://stackoverflow.com/questions/7431313/php-getting-full-server-name-including-port-number-and-protocol
	 */
	function getServerURL(string $path = '')	{

		$protocol = 'http';
		if (!empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == '1')) {
			$protocol = 'https';
		}
		$server = $_SERVER['SERVER_NAME'];
		$port = '';
		// Adiciona puerto si no es 80 (http) ni 443 (https)
		if (isset($_SERVER['SERVER_PORT']) && !in_array($_SERVER['SERVER_PORT'], [ 80, 443 ])) {
			$port = ':'.$_SERVER['SERVER_PORT'];
		}

		if ($path != '') {
			$path = $this->createURL($path);
		}

		return $protocol . '://' . $server . $port . $path;
	}

	/**
	 * Crea un nombre URI usando la URL base.
	 *
	 * @param string $path Path a ser adicionado a la URL base.
	 * @return string URL
	 */
	public function createURL(string $path) {

		// $this->uri_base siempre inicia y termina en "/"
		if ($path != '' && substr($path, 0, 1) == '/') { $path = trim(substr($path, 1)); }
		return $this->uri_base . $path;
	}

	/**
	 * Valida si ha sido declarado el parámetro indicado.
	 *
	 * @param string $name Nombre del parámetro a validar.
	 * @return bool TRUE si el parámetro ha sido declarado.
	 */
	public function paramExists(string $name) {

		return array_key_exists($name, $this->params);
	}

	/**
	 * Exporta los parámetros declarados.
	 *
	 * @param array $dest Arreglo a recibir los parámetros declarados en $this->params.
	 */
	public function export(array &$dest) {
		if (!is_array($dest)) { $dest = array(); }
		$dest = $this->params + $dest;
	}

	/**
	 * Imprime mensaje en pantalla.
	 *
	 * @param string $text Mensaje a imprimir, permite texto en HTML.
	 * @param string $footnote Mensaje adicional (usualmente para mostrar en diferente formato)
	 */
	protected function print(string $text, string $footnote = '') {

		echo $this->sprintf($text, $footnote);
	}

	/**
	 * Retorna texto HTML preformateado.
	 *
	 * @param string $text Mensaje a imprimir, permite texto en HTML.
	 * @param string $footnote Mensaje adicional (usualmente para mostrar en diferente formato)
	 */
	protected function sprintf(string $text, string $footnote = '') {

		if (!$this->jsonRequest()) {
			if ($footnote != '') {
				$footnote = '<div style="font-size:12px;padding:10px;margin-top:10px;background:rgba(175,184,193,0.2); color:#000;">' .
					$footnote .
					'</div>' . PHP_EOL;
			}

			echo '<div style="font-family:Segoe UI,Tahoma;font-size:14px;margin:10px 0;">'.
				$text . PHP_EOL .
				$footnote .
				'</div>' . PHP_EOL;
		}
		else {
			if ($footnote != '') {
				$footnote = PHP_EOL . '---' . PHP_EOL . $footnote . PHP_EOL;
			}
			echo strip_tags($text . $footnote);
		}
	}


	/**
	 * Imprime mensaje en pantalla solamente si $this->debug = true.
	 *
	 * @param string $message Mensaje a imprimir, permite texto en HTML
	 * @param string $footnote Mensaje adicional
	 */
	protected function printDebug(string $text, string $footnote = '') {

		$text = trim($text);
		if ($text != '') {
			// Marca en modo debug
			if ($this->debug) {
				$this->secuencia ++;
				$this->print('<div style="padding:10px;background:' . $this->color_debug . ';color:#fff">' .
					'<b>' . $this->secuencia . '. DEBUG ' . get_class($this) . '</b> - ' .
					$text .
					'</div>',
					$footnote
					);
			}
			// Adiciona al log de errores de PHP
			error_log($text);
		}
	}

	public function setIncludeFun(callable $fun) {

		$this->include_fun = $fun;
	}

	protected function include(string $filename, string $text = '', string $footnote = '', bool $private = false) {

		if (!file_exists($filename)) {
			return false;
		}

		// Reporta uso en modo debug (lo hace antes del include en caso que contenga un "exit")
		$this->printDebug($text, $footnote);

		$include_fun = false;
		if ($private) {
			// La definición "static" aisla la función para no heredar uso de "this"
			$include_fun = static function ($filename) {
				include_once $filename;
			};
		}
		elseif ($this->include_fun !== false) {
			// Ejecuta la función definida por el usuario
			$include_fun =& $this->include_fun;
		}
		else {
			// En este escenario, puede accederse a este objeto en $filename usando $this
			$include_fun = function ($filename) {
				include_once $filename;
			};
		}

		call_user_func($include_fun, $filename);

		return true;
	}

	/**
	 * Asigna directorio a usar para ubicar los scripts.
	 *
	 * @param string $path Path.
	 */
	public function setPathFiles(string $path) {

		if (is_dir($path)) {
			// Recibe el path completo
			$this->path_files = realpath($path);
		}
		else {
			// Recibe el path asociado a la posición actual
			$path = miframe_path(getcwd(), $path);
			// $path = miframe_path($this->path_files, $path);
			if (is_dir($path)) {
				$this->path_files = realpath($path);
			}
		}
	}

	public function jsonRequest() {

		$retornar = false;
		if ($this->force_json) {
			// Manualmente fijado
			$retornar = true;
		}
		elseif (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
			// Ajax request
			// https://stackoverflow.com/questions/19794859/detect-ajax-in-php
			$retornar = ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');
		}
		elseif (isset($_SERVER['HTTP_ACCEPT'])) {
			$retornar = (strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false);
			// TRUE Espera una respuesta en JSON
		}
		else {
			$headers = apache_request_headers();
			// Usa apache_request_headers() para recuperar headers no asociados al $_SERVER (ocurre en algunos servidores Web)
			$retornar = (isset($headers['Accept']) && strpos(strtolower($headers['Accept']), 'application/json') !== false);
		}

		return $retornar;
	}

}