<?php
/**
 * Clase para explorar directorios y consultar archivos en línea.
 * Permite declarar enlaces favoritos para accesos rápidos, que son almacenados en el archivo
 * "favoritos.ini", por defecto generado en el directorio raíz (requiere permisos de escritura).
 *
 * La navegación en línea se realiza mediante el uso de los parámetros post:
 *
 * - dir: Path a explorar (descendiente del directorio raíz)
 * - favadd: URL a adicionar a favoritos.
 * - favrem: URL a remover de favoritos.
 *
 * Pueden adicionarse a favoritos aquellos archivos cuya extensión se encuentre en la lista de
 * $this->showContentsFor o que tengan un visor de contenido asociado (en este caso, se realiza apertura
 * "indirecta" del contenido).
 *
 * Se puede generar toda la navegación usando los estilos propietarios o pueden ser personalizados.
 *
 * ## Mensajes de error
 *
 * Se pueden presentar los siguientes códigos de error (en $this->error_data):
 *
 * - 1: No pudo recuperar valor para DOCUMENT ROOT.
 * - 2: El path indicado para el directorio base (a partir del cual va a navegar) no es valido.
 * - 3: El directorio o archivo indicado en POST (dir/file) no existe. No genera interrupción, salta al directorio raíz.
 * - 4: No pudo crear/actualizar archivo de favoritos.
 *
 * @author John Mejia
 * @since Julio 2022
 */

namespace miFrame\Utils\Explorer;

/**
 * Clase para explorar directorios y consultar archivos en línea.
 * Las siguientes propiedades públicas pueden ser usadas:
 *
 * - $useFavoritos: TRUE para habilitar las opciones de Favoritos, esto es, visualizar en la navegación y actualizar archivo .ini.
 * - $parserTextFunction: Función a usar para interpretar el texto (asumiendo formato Markdown). Retorna texto HTML. Ej:
 * 		function (text) { ... return $html; }
 */
class Explorer {

	private $filename = '';
	private $basedir = '';
	private $root = '';
	private $document_root = '';
	private $arreglofav = array();	// Arreglo de favoritos
	private $error_data = array();
	private $baselink = '';

	// * - $showContentsFor: array. Extensiones para las que se muestra el contenido. Por defecto se habilita para
	// *   las siguientes extensiones: 'txt', 'jpg', 'jpeg', 'gif', 'svg', 'pdf', 'ico', 'png', 'md', 'ini', 'json'.
	private $showContentsFor = array();
	private $followLinks = array();
	// * - $fileFavorites: Path del archivo "favoritos.ini" (por defecto se almacena en el directorio raíz).
	private $fileFavorites = '';

	public $useFavorites = true;
	public $parserTextFunction = false;

	public function __construct() {

		$this->error_data = array('code' => 0, 'details' => array());
		// Fija el DOCUMENT_ROOT
		$this->setDocumentRoot();
		// Fija el directorio de exploración por defecto (DOCUMENT_ROOT)
		$this->setRoot();
		// Predefine extensiones para las que puede visualizar contenido
		$this->showContentsFor = array(
			'htm'  => 'html',
			'html' => 'html',
			'php'  => 'html',
			'xml'  => 'html',
			'txt'  => 'text',
			'md'   => 'text',
			'ini'  => 'text',
			'json' => 'text',
			'css'  => 'text',
			'log'  => 'text',
			'jpg'  => 'image',
			'jpeg' => 'image',
			'gif'  => 'image',
			'svg'  => 'image',
			'ico'  => 'image',
			'png'  => 'image',
			'pdf'  => 'pdf'
		);
		// Predefine enlaces para los que permite ejecución o seguimiento de links
		$this->addFollowLink('html');
		$this->addFollowLink('htm');
		$this->addFollowLink('php');
	}

	/**
	 * Adiciona extensión de archivo (script) que puede consultarse directamente en el navegador.
	 *
	 * @param string $extension Extension asociada.
	 */
	public function addFollowLink(string $extension) {

		$extension = strtolower(trim($extension));
		if ($extension != '') {
			$this->followLinks[$extension] = '.' . $extension;
		}
	}

	/**
	 * Remueve extensión de archivo (script) para que no pueda consultarse directamente en el navegador.
	 *
	 * @param string $extension Extensión asociada.
	 */
	public function removeFollowLink(string $extension) {

		$extension = strtolower(trim($extension));
		if ($extension != '' && isset($this->followLinks[$extension])) {
			unset($this->followLinks[$extension]);
		}
	}

