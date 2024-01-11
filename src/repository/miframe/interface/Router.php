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
 * SUGERENCIA: Manejo de HTTPS y localhost
 *
 * @micode-uses miframe/common/functions
 * @micode-uses miframe/file/serialize
 * @author John Mejia
 * @since Abril 2022
 */

namespace miFrame\Interface;

/**
 * Clase usada para realizar el enrutamiento dinámico.
 * Las siguientes propiedades públicas pueden ser usadas:
 * - $autoExport: boolean. TRUE carga los argumentos encontrados al validar el enrutamiento en la variable global $_REQUEST
 *   (no se modifica $_POST ni $_GET).
 * - $multipleMatch: boolean. TRUE para permitir que busque en todas las rutas programadas. FALSE suspende validaciones luego de la
 *   primer validación éxitosa.
 * - strict: boolean. TRUE requiere que se mapeen las rutas para cada método de acceso (GET, POST, etc.). FALSE permite mapeo genérico
 *  (puede representar un riesgo de seguridad según la aplicación).
 * - $stopScript: boolean. TRUE para terminar toda ejecución al encontrar la primer ruta valida.
 */
class Router extends \miFrame\Interface\Shared\BaseClass {

	private $request_param = '';
	private $request = array();
	private $recibido = false;
	private $rutas_privadas = array();
	private $rutas_publicas = array();
	// private $detour_handler = false;
	private $matchSuccessful = false;		// TRUE si encuentra al menos un match valido
	private $method_bind = '';
	private $abortando = false;
	private $deteniendo = false;
	private $ruta_usada = '';
	private $indexfile = '';
	private $dir_temp = '';
	private $uri_base = '';
	private $params = array();
	private $script_filename = '';
	private $dir_base = '';
	private $request_uri = '?';
	private $document_root = '';
	private $force_json = false;
	private $file_detected = '';

	public $autoExport = false;
	public $multipleMatch = false;
	public $strict = false;
	private $stopScript = true;

	public function __construct() {

		$this->setURIbase();
		$this->initialize();
		$this->clearRoutes();
		$this->assignMode();

		// Definiciones
		$this->script_filename = miframe_server_get('SCRIPT_FILENAME');
		$this->dir_base = realpath(dirname($this->script_filename));
		$this->document_root = $this->createURL(basename($this->script_filename));

		$this->color_debug = '#2e2072';
	}

	/**
	 * Retorna valor de referencia detectado.
	 *
	 * @return string Valor de referencia detectado por la función $this->captureUserAction().
	 */
	public function request() {

		return implode('/', $this->request);
	}


	public function requestStartWith(string $text) {

		return (isset($this->request[0]) && $this->request[0] === strtolower($text));
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
		return $this->getURIbase() . $path;
	}

	/**
	 * Retorna nombre del URL principal.
	 *
	 * @return string URL
	 */
	public function documentRoot() {

		return $this->document_root;
	}

	/**
	 * Path físico del script principal.
	 *
	 * @return string Path
	 */
	public function scriptFilename() {

		return $this->script_filename;
	}

	/**
	 * Path físico real esperado para el path relativo indicado.
	 *
	 * @param string $path Nombre del script o path a adicionar al directorio
	 *   base. Si no se indica, usa el asociado al script invocado.
	 * @return string Path
	 */
	public function createPath(string $script_name) {

		return miframe_path($this->getDirBase(), $script_name);
	}

