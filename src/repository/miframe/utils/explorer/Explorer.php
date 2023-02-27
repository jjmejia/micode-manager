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
 * Se puede generar toda la navegación usando los estilos propietarios o pueden ser personalizados.
 *
 * ## Mensajes de error
 *
 * Se pueden presentar Exceptions con los siguientes códigos (en $e->getMessage() retorna el directorio
 * al que hace referencia el mensaje de error):
 *
 * - 1001 No pudo recuperar valor para DOCUMENT ROOT.
 * - 1002 El path indicado para el directorio base (a partir del cual va a navegar) no es valido.
 * - 1003 El path indicado para construir una URL no es valido.
 * - 1005 El path del DOCUMENT ROOT no es un directorio valido o no pudo ser recuperado.
 *
 * @micode-uses miframe/common/shared
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
 * - $stylesCSS: string. Estilos CSS a usar. Si emplea un archivo externo, use: "url:(path)".
 */
class Explorer {

	private $filename = '';
	private $basedir = '';
	private $root = '';
	private $document_root = '';
	private $arreglofav = array();	// Arreglo de favoritos
	private $error_code = 0;
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

		// Fija el DOCUMENT_ROOT
		$this->setDocumentRoot();
		// Fija el directorio de exploración por defecto (DOCUMENT_ROOT)
		$this->setRoot();
		// Fija directorio de Favoritos
		$this->setFavoritesPath();
		// Predefine extensiones para las que puede visualizar contenido
		$this->showContentsFor = array(
			'htm'  => 'html',
			'html' => 'html',
			'php'  => 'html',
			'txt'  => 'text',
			'md'   => 'text',
			'ini'  => 'text',
			'json' => 'text',
			'jpg'  => 'img',
			'jpeg' => 'img',
			'gif'  => 'img',
			'svg'  => 'img',
			'ico'  => 'img',
			'png'  => 'img',
			'pdf'  => 'pdf'
		);
		// Predefine enlaces para los que permite ejecución o seguimiento de links
		$this->followLinks = array(
			'html',
			'htm',
			'php'
		);
	}

	public function addFollowLink(string $name) {

		$name = strtolower(trim($name));
		if ($name != '' && !in_array($name, $this->followLinks)) {
			$this->followLinks[] = $name;
		}
	}

	public function removeFollowLink(string $name) {

		$name = strtolower(trim($name));
		$pos = array_search($name, $this->followLinks);
		if ($name != '' && $pos !== false) {
			unset($this->followLinks[$pos]);
		}
	}

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
		}
		else {
			// DEBE contener al DOCUMENT_ROOT? No necesariamente.
			// $this->root = $this->evalDocumentRoot($dir);
			$this->error_code = 2;
			$this->throwError($dir);
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

	public function setFavoritesPath(string $dir = '') {

		$this->fileFavorites = '';
		if ($dir == '') { $dir = $this->document_root; }
		if ($dir != '' && is_dir($dir)) {
			$this->fileFavorites = realpath($dir) . DIRECTORY_SEPARATOR . 'favoritos.ini';
		}
	}

	/**
	 * Configura enlaces a usar para visualizar documentación de scripts.
	 * $url debe permitir concatenar el nombre del archivo a documentar (debería terminar en "var=")
	 *
	 * @param string $extension Extension asociada.
	 * @param string $fun Función predefinida o una personalizada.
	 * @param string $type Tipo de argumento a usar con $fun (para el caso personalizado). "filename"/"contents"
	 *               (por defecto)
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

	public function removeContentsFun(string $extension) {

		$extension = strtolower(trim($extension));
		if ($extension != '' && isset($this->showContentsFor[$extension])) {
			unset($this->showContentsFor[$extension]);
		}
	}

	/**
	 * Recupera el listado de archivos o contenido asociado a un archivo.
	 *
	 * @param string $baselink Enlace principal. A este enlace se suman los parámetros para navegación en línea.
	 * @return array Arreglo con la información asociada, ya sean directorios y archivos o el contenido de un archivo.
	 */
	public function explore(string $baselink) {

		$salida = array();

		// Complementa el enlace base
		if (strpos($baselink, '?') !== false) { $this->baselink = $baselink . '&'; }
		else { $this->baselink = $baselink . '?'; }

		$this->arreglofav = array();
		// Captura listado de favoritos
		if ($this->useFavorites && $this->fileFavorites != '' && file_exists($this->fileFavorites)) {
			$this->arreglofav = file($this->fileFavorites, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		}

		$salida = $this->getBaseDir();

		if (is_array($salida)) {
			if ($this->filename != '') {
				// Visualización de Archivos
				$salida += $this->getFileData();
			}
			else {
				// Visualización de directorios
				$salida += $this->getDirectoryData();
			}
		}

		return $salida;
	}

	private function getBaseDir() {

		$salida = array();

		// Path a mostrar
		if (isset($_REQUEST['dir'])) {
			$this->basedir = trim($_REQUEST['dir']);
		}
		elseif (isset($_REQUEST['file'])) {
			$this->basedir = trim($_REQUEST['file']);
		}
		elseif ($this->useFavorites) {
			// Adicionar favorito
			if (isset($_REQUEST['favadd'])) {
				$favorito = strtolower(trim($_REQUEST['favadd']));
				$favorito_full = $this->root . $favorito;
				if (file_exists($favorito_full) && is_file($favorito_full)) {
					$this->basedir = dirname($favorito);
					// Adiciona a favoritos.ini
					if (!in_array($favorito, $this->arreglofav)) {
						$this->arreglofav[] = $favorito;
						if ($this->useFavorites && $this->fileFavorites != '') {
							file_put_contents($this->fileFavorites, implode("\n", $this->arreglofav));
						}
					}
				}
			}
			// Retirar favorito
			elseif (isset($_REQUEST['favrem'])) {
				$favorito = strtolower(trim($_REQUEST['favrem']));
				if ($favorito != '') {
					$guardar = false;
					do {
						$pos = array_search($favorito, $this->arreglofav);
						// echo "$pos / $favorito<hr>";
						if ($pos !== false) {
							$this->basedir = dirname($favorito);
							unset($this->arreglofav[$pos]);
							$guardar = true;
						}
					} while ($pos != false);
					// Guarda archivo
					file_put_contents($this->fileFavorites, implode("\n", $this->arreglofav));
				}
			}
		}

		// Limpia basedir
		if ($this->basedir != '') {
			$this->basedir = str_replace('..', '_', $this->basedir);
			while (substr($this->basedir, -1, 1) == '/') {
				$this->basedir = substr($this->basedir, 0, -1);
			}
			while (substr($this->basedir, 0, 1) == '/') {
				$this->basedir = substr($this->basedir, 1);
			}
			if ($this->basedir == '.') { $this->basedir = ''; }
		}

		if ($this->basedir != '') {
			// Valida que pueda navegar localmente
			$real = str_replace('\\', '/', realpath($this->root . $this->basedir));
			$this->basedir = str_replace('\\', '/', $this->basedir);

			if ($real != '') {
				if (is_dir($real)) {
					$this->basedir = substr($real, strlen($this->root)) . '/';
					}
				else {
					// Path de un archivo a abrir
					$this->filename = substr($real, strlen($this->root));
				}
			}
			else {
				// Intenta salirse del directorio web
				return false;
			}

			// Adiciona enlaces a directorios previos
			$dirname = dirname($this->basedir);
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

		return $salida;
	}

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

					case 'img':
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
					$contenido = file_get_contents($filename_full);
				}
				$salida['content'] = call_user_func($this->showContentsFor[$extension]['fun'], $contenido);
			}
		}

		return $salida;
	}

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
				$seguir_enlace = (in_array($extension, $this->followLinks));
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
					$indirecto = ($enlace == '' || !in_array($extension, $this->followLinks));
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

		$this->error_code = 1;
		if (isset($_SERVER['DOCUMENT_ROOT'])) {
			// Usa documento root por defecto para limitar accesos
			$this->document_root = realpath($_SERVER['DOCUMENT_ROOT']);
			$this->error_code = 5;
		}
		if ($this->document_root != '' && is_dir($this->document_root)) {
			$this->document_root = str_replace("\\", '/', $this->document_root) . '/';
		}
		else {
			// Reporta un directorio no valido para prevenir consulte el raiz si retorna vacio.
			$this->throwError($this->document_root);
		}
	}

	private function evalDocumentRoot(string $dir) {

		$real = '';
		$path = trim($dir);
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
	 * Retorna el URL asociado.
	 * El enlace se genera con origen en el DOCUMENT ROOT para evitar conflictos con el directorio raíz usado.
	 *
	 * @param string $path Enlace a reconstruir. Si se omite, usa el valor definido en $this->filename.
	 * @return string URL.
	 */
	public function url(string $path = '') {

		if ($path == '') { $path = $this->filename; }
		$path = $this->evalDocumentRoot($path);

		return $path;
	}

	/**
	 * Formatea contenido del archivo texto indicado por $this->filename.
	 * Hace clickables los enlaces contenidos en el texto.
	 *
	 * @return string Texto formateado para HTML.
	 */
	private function formatText() {

		$contenido = file_get_contents($this->root . $this->filename);
		// https://stackoverflow.com/a/206087
		$contenido = preg_replace(
				'#((https?|ftp)://(\S*?\.\S*?))([\s)\[\]{},;"\'<]|\.\s|$)#i',
				"<a href=\"$1\" target=\"_blank\">$1</a>$4",
				$contenido
				);
		$salida = '<pre>' . $contenido . '</pre>';

		return $salida;
	}

	/**
	 * Formatea contenido del archivo HTML indicado por $this->filename.
	 * Hace clickables los enlaces contenidos en el texto.
	 *
	 * @return string Texto formateado para HTML.
	 */
	private function formatHtml() {

		$contenido = highlight_file($this->root . $this->filename, true);
		// https://stackoverflow.com/a/206087
		$contenido = preg_replace(
				'#((https?|ftp)://(\S*?\.\S*?))([\s)\[\]{},;"\'<]|\.\s|$)#i',
				"<a href=\"$1\" target=\"_blank\">$1</a>$4",
				$contenido
				);

		return $contenido;
	}

	private function formatMD() {

		$salida = '';
		if (is_callable($this->parserTextFunction)) {
			$contenido = file_get_contents($this->root . $this->filename);
			$salida = call_user_func($this->parserTextFunction, $contenido);
		}
		else {
			$salida = $this->formatText();
		}

		return $salida;
	}

	public function formatBytes($size) {

		$num = 0;
		$tipos = array('bytes', ' KB', ' MB', ' GB');
		$ciclos = -1;
		do {
			$num = $size;
			$ciclos ++;
			$size = ($size / 1024);
		} while ($size >= 1 && isset($tipos[$ciclos]));

		return str_replace('.00', '', number_format($num, 2)) . ' ' . $tipos[$ciclos];
	}

	private function throwError(string $detail) {
		throw new \Exception($detail, 1000 + $this->error_code);
	}
}