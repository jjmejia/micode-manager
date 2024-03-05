<?php

// CUANDO NO SEA URI, _router CONTIENE EL ALIAS, ASI FACILITA PROCESO DE BUSQUEDA. CUANDO ES URI, PASA EL ENLACE

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
 * @micode-uses miframe-common-functions
 * @micode-uses miframe-file-serialize
 * @author John Mejia
 * @since Abril 2022
 */

namespace miFrame\Interface;

/**
 * Clase usada para realizar el enrutamiento dinámico.
 * Las siguientes propiedades públicas pueden ser usadas:
 * - $autoExport: boolean. TRUE carga los argumentos encontrados al validar el enrutamiento en la variable global $_REQUEST
 *   (no se modifica $_POST ni $_GET).
 */
class Router extends \miFrame\Interface\Shared\BaseClass {

	private $request_param = '';
	private $request = array();
	private $recibido = false;
	private $rutas_privadas = array();
	private $rutas_publicas = array();
	private $matchSuccessful = false;		// TRUE si encuentra al menos un match valido
	private $use_request_uri = false;
	private $ruta_usada = '';
	private $dir_temp = '';
	private $uri_base = '';
	private $params = array();
	private $script_filename = '';
	private $dir_base = '';
	private $request_uri = '?';
	private $document_root = '';
	private $force_json = false;
	private $file_detected = '';
	private $history = array();
	private $rutas_alias = array();
	private $form_post_params = array();

	public $autoExport = false;

	public function __construct() {

		$this->setURIbase();
		$this->initialize();
		$this->clearRoutes();
		$this->useRequestURI();

		// Definiciones
		$this->script_filename = realpath(miframe_server_get('SCRIPT_FILENAME'));
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
			// Solamente retorna "?" si PATH_INFO no está definido en $_SERVER.
			$this->request_uri = strtolower(miframe_server_get('PATH_INFO', '?'));

			if ($this->request_uri === '?') {
				// Intenta recuperar manualmente.
				// Recupera URI sin argumentos (ignora todo despues de "?")
				$this->request_uri = $this->removeURIbase(
						parse_url(
							strtolower(miframe_server_get('REQUEST_URI')),
							PHP_URL_PATH
						));
			}
		}