	/**
	 * Directorio físico que contiene el script principal.
	 *
	 * @return string Path
	 */
	public function getDirBase() {

		return $this->dir_base;
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

		if ($this->request_uri === '?') {
			// No se ha declarado aún, recupera PATH_INFO (algunos servidores web no lo declaran).
			// PATH_INFO es el componente que va después del path asociado al index, por ejemplo:
			// Si el index está en /cliente/app/index.php
			// Y el recurso solicitado es /cliente/app/path1/path2
			// Entonces PATH_INFO sería /path1/path2.
			$retornar = strtolower(miframe_server_get('PATH_INFO'));
			// Archivo fisico detectado (si alguno)
			$this->file_detected = '';

			if ($retornar == '') {
				// Intenta recuperar manualmente.
				// Recupera URI sin argumentos (ignora todo despues de "?")
				$request_uri = parse_url(strtolower(miframe_server_get('REQUEST_URI')), PHP_URL_PATH);
				// No está consultando el script base (usualmente "index.php")
				// Recupera del URI la parte que identifica el recurso solicitado.
				// Retorna vacio si no contiene la URI base.
				$retornar = $this->removeURIbase($request_uri);
			}
			if ($retornar != '') {
				// Complementa el path recibido para ubicar el archivo físico
				$filename = $this->createPath($retornar);
				// Valida si se ha recibido un path de un archivo valido y es el script actual.
				if ($filename == $this->scriptFilename()) {
					$retornar = '';
				}
				elseif (file_exists($filename)) {
					// Esto no debería ocurrir ya que el servidor web debería proveerlo.
					// Esto puede pasar debido a una configuración fallida en el servidor.
					// Se procede a evaluar si puede desplegar el archivo solicitado.
					$this->file_detected = $filename;
				}
			}

			// No incluye primer "/" (a menos que solo contenga ese caracter)
			if ($retornar != '' && $retornar != '/' && substr($retornar, 0, 1) === '/') {
				$retornar = substr($retornar, 1);
			}

			// Asigna valor a 'PATH_INFO' para evitar repetir este ciclo si se invoca de nuevo
			$this->request_uri = $retornar;
		}

		return $this->request_uri;
	}

	/**
	 * Retorna arreglo de rutas usado.
	 *
	 * @return array Arreglo de rutas.
	 */
	public function selectedRoute() {

		return $this->ruta_usada;
	}

	/**
	 * Captura valor de la referencia asociada al enrutamiento.
	 * Sugerencia: Esta función debe ejecutarse después de $this->setURIbase(), especialmente cuando $this->method_bind = 'URI".
	 *
	 * @param  string $name_post   Nombre del parámetro REQUEST asociado. Cuando se usa método "uri", el $name es usado para guardar
	 *                             el valor capturado bajo ese nombre. Si no se designa valor y usa un método de captura
	 *  						   diferente al "uri", asigna "mcmx".
	 * @param  string $method_bind Restricción al origen del dato: "post", "get", "request" (POST o GET) o "uri". Si no se indica
	 *                             valor alguno, usa por defecto "uri".
	 * @return bool                TRUE si fue posible capturar el valor de referencia.
	 */
	function assignMode(string $method_bind = '', string $name_post = '') {

		$this->request_param = trim($name_post);

		if ($this->request_param == '') { $this->request_param = 'mcmx'; }

		$valor = '';
		// $method_bind = $this->method_bind;
		$this->method_bind = strtolower(trim($method_bind));
		if ($this->method_bind == '') {
			// Autodetecta método (request/uri)
			// $method_bind = $this->bindMethodAutoDetect();
			// No ha definido el método a usar. Por defecto intenta en modo URI
			$this->method_bind = 'uri';
		}
	}

