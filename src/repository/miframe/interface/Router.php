<?php
/**
 * Librería para control de enrutamientos de código.
 *
 * ¿Cómo funciona el enrutamiento de código? Todas las consultas se canalizan a través de una sola página
 * (por ejemplo "index.php") y se determina qué script o función ejecutar, por alguno de los siguientes métodos:
 *
 * - Validando uno de los parámetros POST o GET recibidos.
 * - Analizando el REQUEST_URI usado para abrir la página (Por ej. "/micode/projects/edit/php-demo").
 *
 * En este último caso se requiere que el servidor Web enrute las solicitudes directamente al script principal,
 * por ejemplo para Apache, usando reglas "rewrite" en archivos .httpaccess para que todo sea enviado a un script
 * "index.php", que podría o no quedar oculto al usuario final.
 *
 * Pueden también definirse todos los enrutamientos por medio de un archivo de configuración .ini para
 * facilitar su modificación sin necesidad de reescribir código cada que se ingresa una nueva funcionalidad
 * a la aplicación.
 *
 * @micode-uses miframe/common/functions
 * @micode-uses miframe/interface/shared
 * @author John Mejia
 * @since Abril 2022
 */

namespace miFrame\Interface;

/**
 * Clase usada para realizar el enrutamiento dinámico.
 * Las siguientes propiedades públicas pueden ser usadas:
 * - $autoExport: boolean. TRUE carga los argumentos encontrados al validar el enrutamiento en la variable global $_REQUEST
 *   (no se modifica $_POST ni $_GET).
 * - $stopScript: boolean. TRUE para terminar toda ejecución al encontrar la primer ruta valida.
 * - $multipleMatch: boolean. TRUE para permitir que busque en todas las rutas programadas. FALSE suspende validaciones luego de la
 *   primer validación éxitosa.
 * - allowDetour: boolean. TRUE para intentar ejecutar scripts PHP mal redireccionados por el servidor.
 * - strict: boolean. TRUE requiere que se mapeen las rutas para cada método de acceso (GET, POST, etc.). FALSE permite mapeo genérico
 *  (puede representar un riesgo de seguridad según la aplicación).
 */
class Router extends \miFrame\Interface\Shared\BaseClass {

	private $request_param = '';
	private $request = array();
	private $recibido = false;
	private $rutas = array();
	private $detour_handler = false;
	private $matchSuccessful = false;		// TRUE si encuentra al menos un match valido
	private $method_bind = '';
	private $abortando = false;
	private $deteniendo = false;
	private $map_usado = '';
	private $prepare_include = false;

	public $autoExport = false;
	public $stopScript = true;
	public $multipleMatch = false;
	public $allowDetour = true;
	public $strict = false;

	public function __construct() {

		$this->initialize();
		$this->color_debug = '#2e2072';
		// Inicializa ob_start() para capturar cualquier salida a pantalla <-- Evaluar
		// ob_start();
	}

	/**
	 * Retorna valor de referencia detectado.
	 *
	 * @return string Valor de referencia detectado por la función $this->bindPost().
	 */
	public function request() {

		return implode('/', $this->request);
	}


	public function requestStartWith(string $text) {

		return (isset($this->request[0]) && $this->request[0] === strtolower($text));
	}

	/**
	 * Evalua el REQUEST_URI reportado para obtener el valor de referencia.
	 * Se usa inicialmente PATH_INFO es fijado cuando se usa direccionamiento dinamico, ya que como se indica en
	 * https://stackoverflow.com/questions/9879225/serverpath-info-on-localhost
	 *
	 * > For example if you have a file located here: localhost/index.php And you access it via this url:
	 * > localhost/index.php/foo/bar then $_SERVER['PATH_INFO'] will be set to a value of "/foo/bar".
	 *
	 * Sin embargo, también se evalúa un medio alterno ya que según el manual en
	 * https://www.php.net/manual/en/reserved.variables.server.php :
	 *
	 * > There is no guarantee that every web server will provide any of these; servers may omit some, or provide
	 * > others not listed here.
	 *
	 * @return string Referencia identificada.
	 */
	private function requestURI() {

		$retornar = miframe_data_get('request-uri', '?');
		if ($retornar === '?') {
			// No se ha declarado aún
			$retornar = strtolower(miframe_server_get('PATH_INFO'));
			if ($retornar == '') {
				// Intenta recuperar manualmente.
				// Recupera URI sin argumentos
				$request_uri = parse_url(strtolower(miframe_server_get('REQUEST_URI')), PHP_URL_PATH);
				// Si el $request_uri termina en "/", adiciona el basename() del root
				$root = $this->documentRoot();
				if (substr($request_uri, -1, 1) == '/') { $root = $this->getURIbase(); }
				if  ($request_uri !== $root) {
					// No está consultando el script base (usualmente "index.php")
					// Recupera del URI la parte que identifica el recurso solicitado.
					$retornar = $this->removeURIbase($request_uri);
					$filename = '';
					if ($retornar == '') {
						// No hay info adicional, puede que este script se haya invocado desde una URL
						// alterna o un directorio virtual. En este caso, recupera el path invocado directamente
						// para validar si existe físicamente un recurso asociado.
						$filename = miframe_server_get('SCRIPT_FILENAME');
					}
					else {
						// Complementa el path recibido para ubicar el archivo físico
						$filename = $this->createPath($retornar);
						// Asigna valor a 'PATH_INFO' para evitar repetir este ciclo si se invoca de nuevo
						miframe_data_put('request-uri', $retornar);
					}
					if (file_exists($filename)) {
						// Valida que no sea un path directo, esos deberían pasar directamente al archivo llamado. Por ej:
						// [REQUEST_URI] => /micode/projects/edit/php-demo.php
						// Esto significa que el direccionamiento en el servidor web fue deficiente.
						// Intenta de todas formas resolverlo.
						$this->detour($filename);
					}
				}
			}
		}

		// No incluye primer "/" (a menos que solo contenga ese caracter)
		if ($retornar != '' && $retornar != '/' && substr($retornar, 0, 1) === '/') {
			$retornar = substr($retornar, 1);
		}

		return $retornar;
	}