	/**
	 * Limpia la lista de extensiones de archivos (scripts) que pueden consultarse directamente en el navegador.
	 */
	public function clearFollowLinks() {

		$this->followLinks = array();
	}

	/**
	 * Define el directorio raíz.
	 * Por defecto se usa el DOCUMENT ROOT del servidor web.
	 * La exploración de directorios no irá por debajo de este directorio.
	 *
	 * @param string $dir Directorio.
	 */
	public function setRoot(string $dir = '') {

		$dir = trim($dir);
		if ($dir == '') { $dir = $this->document_root; }
		if ($dir != '' && is_dir($dir)) {
			$this->root = str_replace("\\", '/', realpath($dir)) . '/';
			// Fija directorio de Favoritos
			$this->setFavoritesPath();
			}
		else {
			// DEBE contener al DOCUMENT_ROOT? No necesariamente.
			$this->error_data['code'] = 2;
			$this->error_data['details']['path'] = $dir;
			// $this->throwError($dir);
		}
	}

	/**
	 * Retorna el Path real usado como directorio raíz.
	 * Excluye el segmento asociado al DOCUMENT ROOT.
	 * Por seguridad, asegurese siempre que sea a un directorio permitido y no lo deje configurable por web,
	 * podría dar acceso a directorios sensibles a usuarios mal intencionados.
	 *
	 * @return string Path.
	 */
	public function getRoot() {
		return str_replace($this->document_root, '', $this->root);
	}

	/**
	 * Fija directorio para el archivo a usar para guardar listado de favoritos.
	 * El directorio a usar debe tener permisos para lectura y escritura. Por defecto usa DOCUMENT ROOT.
	 *
	 * @param string $dir Directorio destino.
	 */
	public function setFavoritesPath(string $dir = '') {

		$this->fileFavorites = '';
		if ($dir == '') { $dir = $this->root; }
		if ($dir != '' && is_dir($dir)) {
			$this->fileFavorites = realpath($dir) . DIRECTORY_SEPARATOR . 'miframe-explorer-favorites.ini';
		}
	}

	/**
	 * Configura enlaces a usar para visualizar contenido o información de un archivo.
	 *
	 * @param string $extension Extension asociada.
	 * @param string $fun Función predefinida o una personalizada.
	 * @param string $type Tipo de argumento a usar con $fun (para el caso personalizado). Puede ser
	 *               "filename" para recibir el path completo del archivo o "contents" para recibir el
	 *               contenido del archivo (este es el valor por defecto si no se especifica alguno).
	 */
	public function setContentsFun(string $extension, mixed $fun, string $type = '') {

		$type = strtolower(trim($type));
		$extension = strtolower(trim($extension));
		if ($extension != '') {
			if (is_callable($fun)) {
				$this->showContentsFor[$extension] = array('fun' => $fun, 'type' => $type);
			}
			elseif (in_array($fun, [ 'text', 'img', 'pdf', 'down', 'html' ])) {
				$this->showContentsFor[$extension] = $fun;
			}
		}
	}

	/**
	 * Remueve enlace asociado a una extensión para visualización de contenido.
	 *
	 * @param string $extension Extension asociada.
	 */
	public function removeContentsFun(string $extension) {

		$extension = strtolower(trim($extension));
		if ($extension != '' && isset($this->showContentsFor[$extension])) {
			unset($this->showContentsFor[$extension]);
		}
	}

