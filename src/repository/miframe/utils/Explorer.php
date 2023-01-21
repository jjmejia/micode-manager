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
 * @micode-uses miframe/common/shared
 * @author John Mejia
 * @since Julio 2022
 */

namespace miFrame\Utils;

/**
 * Clase para explorar directorios y consultar archivos en línea.
 * Las siguientes propiedades públicas pueden ser usadas:
 *
 * - $fileFavorites: Path del archivo "favoritos.ini" (por defecto se almacena en el directorio raíz).
 * - $useFavoritos: TRUE para habilitar las opciones de Favoritos, esto es, visualizar en la navegación y actualizar archivo .ini.
 * - $parserTextFunction: Función a usar para interpretar el texto (asumiendo formato Markdown). Retorna texto HTML. Ej:
 * 		function (text) { ... return $html; }
 * - $stylesCSS: string. Estilos CSS a usar. Si emplea un archivo externo, use: "url:(path)".
 * - $ignoreLocalStyles: boolean. Indica si debe ignorar estilos internos (automáticamente se fija a TRUE luego de imprimir estilos).
 */
class Explorer {

	private $filename = '';
	private $basedir = '';
	private $root = '';
	private $arreglofav = array();	// Arreglo de favoritos

	// * - $showContentsFor: array. Extensiones para las que se muestra el contenido. Por defecto se habilita para
	// *   las siguientes extensiones: 'txt', 'jpg', 'jpeg', 'gif', 'svg', 'pdf', 'ico', 'png', 'md', 'ini', 'json'.
	private $showContentsFor = array();
	private $followLinks = array();

	public $fileFavorites = '';
	public $useFavorites = true;
	public $parserTextFunction = false;
	public $stylesCSS = '';
	public $ignoreLocalStyles = false;

	public function __construct() {

		$this->setRoot();
		$this->showContentsFor = array(
			'txt' => 'text',
			'md' => 'text',
			'ini' => 'text',
			'json' => 'text',
			'jpg' => 'img',
			'jpeg' => 'img',
			'gif' => 'img',
			'svg' => 'img',
			'ico' => 'img',
			'png' => 'img',
			'pdf' => 'pdf'
		);
		$this->followLinks = [ 'html', 'htm', 'php' ];
	}

	/**
	 * Define el directorio raíz.
	 * Por defecto se usa el DOCUMENT ROOT del servidor web.
	 * La exploración de directorios no irá por debajo de este directorio.
	 *
	 * @param string $dir Directorio.
	 * @param bool $set_favorites TRUE define automáticamente el path para el archivo favoritos.ini
	 */
	public function setRoot(string $dir = '', bool $set_favorites = true) {

		$this->root = trim($dir);
		if ($this->root == '') {
			// Usa documento root por defecto para limitar accesos
			$this->root = $this->documentRoot();
		}
		elseif ($this->root != '') {
			$this->root = str_replace("\\", '/', realpath($this->root));
			if ($this->root != '' && substr($this->root, -1, 1) != '/') {
				$this->root .= '/';
			}
		}
		if ($set_favorites) {
			if ($this->root != '') {
				$this->fileFavorites = $this->root . 'favoritos.ini';
			}
			else {
				$this->fileFavorites = '';
			}
		}
	}

	/**
	 * Retorna el Path real usado como directorio raíz.
	 * Excluye el segmento asociado al DOCUMENT ROOT.
	 *
	 * @return string Path.
	 */
	public function getRoot() {
		return str_replace($this->documentRoot(), '', $this->root);
	}