	/**
	 * Retorna arreglo de rutas usado.
	 *
	 * @return array Arreglo de rutas.
	 */
	public function usedMap() {

		return $this->map_usado;
	}

	/**
	 * Asigna manualmente la base para evaluar el enrutamiento.
	 * Si se asigna en blanco, autodetecta al ejecutar $this->bindPost().
	 *
	 * @param string $method_bind Puede tomar valores: "post", "get", "request" (puede ser cualquiera POST o GET, esta
	 *   es la opción por defecto), "uri" para evaluar el REQUEST_URI.
	 */
	public function bindMethod(string $method_bind) {
		$this->method_bind = strtolower(trim($method_bind));
	}

	private function bindMethodAutoDetect() {

		$reference = 'miframe-autocheckbindmethod';
		$request_uri = $this->requestURI();
		$filename = $this->dir_temp . '/router-' . md5($reference) . '.data';
		$validando = false;
		$metodo = '';

		if (file_exists($filename) && filemtime($filename) > filemtime(__FILE__)) {
			// Lee contenido del archivo (debe ser mas reciente que este script)
			$contenido = strtolower(trim(file_get_contents($filename)));
			if ($contenido != '') {
				$arreglo = explode("\n", $contenido . "\n");
				// Toma solamente el primer elemento
				$metodo = trim($arreglo[0]);
			}
			// Confirma si está o no validando
			$validando = ($metodo === 'starting...');
		}
		elseif ($request_uri != '') {
			// Soporta URI (automáticaente detectado)
			$metodo = 'uri';
		}
		else {
			// Está consultando el index o no soporta redirección por URI
			// No ha realizado validación.
			// Inicia creando el archivo de control.
			file_put_contents($filename, 'starting...');
			// Realiza consulta
			$validando = true;
		}

		if ($validando) {
			// Modifica contenido para prevenir ciclos eternos
			file_put_contents($filename, 'checking...');
			// Realiza auto-consulta por URI (ej. http://localhost/micode/autocheckbindmethod)
			$enlace = $this->getServerURL($reference);
			// echo "$enlace<hr>";
			$contenido = @file_get_contents($enlace);
			// echo "<hr>$enlace<hr>$contenido<hr>";
			// file_put_contents($filename, 'checking...' . PHP_EOL . $enlace . PHP_EOL . $contenido);
			$metodo = 'request';
			if ($contenido === 'miFrame/Router URI OK') {
				// Soporta modelo URI
				$metodo = 'uri';
			}
			// Actualiza archivo de control
			file_put_contents($filename, $metodo . PHP_EOL . $enlace . PHP_EOL . $contenido . PHP_EOL . '--- ' . date('Y-m-d H:i:s'));
		}

		if (strtolower($request_uri) === $reference) {
			// Respuesta cuando está ejecutanto una validación (si no soporta el URI retorna vacio)
			exit("miFrame/Router URI OK");
		}
		elseif (in_array($metodo, ['uri', 'request', 'post', 'get'])) {
			$this->bindMethod($metodo);
		}
		elseif ($metodo === 'checking...') {
			// Reporta error
			exit("ERROR: miFrame/Router auto-Validación en proceso");
		}
		else {
			miframe_error('Método de consulta para Router ($1) no soportado/detectado', $metodo);
		}

		return $metodo;
	}