	private function getFavorites() {
		$this->arreglofav = array();
		// Captura listado de favoritos
		if ($this->useFavorites && $this->fileFavorites != '' && file_exists($this->fileFavorites)) {
			$this->arreglofav = file($this->fileFavorites, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		}

	}

	private function saveFavorites(string $action, string $filename) {

		$resultado = @file_put_contents($this->fileFavorites, implode("\n", $this->arreglofav));
		// Si $resultado === false no pudo guardar archivo.
		if (!$resultado) {
			$this->error_data['code'] = 4;
			// $this->error_data['details']['path'] = $this->fileFavorites;
			$this->error_data['details']['param'] = $action;
			$this->error_data['details']['value'] = $filename;
		}

		return $resultado;
	}

	/**
	 * Recupera el listado de archivos o contenido asociado a un archivo.
	 *
	 * @param string $baselink Enlace principal. A este enlace se suman los parámetros para navegación en línea.
	 * @return array Arreglo con la información asociada, ya sean directorios y archivos o el contenido de un archivo.
	 */
	public function explore(string $baselink) {

		$salida = array();

		if ($this->continue()) {
			// Complementa el enlace base
			if (strpos($baselink, '?') !== false) { $this->baselink = $baselink . '&'; }
			else { $this->baselink = $baselink . '?'; }

			$this->getFavorites();

			$salida = $this->getBaseDir();

			// Valida que no hayan ocurrido errores al procesar $this->getBaseDir()
			if ($this->continue()) {
				if ($this->filename != '') {
					// Visualización de Archivos
					$salida += $this->getFileData();
				}
				else {
					// Visualización de directorios
					$salida += $this->getDirectoryData();
				}
			}
		}

		return $salida;
	}

	/**
	 * Procesa los parámetros recibidos por web para ubicar el directorio o archivo a explorar.
	 *
	 * @return array Arreglo de paths asociados al directorio de exploración.
	 */
	private function getBaseDir() {

		$salida = array();
		$this->basedir = '';
		$post = '';

		// Path a mostrar
		if (isset($_REQUEST['dir'])) {
			$post = 'dir';
		}
		elseif (isset($_REQUEST['file'])) {
			$post = 'file';
		}

		if ($post != '') {
			// Limpia basedir
			$this->basedir = str_replace("\\", '/', trim($_REQUEST[$post]));
			if ($this->basedir != '') {
				while (substr($this->basedir, -1, 1) == '/') {
					$this->basedir = substr($this->basedir, 0, -1);
				}
				while (substr($this->basedir, 0, 1) == '/') {
					$this->basedir = substr($this->basedir, 1);
				}
				if ($this->basedir == '.') { $this->basedir = ''; }
			}
		}

		if ($this->useFavorites) {
			// Adicionar favorito
			if (isset($_REQUEST['favadd'])) {
				$favorito = strtolower(trim($_REQUEST['favadd']));
				$favorito_full = $this->root . $favorito;
				if (file_exists($favorito_full) && is_file($favorito_full)) {
					if ($post == '') {
						$post = 'favadd';
						$this->basedir = dirname($favorito);
					}
					// Adiciona a favoritos.ini
					if (!in_array($favorito, $this->arreglofav)) {
						$this->arreglofav[] = $favorito;
						if ($this->useFavorites && $this->fileFavorites != '') {
							$resultado = $this->saveFavorites('favadd', $favorito);
						}
					}
				}
			}
			// Retirar favorito
			if ($this->continue() && isset($_REQUEST['favrem'])) {
				$favorito = strtolower(trim($_REQUEST['favrem']));
				if ($favorito != '') {
					$guardar = false;
					do {
						$posfav = array_search($favorito, $this->arreglofav);
						// echo "$pos / $favorito<hr>";
						if ($posfav !== false) {
							if ($post == '') {
								$post = 'favrem';
								$this->basedir = dirname($favorito);
							}
							unset($this->arreglofav[$posfav]);
							$guardar = true;
						}
					} while ($posfav !== false);
					// Guarda archivo
					$resultado = $this->saveFavorites('favrem', $favorito);
				}
			}
		}

		if ($this->continue() && $this->basedir != '') {
			// Valida que pueda navegar localmente
			$real = str_replace('\\', '/', realpath($this->root . $this->basedir));
			$this->basedir = str_replace('\\', '/', $this->basedir);

			if ($real != '') {
				$this->basedir = substr($real, strlen($this->root));
				if (is_dir($real)) {
					$this->basedir .= '/';
					}
				else {
					// Path de un archivo a abrir
					$this->filename = $this->basedir;
				}
			}
			else {
				// Intenta salirse del directorio web
				$this->error_data['code'] = 3;
				$this->error_data['details']['param'] = $post;
				$this->error_data['details']['value'] = htmlspecialchars($_REQUEST[$post]);
				$this->error_data['details']['path'] = $this->basedir;
			}
		}

		if ($this->continue() && $this->basedir != '') {
			// Adiciona enlaces a directorios previos (siempre que no esté en el raiz)
			// NOTA: Al adicionar o eliminar un favorito de la raiz, $this->basedir toma
			// el path de dicho archivo.
			$dirname = dirname($this->basedir);
			if ($dirname != DIRECTORY_SEPARATOR) {
				$acum = '';
				$salida['paths']['.'] = substr($this->baselink, 0, -1);
				if ($dirname != '.') {
					$predir = explode('/', $dirname);
					foreach ($predir as $k => $path) {
						$salida['paths'][$path] = $this->baselink . 'dir=' . urlencode($acum . $path);
						$acum .= $path . '/';
					}
				}
				$salida['paths'][basename($this->basedir)] = '';
			}
		}

		if (!$this->continue()) {
			// Elimina acceso por precaución
			$this->basedir = '';
			$this->filename = '';
		}

		return $salida;
	}

	/**
	 * Recupera la información del archivo solicitado ($this->filename).
	 * Incluye contenido a mostrar para tipos de archivos declarados en $this->showContentsFor.
	 * Los tipos predefinidos son: "html", "text", "pdf", "image", "down".
	 *
	 * @return array Arreglo de datos.
	 */
	private function getFileData() {

		$filename_full = $this->root . $this->filename;
		$extension = strtolower(pathinfo($filename_full, PATHINFO_EXTENSION));
		$ufilename = strtolower($this->filename);

		$salida = array(
			'type' => 'file',
			// 'extension' => $extension,
			'class' => '',
			// Información del archivo
			'date-creation' => date('Y/m/d H:i:s', filectime($filename_full)),
			'date-modified' => date('Y/m/d H:i:s', filemtime($filename_full)),
			'size' => filesize($filename_full),
			'encoding' => '',
			'add-fav' => '',
			'content' => ''
			);

		if ($this->useFavorites
			&& !in_array($ufilename, $this->arreglofav)
			// && !in_array('?' . $ufilename, $this->arreglofav)
			) {
			$salida['add-fav'] = $this->baselink . 'favadd=' . urlencode($ufilename);
		}
		// Evalua archivos texto
		if (isset($this->showContentsFor[$extension])) {
			if (!is_array($this->showContentsFor[$extension])) {
				switch ($this->showContentsFor[$extension]) {

					case 'text':
						$salida['content'] = $this->formatText();
						$salida['class'] = 'text';
						break;

					case 'html':
						$salida['content'] = $this->formatHtml();
						$salida['class'] = 'text';
						break;

					case 'image':
						// Imagenes
						// Debería armar ruta desde WWROOT para evitar conflictos cuando cambia el root
						$salida['content'] = '<img src="' . $this->url() . '">';
						$salida['class'] = 'image';
						break;

					case 'pdf':
						// https://stackoverflow.com/a/36234568
						$salida['content'] = '<embed src="' . $this->url() . '" type="application/pdf">';
						$salida['class'] = 'pdf';
						break;

					case 'down': // Descargar
						$salida['content'] = '<a href="' . $this->url() . '">Descargar ' . basename($this->filename) . '</a>';
						$salida['class'] = 'down';
						break;

					default:
						if ($this->showContentsFor[$extension] !== '') {
							// No definido (usa DOWN por defecto)
							$salida['content'] = '<b>Error:</b> Tipo "' . $this->showContentsFor[$extension] . '" no valido<p><a href="' . $this->url() . '">Descargar ' . basename($this->filename) . '</a>';
							$salida['class'] = 'down';
							}

				}
			}
			else {
				// Funciones
				$contenido = $filename_full;
				if ($this->showContentsFor[$extension]['type'] != 'filename') {
					$contenido = $this->get_contents($filename_full);
				}
				$salida['content'] = call_user_func($this->showContentsFor[$extension]['fun'], $contenido);
			}
		}

		return $salida;
	}

	/**
	 * Recupera la información del archivo solicitado ($this->filename).
	 *
	 * @return array Arreglo de datos.
	 */
	private function getDirectoryData() {

		$salida = array(
			'type' => 'dir',
			'dirs' => array(),
			'files' => array(),
			'favorites' => array(),
		);

		$fileLista = glob($this->root . $this->basedir . '*');

		foreach ($fileLista as $k => $filename_full) {
			$filename = substr($filename_full, strlen($this->root));
			$ufilename = strtolower($filename);
			$ordenbase = basename($ufilename) . ':' . $k;
			$extension = strtolower(pathinfo($filename_full, PATHINFO_EXTENSION));
			$enlace = '';

			// PENDIENTE: Modificar $ordenbase dependiendo del tipo de ordenamiento deseado

			if (is_dir($this->root . $filename)) {
				$enlace = $this->baselink . 'dir=' . urlencode($filename);
				$salida['dirs'][$ordenbase] = array(
					'name' => basename($filename),
					'date-modified' => filemtime($filename_full),
					'url-content' => $enlace
				);
			}
			else {
				$enlace = $this->baselink . 'file=' . urlencode($filename);
				$clase = '';
				if (isset($this->showContentsFor[$extension])
					&& !is_array($this->showContentsFor[$extension])
					) {
					// Es una clase predefinida
					$clase = $this->showContentsFor[$extension];
				}
				$info = array(
					'file' => basename($filename),
					'date-creation' => filectime($filename_full),
					'date-modified' => filemtime($filename_full),
					'size' => filesize($filename_full),
					'class' => $clase,
					'url' => '',
					'url-content' => '',
					'add-fav' => '',
					'in-fav' => false
				);
				$seguir_enlace = isset($this->followLinks[$extension]);
				if ($this->useFavorites) {
					if (!in_array($ufilename, $this->arreglofav)) {
						if ($seguir_enlace) {
							// Incluye opcion de adicionar a favoritos directamente en la lista
							$info['add-fav'] = $this->baselink . 'favadd=' . urlencode($filename);
						}
					}
					else {
						// Ya registrado en favoritos
						$info['in-fav'] = true;
					}
				}
				if ($seguir_enlace) {
					// El acceso directo para followLinks solamente funciona si el $this->root
					// contiene a $this->document_root
					$info['url'] = $this->url($filename_full);
				}
				if (isset($this->showContentsFor[$extension])) {
					$info['url-content'] = $enlace;
				}
				$salida['files'][$ordenbase] = $info;
			}
		}

		// Adiciona favoritos (siempre los ordena por nombre)
		if (count($this->arreglofav) > 0) {
			$len = strlen($this->root);
			foreach ($this->arreglofav as $k => $ufilename) {
				// Se asegura que el link tenga el nombre exacto del archivo y que este exista
				// (No elimina automaticamente los no existentes en caso que haga cambios en el
				// $this->root dinamicamente).
				$filename = realpath($this->root . $ufilename);
				if ($filename != '') {
					$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
					$enlace = $this->url($filename);
					$indirecto = ($enlace == '' || !isset($this->followLinks[$extension]));
					if ($indirecto) {
						// Remplaza el link por el visualizador indirecto
						$enlace = $this->baselink . 'file=' . urlencode($ufilename);
					}
					$salida['favorites'][$ufilename] = array(
						'url' => $enlace,
						'title' => str_replace('\\', '/', substr($filename, $len)),
						'rem' => $this->baselink . 'favrem=' . urlencode($ufilename),
						'indirect' => $indirecto
					);
				}
			}
		}

		ksort($salida['dirs']);
		ksort($salida['files']);
		ksort($salida['favorites']);
		// Retira llaves usadas para ordenar
		$salida['dirs'] = array_values($salida['dirs']);
		$salida['files'] = array_values($salida['files']);
		$salida['favorites'] = array_values($salida['favorites']);

		return $salida;
	}

	/**
	 * Retorna el path real del DOCUMENT ROOT, en el formato requerido para validaciones.
	 * Use las palabras '{file}' y '{dir}' para que sean buscadas en el enlace y remplazadas por el nombre
	 * del archivo asociado y el directorio actual.
	 *
	 * @return string Path.
	 */
	private function setDocumentRoot() {

		if (isset($_SERVER['DOCUMENT_ROOT'])) {
			// Usa documento root por defecto para limitar accesos
			$this->document_root = realpath($_SERVER['DOCUMENT_ROOT']);
		}
		if ($this->document_root != '' && is_dir($this->document_root)) {
			$this->document_root = str_replace("\\", '/', $this->document_root) . '/';
		}
		else {
			// Reporta un directorio no valido para prevenir consulte el raiz si retorna vacio.
			// $this->throwError($this->document_root);
			$this->error_data['code'] = 1;
			$this->error_data['details']['path'] = $this->document_root;
		}
	}

	/**
	 * Retorna segmento del path de un archivo sin incluir DOCUMENT ROOT, para usar como URL.
	 *
	 * @param string $path Ruta de archivo o directorio. Si se omite, usa el valor definido en $this->filename.
	 * @return string URL.
	 */
	public function url(string $path = '') {

		$real = '';
		$path = trim($path);
		if ($path == '') { $path = $this->filename; }
		if ($path != '') {
			// Maneja todos los separadores como "/"
			// Valida SIEMPRE contra el DOCUMENT_ROOT
			$base = str_replace("\\", '/', realpath($path));
			$len = strlen($this->document_root);
			if ($base != '' && substr($base, 0, $len) == $this->document_root) {
				$real = substr($base, $len);
				if (is_dir($base)) {
					// Es directorio, adiciona separador al final. Sino, corresponde a un archivo.
					$real .= '/';
				}
			}
		}

		return $real;
	}

	/**
	 * Formatea contenido del archivo texto indicado por $this->filename.
	 * Hace clickables los enlaces contenidos en el texto.
	 *
	 * @return string Texto formateado para HTML.
	 */
	private function formatText() {

		$salida = '';
		$contenido = $this->get_contents($this->root . $this->filename);
		if ($contenido != '') {
			$contenido = htmlspecialchars(iso2utf8($contenido));
			$salida = '<pre>' . $this->formatLinks($contenido) . '</pre>';
		}

		return $salida;
	}

	/**
	 * Da formato HTML a enlaces en contenidos de archivos texto y html.
	 *
	 * @param  string $text Contenido texto a dar formato.
	 * @return string Texto formateado para HTML.
	 */
	private function formatLinks(string $text) {

		if ($text != '') {
			// https://stackoverflow.com/a/206087
			$text = preg_replace(
					'#((https?|ftp)://(\S*?\.\S*?))([\s)\[\]{},;"\'<]|\.\s|$)#i',
					"<a href=\"$1\" target=\"_blank\">$1</a>$4",
					$text
					);
		}

		return $text;
	}

	/**
	 * Formatea contenido del archivo HTML indicado por $this->filename.
	 * Hace clickables los enlaces contenidos en el texto.
	 *
	 * @return string Texto formateado para HTML.
	 */
	private function formatHtml() {

		$salida = '';
		$contenido = @highlight_file($this->root . $this->filename, true);
		if ($contenido != '') {
			// https://stackoverflow.com/a/206087
			$contenido = $this->formatLinks(iso2utf8($contenido));
		}

		return $contenido;
	}

	/**
	 * Formatea contenido del archivo markdown (MD) indicado por $this->filename.
	 * Hace clickables los enlaces contenidos en el texto.
	 *
	 * @return string Texto formateado para HTML.
	 */
	private function formatMD() {

		$salida = '';
		if (is_callable($this->parserTextFunction)) {
			$contenido = $this->get_contents($this->root . $this->filename);
			if ($contenido != '') {
				$salida = call_user_func($this->parserTextFunction, $contenido);
			}
		}
		else {
			$salida = $this->formatText();
		}

		return $salida;
	}

	/**
	 * Retorna arreglo con errores.
	 * El arreglo contiene: "code" código de error y "details" arreglo con información del error.
	 * Los códigos reportados son:
	 * - 1 No pudo recuperar valor para DOCUMENT ROOT. Como detalle reporta el "path" encontrado (si alguno).
	 * - 2 El path indicado para el directorio base (a partir del cual va a navegar) no es valido. Como detalle reporta el
	 *     "path" encontrado (si alguno).
	 * - 3 El directorio o archivo indicado en POST (dir/file) no existe. Como detalle reporta el parámetro recibido via POST
	 *     ("post" = file, dir, ...), el valor recibido en dicho parámetro ("value") y su interpretación ("path").
	 *
	 * @param int          $error_code Código de error a evaluar. Si no se indica, retorna todo el arreglo de errores. Si se
	 *                                 indica y existe el error asociado a ese código, retorna los detalles asociados.
	 * @return bool/array              Arreglo de datos o FALSE si no aplica.
	 */
	public function getError() {

		$retornar = false;

		if ($this->error_data['code'] > 0) {
			// Retorna todo (si hay error)
			$retornar = $this->error_data;
			// Complementa respuesta
			$retornar['root'] = $this->root;
			$retornar['favorites'] = $this->fileFavorites;
			$retornar['main-url'] = substr($this->baselink, 0, -1);
		}

		return $retornar;
	}

	/**
	 * Valida si han ocurrido errores en la exploración del directorio.
	 *
	 * @return bool TRUE si puede continuar, FALSE si ha ocurrido algún error.
	 */
	private function continue() {
		return ($this->error_data['code'] == 0);
	}

	private function get_contents(string $filename) {

		$contenido = '';
		if (file_exists($filename)) {
			$contenido = @file_get_contents($filename);
		}

		return $contenido;
	}
}