	/**
	 * Configura enlaces a usar para visualizar documentación de scripts.
	 * $url debe permitir concatenar el nombre del archivo a documentar (debería terminar en "var=")
	 *
	 * @param string $extension Extension asociada.
	 * @param string $fun Función predefinida o una personalizada.
	 * @param string $type Tipo de argumento a usar con $fun (para el caso personalizado). "filename"/"contents" (por defecto)
	 */
	public function setContentsFun(string $extension, mixed $fun, string $type = '') {

		$type = strtolower(trim($type));
		$extension = strtolower(trim($extension));
		if ($extension != '') {
			if (is_callable($fun)) {
				$this->showContentsFor[$extension] = array('fun' => $fun, 'type' => $type);
			}
			elseif (in_array($fun, [ 'text', 'img', 'pdf', 'down' ])) {
				$this->showContentsFor[$extension] = $fun;
			}
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
		if (strpos($baselink, '?') !== false) { $baselink .= '&'; }
		else { $baselink .= '?'; }

		$this->arreglofav = array();
		// Captura listado de favoritos
		if ($this->useFavorites && $this->fileFavorites != '' && file_exists($this->fileFavorites)) {
			$this->arreglofav = file($this->fileFavorites, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		}

		// Path a mostrar
		if (isset($_REQUEST['dir'])) {
			$this->basedir = trim($_REQUEST['dir']);
			while (substr($this->basedir, -1, 1) == '/') {
				$this->basedir = substr($this->basedir, 0, -1);
			}
		}
		// Adicionar favorito
		elseif (isset($_REQUEST['favadd'])) {
			$favorito = strtolower(trim($_REQUEST['favadd']));
			if (file_exists($this->root . $favorito) && is_file($this->root . $favorito)) {
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
				$this->basedir = dirname($favorito);
				$pos = array_search($favorito, $this->arreglofav);
				// echo "$pos / $favorito<hr>";
				if ($pos !== false) {
					unset($this->arreglofav[$pos]);
					if ($this->useFavorites) {
						file_put_contents($this->fileFavorites, implode("\n", $this->arreglofav));
					}
				}
			}
		}

		if ($this->basedir == '.') { $this->basedir = ''; }

		if ($this->basedir != '') {
			// Valida que pueda navegar localmente
			$real = str_replace('\\', '/', realpath($this->root . $this->basedir));
			$this->basedir = str_replace('\\', '/', $this->basedir);
			// echo $real . ' /// ' . $this->basedir . ' ///// ' . $this->root . '<hr>';
			if (substr($real, strlen($this->root)) == $this->basedir) {
				if (is_dir($this->root . $this->basedir)) {
					$this->basedir .= '/';
					}
				else {
					// Path de un archivo a abrir
					$this->filename = $this->basedir;
				}
			}
			else {
				// Intenta salirse del directorio web
				return false;
			}
		}

		// Path previo
		// Visualización de Archivos
		if ($this->filename != '') {
			$contenido = '';
			$filename = $this->root . $this->filename;
			$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
			// Información del archivo
			$salida['info'] = array(
				'fcreation' => date('Y/m/d H:i:s', filectime($filename)),
				'fmodified' => date('Y/m/d H:i:s', filemtime($filename)),
				'size' => filesize($filename)
			);
			$salida['class'] = '';
			// Evalua archivos texto
			if (isset($this->showContentsFor[$extension])) {
				if (!is_array($this->showContentsFor[$extension])) {
					switch ($this->showContentsFor[$extension]) {

						case 'text':
							$salida['content'] = $this->formatText();
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
					$contenido = $filename;
					if ($this->showContentsFor[$extension]['type'] != 'filename') {
						$contenido = file_get_contents($filename);
					}
					// echo htmlentities($contenido); echo "<hr>";
					$salida['content'] = call_user_func($this->showContentsFor[$extension]['fun'], $contenido);
				}
			}
			/*
			if (in_array($extension, [ 'txt', 'ini', 'json' ])) {
				$salida['content'] = $this->formatText();
				$salida['class'] = 'text';
				}
			// Evalua archivos Markdown (requiere haya definido parser)
			elseif ($extension == 'md') {
				$salida['content'] = $this->formatMD();
				$salida['class'] = 'md';
			}
			elseif ($extension == 'pdf') {
				// https://stackoverflow.com/a/36234568
				$salida['content'] = '<embed src="' . $this->url() . '" type="application/pdf">';
				// $salida['html'] = '<div class="x-pdf">$1</div>';
				$salida['class'] = 'pdf';
			}
			elseif (in_array($extension, [ 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'png' ])) {
				// Imagenes
				// Debería armar ruta desde WWROOT para evitar conflictos cuando cambia el root
				$salida['content'] = '<img src="' . $this->url() . '">';
				// $contenido = '<div class="x-imagen">$1</div>';
				$salida['class'] = 'image';
			}
			else {
				// Extensión incluida por el usuario
				$salida['content'] = '<a href="' . $this->url() . '">Descargar ' . basename($this->filename) . '</a>';
				// $contenido = '<div class="x-imagen">$1</div>';
				$salida['class'] = 'down';
			}
			*/

			$salida['type'] = 'file';
			// $salida['filename'] = $this->filename;
			$salida['extension'] = $extension;
		}
		else {
			// Visualización de directorios
			$salida = array('type' => 'dir', 'dirs' => array(), 'files' => array(), 'favorites' => array());

			$fileLista = glob($this->root . $this->basedir . '*');

			foreach ($fileLista as $k => $filename) {
				$filename = substr($filename, strlen($this->root));
				$ufilename = strtolower($filename);
				// $enlace = 'index.php?dir=' . urlencode(str_replace('=', '', base64_encode($filename)));
				$enlace = $baselink . 'dir=' . urlencode($filename);
				if (is_dir($this->root . $filename)) {
					$salida['dirs'][$ufilename] = '<a href="' . $enlace . '">' . basename($filename) . '</a>';
				}
				else {
					$extension = strtolower(pathinfo($this->root . $filename, PATHINFO_EXTENSION));
					if (in_array($extension, $this->followLinks)) {
						if ($this->useFavorites && !in_array($ufilename, $this->arreglofav)) {
							// $salida['files'][$ufilename] = '<a href="' . $this->url($filename) . '">' . basename($filename) . '</a>';
							$salida['favorites-add'][$ufilename] = $baselink . 'favadd=' . urlencode($filename);
						}
						// else {
						// 	$salida['files'][$ufilename] = '<a href="' . $this->url($filename) . '"><b>' . basename($filename) . '</b></a>';
						// }
						$salida['files-url'][$ufilename] = $this->url($filename);
					}
					if (isset($this->showContentsFor[$extension])) {
						$salida['files'][$ufilename] = '<a href="' . $enlace . '">' . basename($filename) . '</a>';
					}
					else {
						$salida['files'][$ufilename] = basename($filename);
					}
				}
			}

			// Adiciona favoritos
			if (count($this->arreglofav) > 0) {
				foreach ($this->arreglofav as $k => $ufilename) {
					$salida['favorites'][$ufilename] = '<a href="' . $ufilename . '" target="xfav_' . $k . '">' . str_replace('/', ' / ', $ufilename) . '</a>';
					$salida['favorites-rem'][$ufilename] = $baselink . 'favrem=' . urlencode($ufilename);
				}
				// echo '<hr size="1" style="color:#ccc">';
			}

			ksort($salida['dirs']);
			ksort($salida['files']);
			ksort($salida['favorites']);
		}

		// Adiciona enlaces a directorios previos
		if ($this->basedir != '') {
			$dirname = dirname($this->basedir);
			$acum = '';
			$salida['paths'][] = '[ <a href="' . substr($baselink, 0, -1) . '">Inicio</a> ]';
			// echo $basedir . '<hr>';
			if ($dirname != '.') {
				$predir = explode('/', $dirname);
				foreach ($predir as $k => $path) {
					$salida['paths'][] .= '<a href="' . $baselink . 'dir=' . urlencode($acum . $path) . '">' . $path . '</a>';
					$acum .= $path . '/';
					// $enlace .= ' / ';
				}
			}
			$salida['paths'][] = '<b>' . basename($this->basedir) . '</b></p>';
		}

		return $salida;
	}

	/**
	 * Recupera el listado de archivos o contenido asociado a un archivo, en formato HTML.
	 *
	 * @param string $baselink Enlace principal. A este enlace se suman los parámetros para navegación en línea.
	 * @return string HTML.
	 */
	public function exploreHTML(string $baselink) {

		$listado = $this->explore($baselink);

		if (!is_array($listado)) {
			$salida = "Error: Path indicado (" . $this->basedir .") no es valido.<br /><a href=\"$baselink\">Volver al Inicio</a>";
			return $salida;
		}

		$salida = $this->getStylesCSS();

		if (isset($listado['paths'])) {
			$salida .= '<p>' . implode(' &rsaquo; ', $listado['paths']) . '</p>';
		}

		$salida .= '<div class="x-explorer">';

		if ($listado['type'] == 'file') {

			$salida .= '<div class="x-info">' .
				// print_r($listado['info'], true) .
				'<table><tr><td><b>Creado en:</b></td><td>' . $listado['info']['fcreation'] . '</td></tr>' .
				'<tr><td><b>Última modificación:</b></td><td>' . $listado['info']['fmodified'] . '</td></tr>' .
				'<tr><td><b>Tamaño:</b></td><td>' . $this->formatBytes($listado['info']['size']) . '</td></tr></table>' .
				'</div>';
			$salida .= '<div class="x-' . $listado['class'] . '">' . $listado['content'] . '</div>';
			return $salida;
		}

		if (count($listado['favorites']) > 0) {
			foreach ($listado['favorites'] as $ufilename => $enlace) {
				$salida .= '<div class="x-star"><i class="bi bi-star-fill"></i> ' . $enlace;
				if (isset($listado['favorites-rem'][$ufilename])) {
					$salida .= ' <a href="' . $listado['favorites-rem'][$ufilename] . '" class="x-favlink" title="Retirar de favoritos"><i class="bi bi-dash-circle"></i></a>';
				}
				$salida .= '</div>';
			}
			$salida .= '<hr size="1" style="color:#ccc">';
		}
		foreach ($listado['dirs'] as $ufilename => $enlace) {
			$salida .= '<div class="x-folder"><i class="bi bi-folder-fill"></i> ' . $enlace . '</div>';
		}
		foreach ($listado['files'] as $ufilename => $enlace) {
			$extension = strtolower(pathinfo($this->root . $ufilename, PATHINFO_EXTENSION));
			/*if (isset($this->documentar[$extension])) {
				$dirback = '';
				if (isset($_REQUEST['dir'])) {
					$dirback = trim($_REQUEST['dir']);
				}
				$enlace .= ' <a href="' .
					str_replace(array('{file}', '{dir}'),
						array(urlencode($ufilename), urlencode($dirback)),
						$this->documentar[$extension]
					) .
					'" class="x-favlink">[Ver documentación]</a>';
			}*/
			if (isset($listado['files-url'][$ufilename])) {
				$enlace .= ' <a href="' . $listado['files-url'][$ufilename] . '" class="x-favlink" title="Ejecutar" target="_blank">[ Abrir ]</a>';
			}
			if (isset($listado['favorites-add'][$ufilename])) {
				$enlace .= ' <a href="' . $listado['favorites-add'][$ufilename] . '" class="x-favlink" title="Adicionar a favoritos"><i class="bi bi-plus-circle"></i></a>';
			}
			$salida .= '<div class="x-file"><i class="bi bi-file"></i> ' . $enlace . '</div>';
		}

		$salida .= '</div>';

		return $salida;
	}

	/**
	 * Retorna el path real del DOCUMENT ROOT, en el formato requerido para validaciones.
	 * Use las palabras '{file}' y '{dir}' para que sean buscadas en el enlace y remplazadas por el nombre
	 * del archivo asociado y el directorio actual.
	 *
	 * @return string Path.
	 */
	private function documentRoot() {
		$root = '';
		if (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] != '') {
			// Usa documento root por defecto para limitar accesos
			$root = str_replace("\\", '/', realpath($_SERVER['DOCUMENT_ROOT'])) . '/';
		}

		return $root;
	}

	/**
	 * Retorna el URL asociado.
	 * El enlace se genera con origen en el DOCUMENT ROOT para evitar conflictos con el directorio raíz usado.
	 *
	 * @param string $path Enlace a reconstruir. Si se omite, usa el valor definido en $this->filename.
	 * @return string URL.
	 */
	private function url(string $path = '') {

		$real = '';

		if ($path == '') { $path = $this->filename; }
		if ($path != '') {
			$real = str_replace("\\", '/', realpath($this->root . $path));
			$root = $this->documentRoot();
			$len = strlen($root);
			if (substr($real, 0, $len) == $root) {
				$real = '/' . substr($real, $len);
			}
			else {
				$real = basename($this->filename); // Para referencia, no es un enlace valido
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

		$contenido = file_get_contents($this->root . $this->filename);
		// https://stackoverflow.com/a/206087
		$contenido = preg_replace(
				'#((https?|ftp)://(\S*?\.\S*?))([\s)\[\]{},;"\':<]|\.\s|$)#i',
				"<a href=\"$1\" target=\"_blank\">$1</a>$4",
				$contenido
				);
		$salida = '<pre>' . $contenido . '</pre>';

		return $salida;
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

	private function formatBytes($size) {

		$num = 0;
		$tipos = array('', ' KB', ' MB', ' GB');
		$ciclos = -1;
		do {
			$num = $size;
			$ciclos ++;
			$size = ($size / 1024);
		} while ($size >= 1 && isset($tipos[$ciclos]));

		return str_replace('.00', '', number_format($num, 2)) . $tipos[$ciclos];
	}

	/**
	 * Carga estilos.
	 * Puede personalizarse los estilos usando $this->stylesCSS. Si emplea un archivo externo, use: "url:(path)".
	 * Si incluye estilos CSS directamente, no debe usar el tag "<style>", solo el texto que iría dentro del tag.
	 *
	 * @return string Estilos o link a usar.
	 */
	private function getStylesCSS() {

		$salida = '';
		if ($this->ignoreLocalStyles) { return $salida; }
		$this->ignoreLocalStyles = true;

		if ($this->stylesCSS != '') {
			$salida = trim($this->stylesCSS);
			if (strtolower(substr($salida, 0, 4)) == 'url:') {
				$salida = '<link rel="stylesheet" href="' . substr($salida, 4) . '">' . PHP_EOL;
			}
			else {
				$salida = '<style>' . PHP_EOL . substr($salida, 4) . PHP_EOL . '</style>' . PHP_EOL;
			}
			$salida = $this->stylesCSS;
		}
		else {
			$salida = '
<style>
.x-explorer {
	margin:14px 0;
	font-family:"Segoe UI",Arial;
	font-size:14px;
}
.x-md, .x-text {
	border:1px solid #ccc;
	background-color:#fafafa;
	padding:12px 24px !important;
	font-size:16px !important;
	max-width:100%;
	overflow:auto;
	}
.x-image {
	margin:0;
	}
.x-image img {
	border:1px solid #ccc;
	padding:4px;
	background-color:#fafafa;
	max-height:600px;
	}
.x-pdf embed {
	width:100%;
	height:600px;
	border:1px solid #333;
}
.x-explorer div {
	padding:3px 0;
	font-size:13px;
}
.x-explorer .x-info {
	border:1px solid #ccc;
	background-color:#fafafa;
	padding:12px 24px !important;
	font-size:12px !important;
	max-width:100%;
	overflow:auto;
	margin-bottom:10px;
}
.x-explorer .x-info td {
	padding:2px;
	padding-right:10px;
}
.x-explorer .bi {
	margin-right:10px;
	}
.x-star .bi-star-fill {
	color:#283593;
	}
.x-favlink {
	margin-left:15px;
	}
.x-explorer .bi {
	display: inline-block;
	content: "";
	vertical-align: -.250em;
	background-repeat: no-repeat;
	background-position: bottom center;
	background-size: 16px 16px;
	width:16px;
	height:16px;
}
.x-explorer .bi-folder-fill {
	/* https://icons.getbootstrap.com/icons/folder-fill/ */
	background-image: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'%239e9d24\' viewBox=\'0 0 16 16\'><path d=\'M9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.825a2 2 0 0 1-1.991-1.819l-.637-7a1.99 1.99 0 0 1 .342-1.31L.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3zm-8.322.12C1.72 3.042 1.95 3 2.19 3h5.396l-.707-.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981l.006.139z\'/></svg>");
}
.x-explorer .bi-file {
	/* https://icons.getbootstrap.com/icons/file-earmark/ */
	background-image: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'currentColor\' viewBox=\'0 0 16 16\'><path d=\'M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z\'/></svg>");
}
.x-explorer .bi-star-fill {
	/* https://icons.getbootstrap.com/icons/star-fill/ */
	background-image: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'%23283593\' viewBox=\'0 0 16 16\'><path d=\'M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z\'/></svg>");
}
.x-explorer .bi-plus-circle
{
	/* https://icons.getbootstrap.com/icons/plus-circle/ */
	background-image: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'%23777\' viewBox=\'0 0 16 16\'><path d=\'M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z\'/><path d=\'M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z\'/></svg>");
}
.x-explorer .bi-dash-circle {
	/* https://icons.getbootstrap.com/icons/dash-circle/ */
	background-image: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'%230969da\' viewBox=\'0 0 16 16\'><path d=\'M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z\'/><path d=\'M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8z\'/></svg>");
}
.x-explorer .bi-dash-circle, .x-explorer .bi-plus-circle {
	width:14px;
	height:14px;
	background-size: 14px 14px;
}
</style>
';
		}

		return $salida;
	}
}