	/**
	 * Captura valor de la referencia asociada al enrutamiento.
	 * Sugerencia: Esta función debe ejecutarse después de $this->setURIbase(), especialmente cuando $this->method_bind = 'URI".
	 *
	 * @param string $name Nombre del parámetro REQUEST asociado. Cuando se usa método "uri", el $name es usado para guardar
	 *       el valor capturado bajo ese nombre.
	 * @param string $method_bind Restricción al origen del dato: "post", "get", "request" (POST o GET) o "uri". Si no se indica
	 *      valor alguno, autodetecta el origen en el siguiente orden: uri, post/get, request.
	 * @return bool TRUE si fue posible capturar el valor de referencia.
	 */
	function bindPost(string $name) {

		$this->request = array();
		$this->recibido = false;
		$this->request_param = trim($name);

		if ($this->request_param == '') { return false; }

		$valor = '';
		$method_bind = $this->method_bind;
		if ($method_bind == '') {
			// Autodetecta método (request/uri)
			$method_bind = $this->bindMethodAutoDetect();
		}

		if ($method_bind == 'uri') {
			$valor = $this->requestURI();
		}
		else {
			$collector = '_REQUEST';
			$tipos = array('post' => '_POST', 'get' => '_GET');
			if (isset($tipos[$method_bind])) {
				$collector = $tipos[$method_bind];
			}
			else {
				$method_bind = 'request';
			}
			if (isset($GLOBALS[$collector])
				&& isset($GLOBALS[$collector][$name])
				&& is_string($GLOBALS[$collector][$name])
				) {
				$valor = trim($GLOBALS[$collector][$name]);
			}
		}

		if ($valor != '') {
			$this->request = explode('/', strtolower($valor));
			$this->matchSuccessful = false;
			$this->recibido = true;
		}

		// Almacena valor para referencias externas
		$this->setParam($this->request_param, $this->request());

		return $this->recibido;
	}

	/**
	 * Carga enrutamientos listados de un archivo .ini.
	 *
	 *
	 * @param string $filename Nombre del archivo .ini a cargar,
	 * @param bool $rewrite TRUE para remplazar enrutamientos existentes. FALSE adiciona a los ya registrados.
	 * @param string $dirbase Path a usar para ubicar los scripts.
	*/
	public function loadConfig(string $filename, bool $rewrite = false) {

		if (!file_exists($filename)) {
			miframe_error('Archivo no encontrado: $1', $filename);
		}

		$data = parse_ini_file($filename, true, INI_SCANNER_RAW);

		foreach ($data as $type => $group) {
			$this->loadConfigArray($type, $group, $rewrite);
		}
	}

	public function clearRoutes(string $type = '') {

		if ($type == '') {
			$this->rutas = array();
		}
		elseif (isset($this->rutas[$type])) {
			$this->rutas[$type] = array();
		}
	}

	public function addPrepareAction(string $reference, string $action) {

		// Permite $action = '' para evitar ejecutar el prepare cuando usa "*"
		$reference = strtolower(trim($reference));
		if ($reference != '') {
			if (!is_array($this->prepare_include)) { $this->prepare_include = array(); }
			$this->prepare_include[$reference] = $action;
		}
	}

	/**
	 * Carga enrutamientos definidos en un arreglo.
	 *
	 * Para el archivo .ini se deben definir tres grupos: "config", "general" y "map" (y/o "map-xxxx").
	 * El grupo "config" permite realizar configuraciones a la clase.
	 * El grupo "map" puede personalizarse para un método request especifico, sea "GET", "POST", "PUT", "DELETE"
	 * u otro. Para esto, se debe adicionar a "map" el nombre del método, por ejemplo: como "map-get". El método
	 * se toma de la variable global $_SERVER['REQUEST_METHOD'].
	 * El grupo "map" global (sin método asociado) deberá definirse primero de forma que puedan redefinirse
	 * elementos en el "map" personalizado por método.
	 * Ejemplo:
	 *
	 * 	   [config]
	 * 	   debug = true
	 *     search-path = (path para buscar scripts)
	 *     method = (método por defecto: uri, get, post, request)
	 *
	 *     [general]
	 *     default = (script a ejecutar cuando no recibe enrutamiento o el enrutamiento apunta al index.php)
	 *     abort = (script a ejecutar en respuesta a $this->abort())
	 *
	 *     [map]
	 *     (enrutamiento) = (script a ejecutar)
	 *     ...
	 *
	 *     [map-get]
	 *     (enrutamiento) = (script a ejecutar)
	 *     ...
	 *
	 * Las reglas para la declaración del enrutamiento se describen en la documentación de la función runOnce().
	 *
	 * El grupo "map" contiene declaración de enrutamientos aplicados para cualquier método de consulta sea web
	 * (GET, POST) o los adiconales usados para servicios web (HEAD, DELETE, PUT, PATCH). Para definir el mapa de
	 * enrutamientos propios de un método específico, use el grupo "map-xxxx" donde la "xxxx" corresponde al nombre
	 * del método de interés.
	 *
	 * El grupo "config" permite realizar configuraciones a la clase. Las opciones validas para este grupo son:
	 * - debug: boolean. TRUE para presentar mensajes de depuración.
	 * - method: string. Puede ser "post", "get", "request" o "uri". Método usado para detectar el enrutamiento.
	 * - uri-base: string. Definición del URI base esperado, especialmente requerido si el método de captura es "uri".
	 *
	 * @param string $type Elemento a asignar: "general" o "map".
	 * @param array $data Arreglo de datos a cargar.
	 * @param bool $rewrite TRUE para remplazar enrutamientos existentes. FALSE adiciona a los ya registrados.
	 * @param bool TRUE si se pudo relacionar el arreglo, FALSE en otro caso.
	*/
	private function loadConfigArray(string $type, array $data,  bool $rewrite = false) {

		$type = strtolower(trim($type));

		// Método request asociado
		$metodo_map = '';
		// Valida si contiene el metodo asociado (REQUEST_METHOD)
		if (strtolower(substr($type, 0, 4)) == 'map-') {
			$metodo_map = strtolower(trim(substr($type, 4)));
			$type = 'map';
		}

		if (!in_array($type, [ 'general', 'map', 'config', 'prepare' ])) { return false; }

		// Valida que existan los elementos mínimos
		if (in_array($type, [ 'general', 'map' ]) && !isset($this->rutas[$type])) {
			$this->rutas[$type] = array();
		}

		foreach ($data as $reference => $accion) {
			if ($type == 'config') {
				// Opciones de configuracion
				switch ($reference) {
					// case 'method':
					// 	// Fija método inicial
					// 	$this->bindMethod($accion);
					// 	break;
					case 'strict':
						$this->strict = ($accion !== false && $accion != 'false' && $accion > 0);
						// print_r($this->strict); echo "<hr>$accion " . (intval($accion) > 0); exit;
						break;
					case 'debug':
						$this->debug = ($accion !== false && $accion != 'false' && $accion > 0);
						break;
					default:
				}
				continue;
			}
			elseif ($type == 'prepare') {
				$this->addPrepareAction($reference, $accion);
				continue;
			}

			// echo "$type : $reference : $accion : $metodo_map<hr>";
			$arreglo = explode('|', $accion . '|');
			$this->addRoute($type, $reference, $arreglo[0], $arreglo[1], $metodo_map);
		}

		return true;
	}

