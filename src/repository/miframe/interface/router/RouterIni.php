<?php
/**
 * Librería para control de enrutamientos de código a través de archivos INI.
 *
 * @micode-uses miframe-interface-router
 * @author John Mejia
 * @since Abril 2022
 */

namespace miFrame\Interface;

/**
 * Clase usada para realizar el enrutamiento dinámico.
 */
class RouterIni extends \miFrame\Interface\Router {

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
	 *     index = (script a ejecutar cuando no recibe enrutamiento o el enrutamiento apunta al index.php)
	 *     abort = (script a ejecutar en respuesta a $this->abort())
	 *     before-stop = (script a ejecutar antes de detener todo)
	 *
	 *     [public]
	 *     (enrutamiento) = (script a ejecutar):(alias)
	 *     ...
	 *
	 *     [public-get]
	 *     (enrutamiento) = (script a ejecutar)
	 *     ...
	 *
	 * 	   [errors]
	 *     404 = (script a ejecutar)
	 *     ...
	 *
	 * Las reglas para la declaración del enrutamiento se describen en la documentación de la función runOnce().
	 *
	 * El grupo "private" contiene declaración para acciones estándar, como el script a mostrar
	 * cuando no se detecta nada (index) o en casos de llamar al método "abort".
	 *
	 * El grupo "public" contiene declaración de enrutamientos aplicados para cualquier método de consulta sea web
	 * (GET, POST) o los adiconales usados para servicios web (HEAD, DELETE, PUT, PATCH). Para definir el mapa de
	 * enrutamientos propios de un método específico, use el grupo "public-xxxx" donde la "xxxx" corresponde al nombre
	 * del método de interés (GET, POST, HEAD, DELETE, PUT, PATCH).
	 *
	 * El grupo "config" permite realizar configuraciones a la clase. Las opciones validas para este grupo son:
	 * - debug: boolean. TRUE para presentar mensajes de depuración.
	 * - method: string. Puede ser "post", "get", "request" o "uri". Método usado para detectar el enrutamiento.
	 * - name_post: string. Nombre de la variable asociada al modo de captura (no requerida para "uri").
	 *
	 * El grupo "error-xxx" permite declarar plantillas a mostrar para codigos de errores especificos. Del tipo:
	 * (codigo error) = script
	 *
	 *
	 * @param string $filename Nombre del archivo .ini a cargar,
	 * @param string $dirbase  Path a usar para ubicar los scripts.
	*/
	public function loadConfig(string $filename) {

		if (!file_exists($filename)) {
			miframe_error('Archivo no encontrado: $1', $filename);
		}

		// Captura datos del archivo .ini

		$data = parse_ini_file($filename, true, INI_SCANNER_RAW);

		foreach ($data as $type => $group) {
			if ($type == 'config') {
				$this->addConfigAttributes($group);
			}
			elseif ($type == 'private') {
				$this->addPrivateRoutes($group);
			}
			elseif (strtolower(substr($type, 0, 7)) == 'public-' ||
				$type == 'public'
				) {
				$this->addPublicRoutes($group, $type);
			}
			elseif ($type == 'errors') {
				$this->addErrorRoutes($group);
			}
		}
	}

	private function addConfigAttributes(array $data) {

		// Configura modo de captura
		$method_bind = '';
		$name_post = '';

		if (isset($group['method'])) { $method_bind = trim($group['method']); }
		if (isset($group['name_post'])) { $name_post = trim($group['name_post']); }
		if ($method_bind != '' || $name_post != '') {
			$this->assignMode(($method_bind == 'uri'), $name_post);
		}

		// Configura modo debug
		if (isset($group['debug'])) {
			$this->debug = boolval($group['debug']);
		}
	}

	private function addPrivateRoutes(array $data) {

		// Evalua contenido
		foreach ($data as $reference => $accion) {
			switch ($reference) {
				case 'index':
					$this->addIndexRoute($accion);
					break;
				case 'abort':
					$this->addAbortRoute($accion);
					break;
				case 'before-stop':
					$this->addBeforeStopRoute($accion);
					break;
				default:
			}
		}
	}

	public function addErrorRoutes(array $data) {

		foreach ($data as $code => $action) {
			if (is_numeric($code)) {
				$this->addErrorRoute($code, $action);
			}
			else {
				miframe_error('Código de error no valido ($1) al declarar rutas de Error', $code);
			}
		}
	}

	/**
	 * Carga enrutamientos definidos en un arreglo.
	 * Los enrutamientos son del tipo:
	 *
	 * - (URI esperado) = (script) o
	 * - (URI esperado) = (script):(alias)
	 *
	 * Cuando no indica el valor de (alias) este se construye con el (URI esperado) eliminando los carácteres "?"
	 * y cambiando "/" por "-", por ejemplo: "path1/?var1/path2" asume alias "path1-var1-path2".
	 *
	 * @param array  $data 	  Arreglo de datos a cargar.
	 * @param string $method  Método asociado (POST, GET, etc.). En blanco, cualquiera.
	 * @param bool  		  TRUE si se pudo relacionar el arreglo, FALSE en otro caso.
	*/
	private function addPublicRoutes(array $data, string $method = '') {

		// Remueve "public-" de $method si aplica (cuando se carga desde .ini)
		$method = trim(str_replace(['public' , '-'], '', $method));

		foreach ($data as $reference => $accion) {
			// El alias se indica separando con ":" (opcional)
			$arreglo = explode(':', $accion . ':');
			$this->addRoute($reference, $arreglo[0], $method, $arreglo[1]);
		}

		return true;
	}

	// PENDIENTE: Habilitar caché sobre privadas y publicas para no reconstruir todo cada vez!
	// ...
}