	private function captureUserAction() {

		$this->request = array();
		$this->recibido = false;

		if ($this->method_bind == 'uri') {
			$valor = $this->requestURI();
		}
		else {
			$tipos = array('post' => '_POST', 'get' => '_GET', 'request' => '_REQUEST');
			if (!isset($tipos[$this->method_bind])) {
				// Error de configuración
				miframe_error('Método de selección no reconocido: $1. Esperaba "$2" o "uri".', $filename, implode('", "', array_keys($tipos)));
			}
			$collector = $tipos[$this->method_bind];
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
	 * Realiza definición de enrutamientos contenidos en un archivo .ini.
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
	 * 	   method = uri/get/post/request
	 *     name_post = cmd
	 *
	 *     [private]
	 *     default = (script a ejecutar cuando no recibe enrutamiento o el enrutamiento apunta al index.php)
	 *     abort = (script a ejecutar en respuesta a $this->abort())
	 *
	 *     [public]
	 *     (enrutamiento) = (script a ejecutar)
	 *     ...
	 *
	 *     [public-get]
	 *     (enrutamiento) = (script a ejecutar)
	 *     ...
	 *
	 * Las reglas para la declaración del enrutamiento se describen en la documentación de la función runOnce().
	 *
	 * El grupo "public" contiene declaración de enrutamientos aplicados para cualquier método de consulta sea web
	 * (GET, POST) o los adiconales usados para servicios web (HEAD, DELETE, PUT, PATCH). Para definir el mapa de
	 * enrutamientos propios de un método específico, use el grupo "map-xxxx" donde la "xxxx" corresponde al nombre
	 * del método de interés.
	 *
	 * El grupo "config" permite realizar configuraciones a la clase. Las opciones validas para este grupo son:
	 * - debug: boolean. TRUE para presentar mensajes de depuración.
	 * - method: string. Puede ser "post", "get", "request" o "uri". Método usado para detectar el enrutamiento.
	 * - name_post: string. Nombre de la variable asociada al modo de captura (no requerida para "uri").
	 *
	 * @param string $filename Nombre del archivo .ini a cargar,
	 * @param string $dirbase  Path a usar para ubicar los scripts.
	*/
	public function loadConfig(string $filename) {

		if (!file_exists($filename)) {
			miframe_error('Archivo no encontrado: $1', $filename);
		}

		$data = parse_ini_file($filename, true, INI_SCANNER_RAW);

		foreach ($data as $type => $group) {
			$metodo_map = ''; // Usado en rutas publicas
			if ($type == 'config') {
				// Configura modo de captura
				$method_bind = '';
				$name_post = '';
				if (isset($group['method'])) { $method_bind = $group['method']; }
				if (isset($group['name_post'])) { $name_post = $group['name_post']; }
				if ($method_bind != '' || $name_post != '') {
					$this->assignMode($method_bind, $name_post);
				}
				// Configura modo debug
				if (isset($group['debug'])) {
					$this->debug = boolval($group['debug']);
				}
			}
			elseif ($type == 'private') {
				$this->addPrivateRoutes($group);
			}
			else {
				if (strtolower(substr($type, 0, 7)) == 'public-') {
					// MRuta pública asociada a un método particular
					$metodo_map = strtolower(trim(substr($type, 7)));
					$type = 'public';
				}
				if ($type == 'public') {
					$this->addRoutes($group, $metodo_map);
				}
			}
		}
	}

	private function addPrivateRoutes(array $data) {

		// Metodos privados asociados
		$privados = array(
			'default' => 'addDefaultRoute',
			'abort' => 'addAbortRoute',
			'before_stop' => 'addBeforeStopRoute'
		);

		// Evalua contenido
		foreach ($data as $reference => $accion) {
			if (isset($privados[$reference])) {
				$metodo_clase = $privados[$reference];
				$this->$metodo_clase($accion);
			}
		}
	}

	public function clearRoutes() {

		$this->rutas_privadas = array(
			'default' => '',
			'abort' => '',
			'before-stop' => ''
			);
		$this->rutas_publicas = array();
	}

	/**
	 * Carga enrutamientos definidos en un arreglo.
	 *
	 * @param array  $data 	  Arreglo de datos a cargar.
	 * @param string $method  Método asociado (POST, GET, etc.). En blanco, cualquiera.
	 * @param bool  		  TRUE si se pudo relacionar el arreglo, FALSE en otro caso.
	*/
	private function addRoutes(array $data, string $method = '') {

		foreach ($data as $reference => $accion) {
			// La descripción se indica separando con "|" (opcional)
			$arreglo = explode('|', $accion . '|');
			$this->addRoute($reference, $arreglo[0], $arreglo[1], $method);
		}

		return true;
	}

	/**
	 * Adiciona nueva ruta pública a evaluar posteriormente.
	 * Consulte la función runOnce() para el detalle de cómo se determina el enrutamiento a partir del
	 * path de referencia.
	 *
	 * @param string $type 		  Elemento a asignar: "general" o "map".
	 * @param string $reference   Path de referencia para determinar el enrutamiento.
	 * @param string $action 	  Script y/o función a ejecutar.
	 * @param string $description Descripción del elemento.
	 * @param string $method 	  Método asociado (POST, GET, etc.). En blanco, cualquiera.
	 */
	private function addRoute(string $reference, string $action, string $description = '', string $method = '') {

		$reference = strtolower(trim($reference));
		$action = trim($action);
		$method = strtolower(trim($method));
		if ($method == '') { $method = '@any'; }
		if ($reference != '' && $action != '') {
			// Incluye método de consulta. Separa el primer elemento
			$arreglo = explode('/', $reference, 2);
			$primero = strtolower($arreglo[0]);
			$resto = '';
			if (isset($arreglo[1])) { $resto = strtolower($arreglo[1]); }
			$this->rutas_publicas[$primero][$resto][$method] = array(
				'action' => $action,
				'description' => trim($description)
			);
		}
	}

	/**
	 * Retorna rutas declaradas por medio de archivo .ini.
	 *
	 * @return array Arreglo de enrutamientos.
	 */
	public function getLoadedRoutes() {

		return array(
				'private' => $this->rutas_privadas,
				'public' => $this->rutas_publicas
				);
	}

	/**
	 * Confirma si puede proceder a validar enrutamiento.
	 * Esto es, cuando no ha encontrado un enrutamiento valido ($this->matchSuccessful = true) o cuando se permite la ejecución de
	 * múltiples enrutamientos ($this->multipleMatch = true).
	 *
	 * @return bool TRUE si puede proceder a validar. FALSE en otro caso.
	 */

	public function continue() {

		return ($this->file_detected == '' && (!$this->matchSuccessful || $this->multipleMatch));
	}

	private function getPublicRoute($reference, $startsWith = '') {

		$accion = '';
		if ($startsWith != '') {
			$metodo = strtolower($_SERVER['REQUEST_METHOD']);
			$metodo_any = '@any';
			if (isset($this->rutas_publicas[$startsWith][$reference][$metodo])) {
				$accion = $this->rutas_publicas[$startsWith][$reference][$metodo]['action'];
			}
			elseif (!$this->strict && isset($this->rutas_publicas[$startsWith][$reference][$metodo_any])) {
				$accion = $this->rutas_publicas[$startsWith][$reference][$metodo_any]['action'];
			}
		}

		return $accion;
	}

	/**
	 * Evalúa y ejecuta enrutamientos declarados en archivo .ini.
	 *
	 * @return bool TRUE si encuentra un enrutamiento valido, FALSE en otro caso.
	 */
	public function run() {

		$this->captureUserAction();

		// Evalua si no hay datos recibidos, en ese caso ejecuta la opción "default"
		if ($this->continue()) {
			$this->runDefault();
		}

		// Evalua coincidencias de los datos recibidos con las rutas mapeadas
		if ($this->continue()
			&& count($this->rutas_publicas) > 0
			&& count($this->request) >= 1
			) {
			// Usa una copia en caso que $this->rutas_publicas sea modificado al ejecutar algún include
			$primero = strtolower($this->request[0]);
			if (isset($this->rutas_publicas[$primero])) {
				$rutas = array_keys($this->rutas_publicas[$primero]);
				sort($rutas); // Asegura se evalue correctamente (primero las acciones sin args)
				foreach ($rutas as $k => $reference) {
					$accion = $this->getPublicRoute($reference, $primero);
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

		return $this->matchSuccessful;
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
	 * @param  string $reference Path de referencia para determinar el enrutamiento.
	 * @param  string $action 	 Script y/o función a ejecutar.
	 * @return bool 		 	 TRUE si encuentra un enrutamiento valido, FALSE en otro caso.
	 */
	public function runOnce(string $reference, string $action = '') {

		// Valida si ya fue ejecutado
		if (!$this->continue()) { return false; }

		$reference_arr = explode('/', strtolower($reference));
		$nueva_reference = '';
		$ultimo_path = '';
		$capturando = false;
		$this->params = array();
		$this->ruta_usada = '';
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

	public function runDefault(string $action = '') {

		if ($this->continue() && !$this->recibido) {
			$action = trim($action);
			if ($action == '') {
				$action = $this->rutas_privadas['default'];
			}
			// Si no se indica, evita ejecutar para no generar mensaje de error y permitir
			// evaluar por fuera cuando no se encuentra coincidencia.
			if ($action != '') {
				return $this->runAction($action, '(default)');
			}
		}

		return false;
	}

	/**
	 * Ejecuta script y/o función relacionada a un enrutamiento valido.
	 * El script se ejecuta en un entorno aislado de la clase actual pero recibe esta clase com parámetro bajo el nombre `$thisRouter`.
	 * Esto para prevenir que se puedan modificar atributos privados de la clase.
	 * El valor de $action puede ser:
	 *
	 * - Nombre de script (absoluto o relativo a $this->path_files).
	 * - Nombre de script + ":" + Nombre de función incluída en el script (recibe como único parámetro esta clase).
	 * - ":" + Función previamente definida (recibe como único parámetro esta clase).
	 *
	 * Puede invocar métodos asociados a una clase definiendo la funcion como: Nombre de clase + "->" + Nombre de método.
	 * En este caso, la instanciación de la clase recibe como único parámetro esta clase ()
	 *
	 * @param string $action Script y/o función a ejecutar.
	 * @param string $reference Nombre o path que identifica el enrutamiento usado. Se muestra en pantalla si `$this->debug` = true).
	 * @return bool TRUE si encuentra un enrutamiento valido, FALSE en otro caso.
	 */
	public function runAction(string $action, string $reference) {

		// Exporta parametros al request
		if ($this->autoExport) {
			$this->exportParamsInto($_REQUEST);
		}

		$this->ruta_usada = $reference;

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
			// Reporta ejecución exitosa solamente si no debe ejecutar alguna función adicionalmente
			if ($funcion == '') {
				$this->matchSuccessful = true;
				$ejecutado_local = true;
			}
		}

		if ($funcion != '') {

			// Valida si define un método dentro de una clase, debe ser del tipo clase->metodo
			$pos = strpos($funcion, '->');

			if ($pos > 0) {
				$clase = trim(substr($funcion, 0, $pos)); // Nombre de la clase
				$funcion = trim(substr($funcion, $pos + 2)); // Método

				if ($clase == '' || !class_exists($clase)) {
					miframe_error('Clase "$1" no existe para la acción *$2* con referencia *$3*', $clase, $action, $reference);
				}

				// La clase a invocar debe recibir como parámetro esta clase
				$obj = new $clase($this);

				if ($funcion == '' && !method_exists($obj, $funcion)) {
					miframe_error('El método $1() de la clase $2 no existe para la acción *$2* con referencia *$3*', $funcion, $clase, $action, $reference);
				}

				if ($this->debug) {
					// error_log('MIFRAME/ROUTER FUNCION ' . $reference . ' --> ' . $funcion);
					$this->printDebug('CLASS ' . $clase . '->' . $funcion . ': ' . $reference);
				}

				// Invoca método
				$obj->$funcion();

				// Remueve objeto
				unset($obj);
			}
			else {
				// Las funciones se validan al ejecutar pues si está asociada a un archivo, no estará
				// disponible sino hasta el cargue del archivo en cuestión.
				if (!function_exists($funcion)) {
					miframe_error('Función $1() no existe para la acción *$2* con referencia *$3*', $funcion, $action, $reference);
				}

				if ($this->debug) {
					// error_log('MIFRAME/ROUTER FUNCION ' . $reference . ' --> ' . $funcion);
					$this->printDebug('FUNCTION ' . $function . ': ' . $reference);
				}

				// La función usa esta clase como unico argumento
				$funcion($this);
			}

			$this->matchSuccessful = true;
			$ejecutado_local = true;
		}

		// $this->matchSuccessful podria ser TRUE de un enrutamiento anterior pero haber fallado en este,
		// por eso se valida $ejecutado_local
		if ($ejecutado_local && $this->stopScript) {
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

		$accion = $this->rutas_privadas['before-stop'];
		if ($accion != '' && !$this->deteniendo) {
			// La elimina para evitar un ciclo infinito al reinvocar esta función include().
			$this->deteniendo = true;
			$this->runAction($accion, '(before-stop)');
			$this->deteniendo = false;
		}

		exit;
	}

	/**
	 * Asocia script y/o función a ejecutar cuando no se recibe parámetro alguno por web.
	 *
	 * @param string $action Script y/o función a ejecutar.
	 */
	public function addDefaultRoute(string $action) {

		$this->rutas_privadas['default'] = trim($action);
	}

	/**
	 * Asocia script y/o función a ejecutar al invocar `$this->abort()`.
	 *
	 * @param string $action Script y/o función a ejecutar.
	 */
	public function addAbortRoute(string $action) {

		$this->rutas_privadas['abort'] = trim($action);
	}

	/**
	 * Asocia script y/o función a ejecutar al invocar `$this->stop()`.
	 *
	 * @param string $action Script y/o función a ejecutar.
	 */
	public function addBeforeStopRoute(string $action) {

		$this->rutas_privadas['before-stop'] = trim($action);
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
			$this->abort($title, $message, $footnote, '404 Not Found');
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
	 */
	public function abort(string $title, string $message, string $footnote = '', string $header = '') {

		$ejecutado = false;

		error_log('ROUTER ABORT - ' . strip_tags($title . ': ' . $message));

		$accion = $this->rutas_privadas['abort'];
		if (!$this->abortando
			&& $accion != ''
			) {
			$this->abortando = true; // Previene ciclo infinito si falla include()
			$this->setParam('abort_title', $title);
			$this->setParam('abort_message', $message);
			$this->setParam('abort_footnote', $footnote);
			// Siempre termina la ejecución al ejecutar la acción
			$this->stopScript = true;

			if ($header != '' && !headers_sent()) {
				header("HTTP/1.1 " . $header);
			}

			$ejecutado = $this->runAction($accion, '(abort)');
			$this->abortando = false;
		}

		if (!$ejecutado) {
			// Si no pudo ejecutar lo anterior, presenta mensaje base
			// Mensaje con error a pantalla
			$message = nl2br($message);
			$this->printDebug(miframe_text('Ejecución cancelada'));
			echo miframe_box($title, $message, '', $footnote);
		}

		// Si llega a este punto y no esta en otro proceso de abortar, termina el script.
		if (!$this->abortando) { $this->stop(); }
	}

	/**
	 * Procedimiento adicional a ejecutar cuando se invoca `$this->detour()`.
	 *
	 * @param callable $function Función a ejecutar.
	 */
	// public function detourCall(callable $function) {
	// 	$this->detour_handler = $function;
	// }

	public function fileDetected() {
		return $this->file_detected;
	}

	public function reportFileDetected(string $title, string $message, string $footnote = '') {

		if ($this->file_detected != '' && !$this->matchSuccessful) {
			$this->abort($title, $message, $footnote, '403 Forbidden');
		}
	}

	public function exportFileDetected(bool $export_direct = false) {

		if ($this->file_detected != '' && !$this->matchSuccessful) {
			$this->exportFile($this->file_detected, $export_direct);
		}

		return false;
	}

	/**
	 * Ejecuta script/archivo que no está asociados a alguno de los enrutamientos declarados.
	 * Esto usualmente permite al sistema intentar recuperar scripts recibidos por enrutamientos erróneos realizados
	 * por el servidor web y detectados al evaluar el REQUEST_URI.
	 * También puede usarse para ejecutar scripts en un entorno aislado al actual.
	 *
	 * @param string $filename Script a ejecutar.
	 * @param bool $export_direct TRUE envía archivo directo al browser, FALSE envía headers para guardar archivo.
	 */
	public function exportFile(string $filename, bool $export_direct = false) {

		if ($filename != '' && is_file($filename)) {

			// En teoría, todo archivo script debería ser invocado por el WebServer y no pasado a este script
			if (strtolower(substr($filename, -4)) == '.php' && $export_direct) {
				// Cambia al directorio del archivo
				chdir(dirname($filename));
				// Ejecuta en modo privado
				$this->include(
					basename($filename),
					miframe_text('READFILE: Script ejecutado localmente'),
					miframe_text('Archivo $1', $filename),
					true
					);
			}
			else {
				// Envia archivo directamente a pantalla o para guardar
				// header('Content-Description: File Transfer');
				$mimetype = mime_content_type($filename);
				$size = filesize($filename);

				if ($mimetype != '') {
					header('Content-Type: ' . $mimetype);
				}

				// $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
				// if (!in_array($extension, [ 'jpg', 'jpeg', 'gif', 'png', 'svg', 'mp4' ])) { ... }
				if (!$export_direct) {
					header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
				}

				header('Expires: 0');
				header('Cache-Control: must-revalidate');
				header('Pragma: public');
				header('Content-Length: ' . $size);

				readfile($filename);
			}

			exit;
		}

		return false;
	}

	/**
	 * Exporta los parámetros declarados.
	 *
	 * @param array $dest Arreglo a recibir los parámetros declarados en $this->params.
	 */
	public function exportParamsInto(array &$dest) {

		$dest = $this->params + $dest;
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
		$name = strtolower(trim($name));
		if ($name !== '' && array_key_exists($name, $this->params)) {
			$valor = $this->params[$name];
		}
		if ($valor === '') { $valor = $default; }

		return $valor;
	}

	/**
	 * Almacena valor asociado a $name.
	 * No se permite valor vacio para $name y este siempre será convertido a minúsculas.
	 * @param string $name Nombre asociado al parámetro.
	 * @param mixed $value Valor del parámetro (siempre será tratado como texto).
	 */
	public function setParam(string $name, string $value) {

		$name = trim(strtolower($name));
		if ($name != '') {
			$this->params[$name] = trim($value);
		}
	}

	/**
	 * Soporte para definición de enlaces y/o formularios.
	 * Si el método de detección es "POST" (ej. datos recibidos de un formulario), retorna un arreglo con los
	 * valores a usar para crear un nuevo formulario.
	 * En otro caso, retorna el enlace ya listo para su uso.
	 *
	 * @param string $request_param  Valor del parámetro principal. Puede contener parámetros adicionales
	 *                               conectados por "?" y con formato "[nombre1]=[valor1]&[nombre2]=[valor2]...".
	 *                               Complementa el listado de parámetros recibido en el argumento $params (si alguno).
	 * @param bool   $force_get_mode Obliga el uso del método GET al momento de crear el enlace.
	 * @param mixed  $params		 Parámetros adicionales a incluir en el enlace.
	 * @return mixed                 Enlace a usar o arreglo de datos (método de detección "post")
	 */
	public function getFormAction(string $request_param = null, bool $force_get_mode = false, mixed $params = false) {

		$accion = $this->documentRoot();
		if (is_null($request_param)) {
			$request_param = $this->request();
		}
		if (is_array($params)) {
			$params = http_build_query($params);
		}
		if ($request_param != '') {
			// Valida si contiene "?"
			$pos = strpos($request_param, '?');
			if ($pos !== false) {
				if ($params != '') { $params = '&' . $params; }
				$params = substr($request_param, $pos + 1) . $params;
				$request_param = substr($request_param, 0, $pos);
			}
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

		$filedata = $this->setDataBeforeReload($data);
		if ($filedata !== '') {
			$params['micodedata'] = $filedata;
		}

		$location = $this->getFormAction($request_param, true, $params);

		/*
		$accion = $this->rutas_privadas['reload'];
		if ($accion != '') {
			$this->setParam('xxxx', $location);
			$this->openAction($accion);
		}

		*/

		$mensaje = '';

		if (!$this->jsonRequest()) {
			$mensaje = "<script>window.location='{$location}';</script>" .
				miframe_text('Se está redireccionando a una nueva página.') .
				"<a href=\"{$location}\">" . miframe_text('En caso que no se cargue la nueva página, puede hacerlo manualmente haciendo click aquí.') . "</a>.";
			if (!headers_sent()) {
				// header("HTTP/1.1 301 Moved Permanently"); <-- No debe marcarla como permanente!
				header("Location: {$location}");
			}
		}
		else {
			// En consultas por JSON no son permitidos los reload automáticos
			$mensaje = miframe_text("Ejecución interrumpida: Se está intentando redireccionar a una nueva página") .
				":\n" . $location;
		}

		exit($mensaje);
	}

	public function setDataBeforeReload(mixed $data = false) {

		$filedata = '';

		if ($data !== false) {
			$dirname = miframe_temp_dir('micode-cache-reloads', true);
			$basename = uniqid();
			$m = intval(rand(0, 9));
			$filename = miframe_path($dirname , $basename . dechex($m));
			while (file_exists($filename)) {
				$m ++;
				$filename = miframe_path($dirname , $basename . dechex($m));
			}

			miframe_serialize($filename, $data);

			$filedata = basename($filename);
		}

		return $filedata;
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

	public function setDirTemp($path) {

		if (is_dir($path)) {
			$this->dir_temp = $path;
		}
	}

	public function getDirTemp() {

		if ($this->dir_temp == '') {
			$this->dir_temp = miframe_temp_dir();
		}

		return $this->dir_temp;
	}

	/**
	 * Asigna valor al URL base.
	 */
	private function setURIbase() {

		$this->uri_base = '/';
		// Nombre de quien se invoca como el script base (puede ser diferente al SCRIPT_FILENAME).
		// Ejemplo:
		// SCRIPT_NAME = /micode-manager/index.php
		// SCRIPT_FILENAME = C:\xxx\micode-manager\index.php
		$script_name = strtolower(miframe_server_get('SCRIPT_NAME'));
		$dirbase = dirname($script_name);
		// Si no hay subdirectorios (ej. '/index.php'), dirbase() retorna "\". Lo ignora en ese caso.
		if ($dirbase !== DIRECTORY_SEPARATOR) { $this->uri_base = $dirbase . '/'; }
		// Asegura formato
		if (substr($this->uri_base, 0, 1) != '/') { $this->uri_base = '/' . $this->uri_base; }
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
	 * https://stackoverflow.com/questions/7431313/php-getting-full-server-name-including-port-number-and-protocol
	 */
	function getServerURL(string $path = '')	{

		$protocol = 'http';
		if (!empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == '1')) {
			$protocol = 'https';
		}
		$server = $_SERVER['SERVER_NAME'];
		$port = ':' . miframe_server_get('SERVER_PORT', 80);
		// Adiciona puerto si no es 80 (http) ni 443 (https)
		if (in_array($port, [ ':80', ':443' ])) {
			$port = '';
		}

		if ($path != '') {
			$path = $this->createURL($path);
		}

		return $protocol . '://' . $server . $port . $path;
	}

	public function jsonRequest() {

		$retornar = false;

		if ($this->force_json) {
			// Manualmente fijado
			return true;
		}
		elseif (
			// Ajax request
			// https://stackoverflow.com/questions/19794859/detect-ajax-in-php
			(miframe_server_get('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest') ||
			// Espera una respuesta en JSON
			(strpos(strtolower(miframe_server_get('HTTP_ACCEPT')), 'application/json') !== false)
			) {
			$retornar = true;
		}
		else {
			$headers = apache_request_headers();
			// Usa apache_request_headers() para recuperar headers no asociados al $_SERVER (ocurre en algunos servidores Web)
			$retornar = (isset($headers['Accept']) && strpos(strtolower($headers['Accept']), 'application/json') !== false);
			// TRUE Espera una respuesta en JSON
		}

		// Actualiza $this->force_json con el valor obtenido para agilizar futuras invocaciones
		if ($retornar) {
			$this->forceJSON(true);
		}

		return $retornar;
	}

	public function forceJSON(bool $json) {
		$this->force_json = $json;
		miframe_set_noweb($json);
	}

	/**
	 * Muestra en pantalla información de la clase actual.
	 */
	public function showInfo() {

		echo miframe_box('INFO ROUTER',
				'<ul>' .
				'<li><b>URI base:</b> ' .			$this->getURIbase() . '</li>' . PHP_EOL .
				'<li><b>Path base:</b> ' .			$this->getDirbase() . '</li>' . PHP_EOL .
				'<li><b>Document Root:</b> ' .		$this->documentRoot() . '</li>' . PHP_EOL .
				'<li><b>Script:</b> ' .				$this->scriptFilename() . '</li>' . PHP_EOL .
				'<li><b>JSON:</b> ' .				($this->jsonRequest() ? 'true' : 'false') . '</li>' . PHP_EOL .
				'<li><b>Parámetros:</b> ' . 		$this->request() . '</li>' . PHP_EOL .
				// Este print_r() puede hacer que la salida a pantalla se altere. Porqué?
				// Cuando el parámetro return se usa, esta función utiliza el almacenamiento en búfer de salida interno,
				// por lo que no puede usarse dentro de una función de llamada de retorno ob_start().
				// https://www.php.net/manual/es/function.print-r.php
				'<li><b>Rutas:</b><pre>' .			print_r($this->getLoadedRoutes(), true) . '</pre></li>' .
				'</ul>'
				);
	}
}