	/**
	 * Adiciona nueva ruta a evaluar posteriormente.
	 * Consulte la función runOnce() para el detalle de cómo se determina el enrutamiento a partir del
	 * path de referencia.
	 *
	 * @param string $type Elemento a asignar: "general" o "map".
	 * @param string $reference Path de referencia para determinar el enrutamiento.
	 * @param string $description Descripción del elemento.
	 * @param string $method Método asociado (POST, GET, etc.). En blanco, cualquiera (se ignora para $type = "general").
	 * @param string $action Script y/o función a ejecutar.
	 */
	public function addRoute(string $type, string $reference, string $action, string $description = '', string $method = '') {

		$type = strtolower($type);

		if (in_array($type, [ 'general', 'map' ])) {
			$reference = strtolower(trim($reference));
			$action = trim($action);
			$method = strtolower(trim($method));
			if ($method == '') { $method = '@any'; }
			if ($reference != '' && $action != '') {
				if ($type == 'general') {
					$this->rutas[$type][$reference] = $action;
				}
				else {
					// Incluye método de consulta. Separa el primer elemento
					$arreglo = explode('/', $reference, 2);
					$primero = strtolower($arreglo[0]);
					$resto = '';
					if (isset($arreglo[1])) { $resto = strtolower($arreglo[1]); }
					$this->rutas[$type][$primero][$resto][$method] = array(
						'action' => $action,
						'description' => trim($description)
					);
				}
			}
		}
	}

	/**
	 * Retorna rutas declaradas por medio de archivo .ini.
	 *
	 * @return array Arreglo de enrutamientos.
	 */
	public function getLoadedRoutes() {
		return $this->rutas;
	}

	/**
	 * Confirma si puede proceder a validar enrutamiento.
	 * Esto es, cuando no ha encontrado un enrutamiento valido ($this->matchSuccessful = true) o cuando se permite la ejecución de
	 * múltiples enrutamientos ($this->multipleMatch = true).
	 *
	 * @return bool TRUE si puede proceder a validar. FALSE en otro caso.
	 */

	public function continue() {
		return (!$this->matchSuccessful || $this->multipleMatch);
	}

	private function getRoute($reference, $type = '') {

		$accion = '';
		if ($type != '') {
			// $type = $reference; // Usualmente "map"
			$metodo = strtolower($_SERVER['REQUEST_METHOD']);
			$metodo_any = '@any';
			if (isset($this->rutas['map'][$type][$reference][$metodo])) {
				$accion = $this->rutas['map'][$type][$reference][$metodo]['action'];
			}
			elseif (!$this->strict && isset($this->rutas['map'][$type][$reference][$metodo_any])) {
				$accion = $this->rutas['map'][$type][$reference][$metodo_any]['action'];
				// echo "ACCION OK $accion<hr>";
			}
			// miframe_debug_box("{$this->strict} : $type : $reference : $metodo = $accion --> " . isset($this->rutas[$type][$reference][$metodo_any]), 'getRoute');
			// ECHO "<PRE>"; print_r($this->rutas); print_r($this->request); echo "</pre><hr>";
		}
		elseif (isset($this->rutas['general'][$reference])) {
			// Generales
			$accion = $this->rutas['general'][$reference];
		}

		return $accion;
	}

	/**
	 * Evalúa y ejecuta enrutamientos declarados en archivo .ini.
	 *
	 * @return bool TRUE si encuentra un enrutamiento valido, FALSE en otro caso.
	 */
	public function run() {

		// Evalua si no hay datos recibidos, en ese caso ejecuta la opción "default"
		if ($this->continue()) {
			$accion = $this->getRoute('default');
			if ($accion != '') {
				$this->runDefault($accion);
			}
		}

		if (isset($this->rutas['map'])) {
			// Evalua rutas programadas por método
			$this->runMap();
		}

		return $this->matchSuccessful;
	}