		return $this->request_uri;
	}

	private function validateRequest(string &$request_action) {

		// Archivo fisico detectado (si alguno)
		$this->file_detected = '';

		if ($request_action != '') {
			// Complementa el path recibido para ubicar el archivo físico
			$filename = $this->createPath($request_action);
			// Valida si se ha recibido un path de un archivo valido y es el script actual.
			if ($filename == $this->scriptFilename()) {
				$request_action = '';
			}
			elseif (file_exists($filename) && is_file($filename)) {
				// Nota: Los directorios reportan TRUE al usar file_exists()
				// Se recibe como path uno que apunta a un archivo fisico del servidor.
				// Esto no debería ocurrir ya que el servidor web debería proveerlo.
				// Puede pasar debido a una configuración fallida en el servidor.
				$this->file_detected = $filename;
			}
		}

		// No incluye primer "/" (a menos que solo contenga ese caracter)
		if ($request_action != '' && $request_action != '/' && substr($request_action, 0, 1) === '/') {
			$request_action = substr($request_action, 1);
		}
	}

	/**
	 * Retorna arreglo de rutas usado.
	 *
	 * @return array Arreglo de rutas.
	 */
	public function selectedRoute() {
		return $this->ruta_usada;
	}

	public function requestParam() {
		return $this->request_param;
	}

	// ********************************
	// URI no discrimina GET/POST pero debería porque para las API pueden estar las dos...
	// Revisar
	// ********************************

	/**
	 * Captura valor de la referencia asociada al enrutamiento.
	 * Sugerencia: Esta función debe ejecutarse después de $this->setURIbase(), especialmente cuando $this->use_request_uri = true.
	 *
	 * @param  string $name_post   Nombre del parámetro REQUEST asociado. Cuando se usa método "uri", el $name es usado para guardar
	 *                             el valor capturado bajo ese nombre. Si no se designa valor y usa un método de captura
	 *  						   diferente al "uri", asigna "_route".
	 * @param  string $method_bind Restricción al origen del dato: "request" (POST o GET) o "uri". Si no se indica
	 *                             valor alguno, usa por defecto "request".
	 * @return bool                TRUE si fue posible capturar el valor de referencia.
	 */
	public function useRequestURI(bool $use_request_uri = true, string $name_post = '') {

		$this->request_param = trim($name_post);

		if ($this->request_param == '') { $this->request_param = '_route'; }

		$this->use_request_uri = $use_request_uri;
	}

	private function captureUserAction() {

		$this->request = array();
		$this->recibido = false;
		$valor = '';

		if ($this->use_request_uri) {
			$valor = $this->requestURI();
		}
		else {
			// $tipos = array('post' => '_POST', 'get' => '_GET', 'request' => '_REQUEST');
			// if (!isset($tipos[$this->method_bind])) {
			// 	// Error de configuración
			// 	miframe_error('Método de selección no reconocido: $1. Esperaba "$2" o "uri".', $this->method_bind, implode('", "', array_keys($tipos)));
			// }
			// $collector = $tipos[$this->method_bind];
			$collector = '_REQUEST';
			if (isset($GLOBALS[$collector])
				&& is_array($GLOBALS[$collector])
				&& isset($GLOBALS[$collector][$this->request_param])
				&& is_string($GLOBALS[$collector][$this->request_param])
				) {
				$valor = trim($GLOBALS[$collector][$this->request_param]);
			}
		}

		$this->validateRequest($valor);

		if ($valor != '') {
			$this->request = explode('/', strtolower($valor));
			$this->matchSuccessful = false;
			$this->recibido = true;
		}

		// Almacena valor para referencias externas
		$this->setParam($this->request_param, $this->request());

		return $this->recibido;
	}

	public function clearRoutes() {

		$this->rutas_privadas = array(
			'index' => '',
			'abort' => '',
			'before-stop' => ''
			);

		$this->rutas_publicas = array();
		$this->rutas_alias = array();
	}


	public function addErrorRoute(int $code, string $action) {

		if ($code > 0) {
			$this->rutas_privadas['error-' . $code] = trim($action);
		}
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
	protected function addRoute(string $reference, string $action, string $method = '', string $alias = '') {

		$reference = strtolower(trim($reference));
		$action = trim($action);

		// Elimina cualquier "/" en el primer campo
		while ($action !== '' && substr($action, 0, 1) == '/') {
			$action = trim(substr($action, 1));
		}

		// Determina método asociado
		$method = strtolower(trim($method));
		if ($method == '') { $method = '@any'; }
		if ($reference != '' && $action != '') {
			$pos = strpos($reference, '?');
			$llave = $reference;
			if ($pos !== false) {
				$llave = substr($reference, 0, $pos);
				if (substr($llave, -1, 1) == '/') { $llave = substr($llave, 0, -1); }
			}
			// Valida el nombre
			if ($alias == '') {
				$alias = miframe_only_alphanum(str_replace('?', '', $llave), '-', '_');
			}
			if ($alias == 'index') {
				miframe_error('Error al configurar rutas: El alias "index" no puede asignarse a rutas públicas');
			}
			elseif (isset($this->rutas_alias[$alias])) {
				// El nombre dado ya existe, deberá fijar uno manualmente
				miframe_error(
					'Error al configurar rutas: El alias "$1" asignado a la referencia "$2" ya fue asignado a "$3"',
					$alias,
					$reference,
					$this->rutas_alias[$alias]['reference']
					);
			}
			// Incluye método de consulta. Separa el primer elemento
			// $arreglo = explode('/', $reference);
			// $primero = strtolower($arreglo[0]);
			// $resto = '';
			// if (isset($arreglo[1])) { $resto = strtolower($arreglo[1]); }
			$this->rutas_publicas[$method][$llave][] = $alias;
			$this->rutas_alias[$alias] = array(
				'action' => $action,
				// 'description' => trim($description),
				// 'alias' => $alias,
				'reference' => $reference
				);

			/*
			$this->rutas_publicas[$primero][$resto][$method] = new miAttributes([
				'action' => $action,
				'description' => trim($description)
				]);
			*/
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
	 * Esto es, cuando no ha encontrado un enrutamiento valido ($this->matchSuccessful = true).
	 *
	 * @return bool TRUE si puede proceder a validar. FALSE en otro caso.
	 */

	public function continue() {

		return ($this->file_detected == '' && !$this->matchSuccessful);
	}

	private function getPublicRoutes() {

		$rutas = array();
		$metodo = strtolower($_SERVER['REQUEST_METHOD']);
		// $reference .= '/';
		$acum = '';
		$metodo_any = '@any';

		// echo "<pre>"; print_r($this->rutas_publicas); echo "</pre><hr>";

		foreach ($this->request as $k => $path) {
			if ($acum != '') { $acum .= '/'; }
			$acum .= $path;
			$aliases = array();
			if (isset($this->rutas_publicas[$metodo][$acum])) {
				$aliases = $this->rutas_publicas[$metodo][$acum];
			}
			elseif (isset($this->rutas_publicas[$metodo_any][$acum])) {
				$aliases = $this->rutas_publicas[$metodo_any][$acum];
			}
			if (count($aliases) > 0) {
				sort($aliases);
				$rutas = array_merge($rutas, $aliases);
			}
		}

		// print_r($rutas); echo "<hr>";

		/*
		if ($startsWith != '') {
			$metodo = strtolower($_SERVER['REQUEST_METHOD']);
			$metodo_any = '@any';
			if (isset($this->rutas_publicas[$startsWith][$reference][$metodo])) {
				$accion = $this->rutas_publicas[$startsWith][$reference][$metodo];
			}
			elseif (isset($this->rutas_publicas[$startsWith][$reference][$metodo_any])) {
				$accion = $this->rutas_publicas[$startsWith][$reference][$metodo_any];
			}
		}
		*/

		return $rutas;
	}

	/**
	 * Evalúa y ejecuta enrutamientos declarados en archivo .ini.
	 *
	 * @return bool TRUE si encuentra un enrutamiento valido, FALSE en otro caso.
	 */
	public function run() {

		$this->captureUserAction();

		// Evalua si no hay datos recibidos, en ese caso ejecuta la opción "index"
		if ($this->continue()) {
			$this->runIndex();
		}

		// Evalua coincidencias de los datos recibidos con las rutas mapeadas
		if ($this->continue()
			&& count($this->rutas_publicas) > 0
			&& count($this->request) > 0
			) {
			// $primero = strtolower($this->request[0]);
			if (!$this->use_request_uri) {
				// Recibe el alias como referencia
				return $this->runAlias($this->request[0]);
			}
			else {
				// Usa una copia en caso que $this->rutas_publicas sea modificado al ejecutar algún include
				// if (isset($this->rutas_publicas[$primero])) {
					// PENDIENTE: Qué pasa si $primero es una variable??
					$rutas = $this->getPublicRoutes();
					// print_r($rutas); echo "<hr>";
					// sort($rutas); // Asegura se evalue correctamente (primero las acciones sin args)
					foreach ($rutas as $k => $alias) {
						// $alias = $this->getPublicRoute($reference, $primero);
						$this->runAlias($alias);
					}
				// }
			}
		}

		// Si detectó algún archivo, pero no corresponde a los mapas de navegación lo intenta exportar
		// usando $app->router->exportFileDetected() o lo reporta usando $app->router->reportFileDetected().
		$this->exportFileDetected(true);

		return $this->matchSuccessful;
	}

	private function runAlias($alias) {

		if ($alias !== '' && isset($this->rutas_alias[$alias])) {
			$accion = $this->rutas_alias[$alias]['action'];
			$full_reference = $this->rutas_alias[$alias]['reference'];
			// echo "PRIMERO $alias / $full_reference = $accion<hr>";
			if ($accion != '') {
				// if ($reference != '') { $reference = '/' . $reference; }
				// $full_reference = $primero . $reference;
				if ($this->runOnce($full_reference)) {
					$this->ruta_usada = $alias;
					// print_r($data_accion);
					return $this->runAction($accion, $full_reference);
				}
			}
		}

		return false;
	}

	/**
	 * Evalúa enrutamiento.
	 *
	 * El path de referencia para el enrutamiento debe declararse siguiendo uno de los siguientes formatos:
	 *
	 * - Path absoluto. Ej: "path1/path2". En este caso si recibe "path1/path2/valor1/valor2", no lo tomará como una
	 *   coincidencia valida.
	 * - Path relativo con parámetros variables, se indican con "?". Ej: "path1/path2/?arg1/?arg2". El valor para "arg1" y "arg2"
	 *   son registrados en el arreglo $this->params. Este enrutamiento se ejecutará tanto si se invoca "path1/path2/valor1/valor2", como si
	 *   se invoca "path1/path2" ("arg1" y "arg2" se definen con valor en blanco). Si por el contrario, recibe "path1/path2/valor1/valor2/valor3"
	 *   se retornará como fallido ya que tiene más argumentos que los esperados.
	 *
	 * En caso de declarar `$this->autoExport` = true, cargará los valores de $this->params en $_REQUEST (no modifica $_POST ni $_GET).
	 * Si la accion asociada es vacio, busca archivo usando como patron el valor de $reference (adicionando extensión ".php").
	 *
	 * @param  string $reference Path de referencia para determinar el enrutamiento.
	 * @param  string $action 	 Script y/o función a ejecutar.
	 * @return bool 		 	 TRUE si encuentra un enrutamiento valido, FALSE en otro caso.
	 */
	private function runOnce(string $reference) {

		$reference = trim($reference);
		// Valida si ya fue ejecutado
		if (!$this->continue() || $reference == '') { return false; }

		// Inicializa arreglo con el valor recibido previamente
		$this->setParam($this->request_param, $this->request());

		// Cuando no es URI el método, simula respuesta para recuperar posibles variables
		if  (!$this->use_request_uri && strpos($reference, '?') === false) {
			// No hay variables que buscar
			return true;
		}

		$reference_arr = explode('/', strtolower($reference));
		// $nueva_reference = '';
		// $ultimo_path = '';
		// $capturando = false;

		$this->params = array();
		// $this->ruta_usada = '';

		$count_request = count($this->request);
		$ultima_posicion = count($reference_arr) - 1;

		foreach ($reference_arr as $k => $path) {

			$path = trim($path);

			if (substr($path, 0, 1) == '?') {
				// El contenido pasa a una variable.
				// Si es el último, captura los campos restantes (al salir del ciclo)
				// usando $ultimo_var como referencia de control.
				$path = substr($path, 1);

				// Solamente permite letras, numeros y "_". Cualquier otro caracter se remplaza por "_"
				$npath = miframe_only_alphanum($path, '_', '_');

				if ($path === '' || str_replace('_', '', $npath) === '') {
					// No existe nombre valido para la variable a usar (sea vacio o algo como "___")
					miframe_error('Error al validar atributos de ruta para la referencia $1 (item $2)', $reference, ($k + 1));
				}
				elseif (strtolower($path) == strtolower($this->request_param)) {
					// No existe nombre valido para la variable a usar (sea vacio o algo como "___")
					miframe_error('Error al validar atributos de ruta: uso de nombre no permitido ($1)', $path);
				}

				// Si contiene "*" al final, indica que debe capturar todo lo que
				// encuentre de este punto en adelante (si alguno)
				// $capturar_resto = ($k == $ultima_posicion && substr($path, -1, 1) == '*');

				$valor = '';
				if (!$this->use_request_uri) {
					if (array_key_exists($path, $_REQUEST)) {
						$valor = $_REQUEST[$path];
					}
				}
				elseif (isset($this->request[$k])) {
					$valor = trim($this->request[$k]);
				}

				if ($k == $ultima_posicion && $count_request > $k + 1) {
					// Adiciona cualquier resto que queda
					// $path = substr($path, 0, -1);
					// if ($count_request > $k + 1) {
						for ($k = $k + 1; $k < $count_request; $k ++) {
							$valor .= '/' . trim($this->request[$k]);
						}
					// }
				}

				/*elseif ($path != '' && isset($_REQUEST[$path]) && !is_array($_REQUEST[$path])) {
					// Captura los datos de entre los recibidos via POST/GET
					$valor = trim($_REQUEST[$path]);
				}*/

				$this->setParam($npath, $valor);
			}
			elseif ($this->use_request_uri) {
				// Debe ser un valor estático
				if ($path === '' || !isset($this->request[$k]) || $this->request[$k] !== $path) {
					return false;
				}
				// if ($nueva_reference != '') { $nueva_reference .= '/'; }
				// $nueva_reference .= $path;
			}

			/*
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
			*/
		}

		if ($count_request > $k + 1) {
			/* if ($capturando) {
				// El último elemento fue una variable de captura, adiciona el resto
				// del path a esa variable (ej: un path de archivo)
				if ($ultimo_path != '') {
					for ($k = $k + 1; $k < $count_request; $k ++) {
						$this->params[$ultimo_path] .= '/' . trim($this->request[$k]);
					}
				}
			}
			else {*/
				// Hay mas datos en lo recibido que en el patron de busqueda y el patron no tiene
				// para capturar valores, luego lo da como fallido.
				return false;
			// }
		}

		// Si no definió acción, usa el mismo valor de referencia
		// if ($action == '') {
		// 	$action = $nueva_reference . '.php';
		// }

		// Actualiza valor del parametro a buscar (no modifica $this->request)
		// $this->setParam($this->request_param, $reference);

		return true;
	}

	public function runIndex(string $action = '') {

		if ($this->continue() && !$this->recibido) {
			$action = trim($action);
			if ($action == '') {
				$action = $this->rutas_privadas['index'];
			}
			// Si no se indica, evita ejecutar para no generar mensaje de error y permitir
			// evaluar por fuera cuando no se encuentra coincidencia.
			return $this->runAction($action, '(index)');
		}

		return false;
	}

	private function existsHistory(string $reference) {

		$llave_reference = md5($reference);
		return isset($this->history[$llave_reference]);
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

		$filename = trim($action);
		$reference = trim($reference);
		$llave_reference = md5($reference);
		// Nada que procesar
		// Valida history para prevenir ciclos infinitos en caso que se intente invocar nuevamente este método
		// (stop() y abort() invocan este método)
		if ($filename === '' || $reference == '' || isset($this->history[$llave_reference])) {
			return false;
		}

		// $this->ruta_usada = $reference;

		$funcion = '';
		$path = '';

		// Busca referencia a funciones.
		// "[archivo]::[función]" ó simplemente "::[función]"
		$pos = strrpos($filename, '::');
		// Ignora casos como "C:/xxxx" que corresponde a un nombre de archivo
		// o "file://xxxx" o "https://xxxx" que serían referencias posiblemente válidas también.
		if ($pos !== false && ($pos === 0 || $pos > 1)) {
			$filename = trim(substr($action, 0, $pos));
			$funcion = trim(substr($action, $pos + 2));
		}

		if ($filename != '') {
			$path = $filename;
			if (!file_exists($path) && strpos($filename, ':') === false && $this->path_files != '') {
				$path = miframe_path($this->path_files, $filename);
			}
			if (!file_exists($path)) {
				miframe_error('Archivo no encontrado para la referencia *$1*', $reference, debug: $path);
			}
		}
		elseif ($funcion == '') {
			// No define filename ni función y requiere alguna de las dos.
			miframe_error('Acción "$1" no valida para la referencia *$2*', $action, $reference);
		}

		$ejecutado_local = false;

		// Usado para prevenir que repita el mismo llamado varias veces DENTRO del mismo ciclo.
		// Se libera al terminar la ejecución
		$this->history[$llave_reference] = $filename;

		// Exporta parametros al request
		if ($this->autoExport) {
			$this->exportParamsInto($_REQUEST);
		}
		// Ejecuta include asegurando que esté aislado para no acceder a elementos privados de esta clase
		$this->printDebug("INCLUDE {$reference} --> {$filename}");
		if (miframe_include_file($path)) {
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
					$this->printDebug('FUNCTION ' . $funcion . ': ' . $reference);
				}

				// La función usa esta clase como unico argumento
				$funcion($this);
			}

			$this->matchSuccessful = true;
			$ejecutado_local = true;
		}

		// $this->matchSuccessful puede ser TRUE de una ejecución previa pero no de esta (por ejemplo durante la ejecución
		// de un "before-stop")
		if ($ejecutado_local) {
			// Valida si debe ejecutar algo antes (termina siempre porque la referencia coincidió, diferente si no coincide)
			$this->stop();
		}

		// Libera historial
		unset($this->history[$llave_reference]);

		return $this->matchSuccessful;
	}

	/**
	 * Detiene la ejecución del script principal.
	 * Valida si se ha definido alguna acción a realizar antes de ejecutar la detención.
	 */
	public function stop() {

		if (!$this->existsHistory('(before-stop)')) {
			$this->runAction($this->rutas_privadas['before-stop'], '(before-stop)');
			exit;
		}
	}

	/**
	 * Asocia script y/o función a ejecutar cuando no se recibe parámetro alguno por web.
	 *
	 * @param string $action Script y/o función a ejecutar.
	 */
	public function addIndexRoute(string $action) {

		$this->rutas_privadas['index'] = trim($action);
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
	public function notFound(string $title = '', string $message = '', string $footnote = '') {

		if (!$this->matchSuccessful) {
			// Si no recibe título y mensaje, asigna automáticamente
			if ($title == '' && $message == '') {
				$title = miframe_text('Página no encontrada');
				$message = miframe_text('La referencia **$1** no está asociada con una página valida.', $this->request());
			}
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

		// Declara mensajes como elementos en params
		$this->setParam('abort_title', $title);
		$this->setParam('abort_message', $message);
		$this->setParam('abort_footnote', $footnote);

		if ($header != '' && !headers_sent()) {
			header("HTTP/1.1 " . $header);
		}

		// Busca en errores
		$error_code = intval($header);
		if ($error_code > 0 && isset($this->rutas_privadas['error-' . $error_code])) {
			$ejecutado = $this->runAction($this->rutas_privadas['error-' . $error_code], '(error ' . $error_code . ')');
		}
		if (!$ejecutado) {
			$ejecutado = $this->runAction($this->rutas_privadas['abort'], '(abort)');
		}
		if (!$ejecutado) {
			// Si no pudo ejecutar lo anterior, presenta mensaje base
			// Mensaje con error a pantalla
			$this->printDebug('ROUTER ABORT');
			echo miframe_box($title, nl2br($message), 'critical', $footnote);
			// Termina el script
			$this->stop();
		}
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

	public function exportFileDetected() {

		if ($this->file_detected != '' && !$this->matchSuccessful) {
			$this->exportFile($this->file_detected, true);
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
				$this->printDebug(miframe_text('READFILE: Script ejecutado localmente'),
					miframe_text('Archivo $1', $filename)
					);
				// Cambia al directorio del archivo para que los enrutamiento en el archivo funcionen correctamente
				chdir(dirname($filename));
				miframe_include_file(basename($filename));
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
	 * @param string $request_param  Valor del alias de ruta o URL. Si no se indica, usa el actual. Cadena vacia para "index".
	 * @param bool   $force_get_mode Obliga el uso del método GET al momento de crear el enlace. FALSE usa el método prefijado.
	 * @param mixed  $params		 Parámetros adicionales a incluir en el enlace. Puede ser un arreglo o una cadena del tipo "a=xx&b=xx..".
	 * @return mixed                 Enlace a usar o arreglo de datos (método de detección "post")
	 */
	public function createRouteURL(string $request_param = null, mixed $params = '', bool $force_get_mode = true) {

		$accion = $this->documentRoot();
		$this->form_post_params = array();

		if (!is_array($params)) {
			// Asume es una cadena del tipo "a=xx&b=xx.."
			$pre = $params;
			$params = array();
			if (is_string($pre) && $pre != '') {
				parse_str($pre, $params);
			}
		}

		if (is_null($request_param)) {
			// $request_param = $this->request();
			$request_param = $this->ruta_usada;
		}

		if ($request_param != '') {
			// Remplaza alias por la ruta correcta
			if ($request_param == 'index') {
				$request_param = ''; // $this->rutas_privadas['index'];
			}
			elseif (isset($this->rutas_alias[$request_param])) {
				// Si no está definida, usa la ruta tal cual fue recibida
				// miframe_error('Ruta no especificada/alias no encontrado ($1)', $request_param);
				$alias = $request_param;
				if ($this->use_request_uri) {
					$request_param = $this->rutas_alias[$alias]['reference'];
					if (strpos($request_param, '?') !== false && count($params) > 0) {
						// Remplaza los params
						$remplazar = array();
						foreach ($params as $k => $v) {
							if (strpos($request_param, '?' . $k) !== false) {
								// No remplaza directamente para prevenir que un valor de
								// remplazo introduzca nuevas variables
								$remplazar['?' . $k] = $v;
								unset($params[$k]);
							}
						}
						if (count($remplazar) > 0) {
							$request_param = str_replace(array_keys($remplazar), $remplazar, $request_param);
						}
						if (strpos($request_param, '?') !== false) {
							// No encontró las variables de remplazo? Debería dejarlas en blanco?
							miframe_error('Error al generar URL, no pudo generar correctamente la URL ($1) para "$2"', $request_param, $alias);
						}
						// Remueve ultimo "/"
						if (substr($request_param, -1, 1) == '/') { $request_param = substr($request_param, 0, -1); }
					}
				}
			}

			// Valida si contiene "?"
			// $pos = strpos($request_param, '?');
			// if ($pos !== false) {
			// 	if ($params != '') { $params = '&' . $params; }
			// 	$params = substr($request_param, $pos + 1) . $params;
			// 	$request_param = substr($request_param, 0, $pos);
			// }
		}

		if ($this->use_request_uri) {
			$accion = $this->createURL($request_param);
			// Valida si adiciona el resto de parámetros o los registra para uso posterior (formulario POST)
			if (count($params) > 0) {
				if ($force_get_mode) {
					$params = http_build_query($params);
					if ($params != '') {
						$accion .= '?' . $params;
					}
				}
				else {
					// Guarda para uso posterior
					$this->form_post_params = $params;
				}
			}
		}
		else {
			// if (!is_array($params)) { $params = array(); }
			$params[$this->request_param] = $request_param;
			if ($force_get_mode) {
				// Remueve enlace por visibilidad
				if ($params[$this->request_param] == '') { unset($params[$this->request_param]); }
				$params = http_build_query($params);
				if ($params != '') {
					$accion .= '?' . $params;
				}
			}
			else {
				// Si forza el modo POST, la acción debe configurarse manualmente en el formulario.
				// PENDIENTE: PARA EL METODO POST ES NECESARIO UN METODO QUE RETORNE LOS PARAMETROS PROPIOS REQUERIDOS, INCLUIDO EL CSRF
				// $accion = array(
				// 	'action' => $accion,
				// 	'params' => $params
				// );

				// Guarda para uso posterior
				$this->form_post_params = $params;
			}
		}

		return $accion;
	}

	public function getFormPostParams(bool $csrf = false) {

		$params = $this->form_post_params;
		// Adiciona otros elementos sugeridos
		if ($csrf) {
			// PENDIENTE...
		}

		return $params;
	}

	public function reload(string $request_param = null, mixed $params = '', array $data = null) {

		if (is_array($data) && count($data) > 0) {
			$filedata = $this->setDataBeforeReload($data);
			if ($filedata !== '') {
				$params['micodedata'] = $filedata;
			}
		}

		$location = $this->createRouteURL($request_param, $params);

		/*
		$accion = $this->rutas_privadas['reload'];
		if ($accion != '') {
			$this->setParam('xxxx', $location);
			$this->openAction($accion);
		}

		*/

		$mensaje = '';

		if (!$this->isJSONRequest()) {
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

	public function setDataBeforeReload(array $data) {

		$filedata = '';

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
		// SCRIPT_NAME = /micode-manager/public/index.php
		// SCRIPT_FILENAME = C:\xxx\micode-manager\public\index.php
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

	public function isJSONRequest() {

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
				'<li><b>JSON:</b> ' .				($this->isJSONRequest() ? 'true' : 'false') . '</li>' . PHP_EOL .
				'<li><b>Parámetros:</b> ' . 		$this->request() . '</li>' . PHP_EOL .
				// Este print_r() puede hacer que la salida a pantalla se altere. Porqué?
				// Cuando el parámetro return se usa, esta función utiliza el almacenamiento en búfer de salida interno,
				// por lo que no puede usarse dentro de una función de llamada de retorno ob_start().
				// https://www.php.net/manual/es/function.print-r.php
				// '<li><b>Rutas:</b><pre>' .		print_r($this->getLoadedRoutes(), true) . '</pre></li>' .
				'<li><b>Rutas:</b>' .				miframe_debug_dump($this->getLoadedRoutes()) . '</li>' .
				'<li><b>Ruta usada:</b>' .			$this->ruta_usada . '</li>' .
				'</ul>'
				);
	}
}

/*
class miAttributes {

	public $attribs = array();

	public function __construct(array $attribs) {
		// Define los atributos a manejar y sus valores por defecto
		$this->attribs = $attribs;
	}

	/**
	 * Recupera valor de un atributo
	 *-/
	public function __get(string $name) {

		if (array_key_exists($name, $this->attribs)) {
			return $this->attribs[$name];
		}

		return false;
	}

	/**
	 * Para asignar un valor a un atributo se invoca como método
	 *-/
	public function __call(string $name, mixed $arguments) {

		// Validar que sea del mismo tipo del definido?
		if (array_key_exists($name, $this->attribs)) {
			$this->attribs[$name] = $arguments;
		}
	}
}
*/