	private function runMap() {

		if ($this->continue()
			&& isset($this->rutas['map'])
			&& count($this->rutas['map']) > 0
			&& count($this->request) >= 1
			) {
			// Usa una copia en caso que $this->rutas sea modificado al ejecutar algún include
			$primero = strtolower($this->request[0]);
			if (isset($this->rutas['map'][$primero])) {
				$rutas = array_keys($this->rutas['map'][$primero]);
				sort($rutas); // Asegura se evalue correctamente (primero las acciones sin args)
				foreach ($rutas as $k => $reference) {
					$accion = $this->getRoute($reference, $primero);
					// echo "PRIMERO $primero / $reference = $accion<hr>";
					if ($accion != '') {
						if ($reference != '') { $reference = '/' . $reference; }
						// echo "PRIMERO $primero$reference = $accion<hr>";
						if ($this->runOnce($primero . $reference, $accion)) {
							if (!$this->multipleMatch) { break; }
						}
					}
				}
			}
		}

		// miframe_debug_box($this->rutas, 'Rutas');
	}

	/**
	 * Evalúa enrutamiento.
	 *
	 * El path de referencia para el enrutamiento debe declararse siguiendo uno de los siguientes formatos:
	 *
	 * - Path absoluto. Ej: "path1/path2". En este caso si recibe "path1/path2/valor1/valor2", no lo tomará como una
	 *   coincidencia valida.
	 * - Path relativo con parámetros variables, se indican con "?". Ej: "path1/path2/?arg1/arg2". El valor para "arg1" y "arg2"
	 *   son registrados en el arreglo $this->params. Este enrutamiento se ejecutará tanto si se invoca "path1/path2/valor1/valor2", como si
	 *   se invoca "path1/path2" ("arg1" y "arg2" se definen con valor en blanco). Si por el contrario, recibe "path1/path2/valor1/valor2/valor3"
	 *   se almacenará "valor1" en "arg1" y en "arg2" almacenará "valor2/valor3". En este caso es también viabe que intente recuperar
	 *   el valor de los argumentos de $_REQUEST, para cubrir el escenario en que hayan sido enviados a través de un formulario con los
	 *   valores desglosados.
	 *
	 * En caso de declarar `$this->autoExport` = true, cargará los valores de $this->params en $_REQUEST (no modifica $_POST ni $_GET).
	 * Si la accion asociada es vacio, busca archivo usando como patron el valor de $reference (adicionando extensión ".php").
	 *
	 * @param string $reference Path de referencia para determinar el enrutamiento.
	 * @param string $action Script y/o función a ejecutar.
	 * @return bool TRUE si encuentra un enrutamiento valido, FALSE en otro caso.
	 */
	public function runOnce(string $reference, string $action = '') {

		// Valida si ya fue ejecutado
		if (!$this->continue()) { return false; }

		$reference_arr = explode('/', strtolower($reference));
		$nueva_reference = '';
		$ultimo_path = '';
		$capturando = false;
		$this->params = array();
		$this->map_usado = '';
		// Inicializa arreglo con el valor recibido previamente
		$this->setParam($this->request_param, $this->request());

		$count_request = count($this->request);

		foreach ($reference_arr as $k => $path) {
			$path = trim($path);
			if (substr($path, 0, 1) == '?') {
				$capturando = true;
				$path = trim(substr($path, 1));
			}
			if (!$capturando) {
				// Evaluando path
				if (!isset($this->request[$k]) || trim($this->request[$k]) !== $path) {
					return false;
				}
				if ($nueva_reference != '') { $nueva_reference .= '/'; }
				$nueva_reference .= $path;
			}
			else {
				// Registrando valores
				$valor = '';
				if (isset($this->request[$k])) { $valor = trim($this->request[$k]); }
				elseif ($path != '' && isset($_REQUEST[$path]) && is_string($_REQUEST[$path])) { $valor = trim($_REQUEST[$path]); }
				$this->setParam($path, $valor);
				$ultimo_path = $path;
			}
		}

		if ($count_request > $k + 1) {
			if ($capturando) {
				// El último elemento fue una variable de captura, adiciona el resto
				// del path a esa variable (ej: un path de archivo)
				if ($ultimo_path != '') {
					for ($k = $k + 1; $k < $count_request; $k ++) {
						$this->params[$ultimo_path] .= '/' . trim($this->request[$k]);
					}
				}
			}
			else {
				// Hay mas datos en lo recibido que en el patron de busqueda y el patron no tiene
				// para capturar valores, luego lo da como fallido.
				return false;
			}
		}

		// Si no definió acción, usa el mismo valor de referencia
		if ($action == '') {
			$action = $nueva_reference . '.php';
		}

		// Actualiza valor del parametro a buscar (no modifica $this->request)
		$this->setParam($this->request_param, $nueva_reference);

		return $this->runAction($action, $reference);
	}

	/**
	 * Acción a realizar si no se detecta enrutamiento.
	 *
	 * @param string $action Script y/o función a ejecutar.
	 * @return bool TRUE si encuentra un enrutamiento valido, FALSE en otro caso.
	 */
	public function runDefault(string $action, bool $force = false) {

		$reference = '(default-forced)';
		if (!$force) {
			// Si no se forza manualmente, valida si procede por vía regular
			$force = ($this->continue() && !$this->recibido);
			$reference = '(default)';
		}

		if ($force) {
			return $this->runAction($action, $reference);
		}

		return false;
	}

	/**
	 * Ejecuta script y/o función relacionada a un enrutamiento valido.
	 * El script se ejecuta en un entorno aislado de la clase actual pero recibe esta clase com parámetro bajo el nombre `$thisRouter`.
	 * Esto para prevenir que se puedan modificar atributos privados de la clase.
	 * El valor de $action puede ser:
	 * - Nombre de script (absoluto o relativo a $this->path_files).
	 * - Nombre de script + ":" + Nombre de función incluída en el script (recibe como único parámetro esta clase).
	 * - ":" + Función previamente definida (recibe como único parámetro esta clase).
	 *
	 * @param string $action Script y/o función a ejecutar.
	 * @param string $reference Path para el enrutamiento (para documentación si `$this->debug` = true).
	 * @return bool TRUE si encuentra un enrutamiento valido, FALSE en otro caso.
	 */
	private function runAction(string $action, string $reference, bool $prepare = false) {

		// Exporta parametros al request
		if ($this->autoExport) {
			$this->export($_REQUEST);
		}

		// Valida si tiene algo pendiente por procesar
		if (!$prepare && is_array($this->prepare_include)) {
			// echo "BUSCAR PREPARE"; exit;
			$action_prepare = '';
			// Revisa todos, de forma que si existe un "*" como referencia, pueda excluir algún
			// archivo al declararlo en blanco.
			foreach ($this->prepare_include as $llave => $info) {
				$request = strtolower($this->request());
				if (substr($llave, -1, 1) == '*') {
					// Termina en "*", valida parcialmente la llave
					$llave = strtolower(substr($llave, 0, -1));
					if ($llave == '') {
						// Aplica para todos
						$action_prepare = $info;
					}
					elseif (substr($request, 0, strlen($llave)) === $llave) {
						// Coincidencia
						$action_prepare = $info;
					}
				}
				elseif (strtolower($llave) === $request) {
					// La concidencia debe ser full
					$action_prepare = $info;
				}
			}
			// echo "PREPARE $request : $action_prepare<hr>"; exit;
			if ($action_prepare != '') {
				$this->runAction($action_prepare, '(prepare)', true);
				// Restablece referencias
				$this->matchSuccessful = false;
			}
		}

		$this->map_usado = $reference;

		$filename = trim($action);
		$funcion = '';
		$path = '';
		$pos = strrpos($action, ':');

		// Ignora casos como "C:/xxxx" que corresponde a un nombre de archivo
		if ($pos !== false && ($pos === 0 || $pos > 1)) {
			$filename = trim(substr($action, 0, $pos));
			$funcion = trim(substr($action, $pos + 1));
		}

		if ($filename != '') {
			$path = $filename;
			if (!file_exists($path) && strpos($filename, ':') === false && $this->path_files != '') {
				$path = miframe_path($this->path_files, $filename);
			}
			if (!file_exists($path)) {
				miframe_error('Archivo no encontrado para la referencia *$1*', $reference, debug: $path);
				return false;
			}
		}
		elseif ($funcion == '') {
			// No define filename ni función y requiere alguna de las dos.
			miframe_error('Acción "$1" no valida para la referencia *$2*', $action, $reference);
			return false;
		}

		$ejecutado_local = false;

		// Ejecuta include asegurando que esté aislado para no acceder a elementos privados de esta clase
		if ($this->include($path, 'INCLUDE ' . $reference . ' --> ' . $filename)) {
			$this->matchSuccessful = true;
			$ejecutado_local = true;
		}

		if ($funcion != '') {

			// Las funciones se validan al ejecutar pues si está asociada a un archivo, no estará
			// disponible sino hasta el cargue del archivo en cuestión.
			if (!function_exists($funcion)) {
				miframe_error('Función $1() no existe para la acción *$2* con referencia *$3*', $funcion, $action, $reference);
			}

			if ($this->debug) {
				// error_log('MIFRAME/ROUTER FUNCION ' . $reference . ' --> ' . $funcion);
				$this->printDebug('FUNCTION ' . $reference . ' --> ' . $funcion);
			}

			// La función usa esta clase como unico argumento
			$funcion($this);

			$this->matchSuccessful = true;
			$ejecutado_local = true;
		}

		// $this->matchSuccessful podria ser TRUE de un enrutamiento anterior pero haber fallado en este,
		// por eso se valida $ejecutado_local
		if ($ejecutado_local && $this->stopScript && !$prepare) {
			// Valida si debe ejecutar algo antes
			$this->stop();
		}

		return $this->matchSuccessful;
	}

	/**
	 * Detiene la ejecución del script principal.
	 * Valida si se ha definido alguna acción a realizar antes de ejecutar la detención.
	 */
	public function stop() {

		$accion = $this->getRoute('before-stop');
		if ($accion != '' && !$this->deteniendo) {
			// La elimina para evitar un ciclo infinito al reinvocar esta función include().
			$this->deteniendo = true;
			$this->runAction($accion, '(before-stop)');
			$this->deteniendo = false;
		}

		exit;
	}

	/**
	 * Asocia script y/o función a ejecutar al invocar `$this->abort()`.
	 *
	 * @param string $action Script y/o función a ejecutar.
	 */
	public function abortHandler(string $action) {

		$this->addRoute('general', 'abort', $action);
	}

	/**
	 * Asocia script y/o función a ejecutar al invocar `$this->stop()`.
	 *
	 * @param string $action Script y/o función a ejecutar.
	 */
	public function beforeStopHandler(string $action) {

		$this->addRoute('general', 'before-stop', $action);
	}

	/**
	 * Ejecuta acción cuando no se han encontrado enrutamientos validos.
	 *
	 * @param string $title Titulo
	 * @param string $message Mensaje
	 * @param string $footnote Mensaje adicional (usualmente para mostrar en diferente formato)
	 */
	public function notFound(string $title, string $message, string $footnote = '') {

		if (!$this->matchSuccessful) {
			if (!headers_sent()) {
				header("HTTP/1.1 404 Not Found");
			}
			$this->abort($title, $message, $footnote, $this->stopScript);
		}
	}

	/**
	 * Ejecuta acción cuando se aborta o cancela un enrutamiento al invocar `$this->abort()`.
	 * Almacena los valores de título y mensaje en `$this->params` para permitir que sean luego recuperados para
	 * su uso desde el script invocado.
	 *
	 * @param string $title Titulo
	 * @param string $message Mensaje
	 * @param string $footnote Mensaje adicional (usualmente para mostrar en diferente formato)
	 * @param bool $stopScript TRUE para detener la ejecución del script principal (valor por defecto). FALSE, continua.
	 */
	public function abort(string $title, string $message, string $footnote = '', bool $stopScript = true) {

		$ejecutado = false;

		error_log('ROUTER ABORT - ' . strip_tags($title . ': ' . $message));

		$accion = $this->getRoute('abort');
		if (!$this->abortando
			&& $accion != ''
			) {
			$this->abortando = true; // Previene ciclo infinito si falla include()
			$this->params['title'] = $title;
			$this->params['message'] = $message;
			$this->params['footnote'] = $footnote;
			$this->stopScript = $stopScript;

			$ejecutado = $this->runAction($accion, '(abort)');
			$this->abortando = false;
		}

		if (!$ejecutado) {
			// Si no pudo ejecutar lo anterior, presenta mensaje base
			// Mensaje con error a pantalla
			$message = nl2br($message);
			$this->printDebug('Ejecución cancelada');
			$this->print("<h1>$title</h1>\n<p>$message</p>", $footnote);
		}

		if ($stopScript && !$this->abortando) { $this->stop(); }
	}

	/**
	 * Procedimiento adicional a ejecutar cuando se invoca `$this->detour()`.
	 *
	 * @param callable $function Función a ejecutar.
	 */
	public function detourCall(callable $function) {

		$this->detour_handler = $function;
	}

	/**
	 * Ejecuta script que no está asociados a alguno de los enrutamientos declarados.
	 * Esto usualmente permite al sistema intentar recuperar scripts recibidos por enrutamientos erróneos realizados
	 * por el servidor web y detectados al evaluar el REQUEST_URI.
	 * También puede usarse para ejecutar scripts en un entorno aislado al actual.
	 *
	 * @param string $filename Script a ejecutar.
	 * @param bool $export_file Exportar archivo. TRUE envía headers para guardar archivo, FALSE envía directo al navegador.
	 */
	public function detour(string $filename, bool $export_file = false) {

		if (file_exists($filename) && $this->allowDetour) {

			$this->printDebug(miframe_text('Enrutamiento correcto? $1', $filename));

			// Suspende cualquier proceso adicional en curso
			if (is_callable($this->detour_handler)) {
				call_user_func($this->detour_handler);
			}

			// Forza terminación de capturas previas (incluida la generada al inicio de Router)
			// while (ob_get_level()) { ob_end_clean(); }

			if (strtolower(substr($filename, -4)) == '.php') {
				// Cambia al directorio del archivo
				chdir(dirname($filename));
				// Ejecuta en modo privado
				$this->include(basename($filename),
					miframe_text('DETOUR: Script **$1** ejecutado localmente', $filename),
					miframe_text('Si este no era el comportamiento esperado, favor revisar enrutamiento en el servidor web.'),
					true
					);
			}
			else {
				// Envia archivo directamente a pantalla
				// o para guardar, según se defina en $export_file
				// (las imágenes las envía directo al navegador siempre?)
				if ($export_file) {
					// header('Content-Description: File Transfer');
					$mimetype = mime_content_type($filename);
					$name = basename($filename);
					$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
					$size = filesize($filename);

					header('Content-Type: ' . $mimetype);
					// if (!in_array($extension, [ 'jpg', 'jpeg', 'gif', 'png', 'svg', 'mp4' ])) {
						header('Content-Disposition: attachment; filename="' . $name . '"');
					// }
					header('Expires: 0');
					header('Cache-Control: must-revalidate');
					header('Pragma: public');
					header('Content-Length: ' . $size);
				}

				readfile($filename);
			}

			exit;
		}
		else {
			$request_uri = parse_url(miframe_server_get('REQUEST_URI'), PHP_URL_PATH);
			$this->matchSuccessful = false;
			$this->notFound(
				miframe_text('Solicitud no soportada'),
				'<p>' .
				miframe_text('La solicitud para **$1** no pudo ser procesada.', $request_uri) .
				'</p>' .
				miframe_text('El archivo solicitado no existe en el servidor o no tiene permisos para su ejecución.')
				);
		}
	}

	/**
	 * Recupera el parámetro indicado.
	 *
	 * @param string $name Nombre del parámetro a buscar.
	 * @param string $default Valor a retornar si el parámetro no ha sido previamente declarado en $this->params.
	 * @return mixed Valor del parámetro deseado.
	 */
	public function param(string $name, string $default = '') {

		$valor = '';
		if ($this->paramExists($name)) {
			$valor = trim($this->params[$name]);
		}
		if ($valor === '') { $valor = $default; }

		return $valor;
	}

	public function setParam(string $name, mixed $value) {
		$name = trim($name);
		if ($name != '') {
			$this->params[$name] = $value;
		}
	}

	/**
	 * Soporte para definición de enlaces y/o formularios.
	 * Si el método de detección es "post", retorna un arreglo con los valores a usar para crear el formulario.
	 * En otro caso, retorna el enlace ya listo para su uso.
	 *
	 * @return mixed Enlace a usar o arreglo de datos (método de detección "post")
	 */
	public function getFormAction(string $request_param = null, bool $force_get_mode = false, mixed $params = false) {

		$accion = $this->documentRoot();
		if (is_null($request_param)) {
			$request_param = $this->request();
		}
		if (is_array($params)) {
			$params = http_build_query($params);
		}

		if ($this->method_bind == 'uri') {
			$accion = $this->createURL($request_param);
			if ($params != '') {
				$accion .= '?' . $params;
			}
		}
		elseif ($this->request_param != '') {
			if ($this->method_bind != 'post' || $force_get_mode) {
				$accion .= '?' . $this->request_param . '=' . urlencode($request_param);
				if ($params != '') {
					$accion .= '&' . $params;
				}
			}
			else {
				// Si forza el modo POST, la acción debe configurarse manualmente en el formulario.
				// En este caso ignora $params.
				$accion = array(
					'action' => $accion,
					'param' => $this->request_param,
					'value' => $request_param
					);
			}
		}

		return $accion;
	}

	public function reload(string $request_param = null, mixed $params = false, mixed $data = false) {

		if (!is_array($params)) {
			// Asume es una cadena del tipo "a=xx&b=xx.."
			$pre = $params;
			$params = array();
			if (is_string($pre) && $pre != '') {
				parse_str($pre, $params);
			}
		}

		if ($data !== false) {
			$dirname = miframe_temp_dir('micode-cache-reloads', true);
			$basename = uniqid();
			$m = intval(rand(0, 9));
			$filename = miframe_path($dirname , $basename . dechex($m));
			while (file_exists($filename)) {
				$m ++;
				$filename = miframe_path($dirname , $basename . dechex($m));
			}
			$params['micodedata'] = basename($filename);
			miframe_serialize($filename, $data);
		}

		$enlace = $this->getFormAction($request_param, true, $params);

		// Complementa con headers? Falta el hostname...
		echo "<script>window.location='$enlace';</script>";
		exit;
	}

	public function getDataReloaded(bool $delete_file = false) {

		$data = false;
		if (isset($_REQUEST['micodedata']) && $_REQUEST['micodedata'] != '') {
			$dirname = miframe_temp_dir('micode-cache-reloads');
			$filename = miframe_path($dirname , str_replace('..', '_', $_REQUEST['micodedata']));
			if (file_exists($filename)) {
				$data = miframe_unserialize($filename);
				if ($delete_file) {
					unlink($filename);
				}
			}
		}

		return $data;
	}

	/**
	 * Muestra en pantalla información de la clase actual.
	 */
	public function showInfo() {

		echo miframe_box('INFO ROUTER',
			'<ul>' .
			'<li><b>URI base:</b> ' .		$this->getURIbase() . '</li>' .
			'<li><b>Path base:</b> ' .			$this->getDirbase() . '</li>' .
			'<li><b>Document Root:</b> ' .		$this->documentRoot() . '</li>' .
			'<li><b>Script principal:</b> ' .	$this->scriptName() . '</li>' .
			'<li><b>Rutas:</b><pre>' .			print_r($this->getLoadedRoutes(), true) . '</pre></li>' .
			'</ul>'
			);
	}
}