<?php
/**
 * Clase para generar salida HTML a los datos generados por la clase Explorer.
 *
 * @micode-uses miframe-common-shared
 * @micode-uses miframe-utils-htmlsupport
 * @author John Mejia
 * @since Julio 2022
 */
namespace miFrame\Utils\Explorer;

class ExplorerHTML extends Explorer {

	private $html = false;				// Objeto Utils/UI/HTMLSupport

	public function __construct() {
		// Ejecuta __construct() de la clase padre (Explorer)
		parent::__construct();
		// Para uso en HTMLSupport
		$this->html = new \miFrame\Utils\UI\HTMLSupport();
		$this->html->setFilenameCSS(__DIR__ . '/explorer-styles.css');
	}

	/**
	 * Genera presentación del listado de archivos o contenido asociado a un archivo, en formato HTML.
	 *
	 * @param string $baselink Enlace principal. A este enlace se suman los parámetros para navegación en línea.
	 * @return string HTML.
	 */
	public function render(string $baselink) {

		$listado = $this->explore($baselink);

		// Adiciona estilos en líne (si previamente no han sido incluidos)
		$salida = $this->html->getStylesCSS(true);

		$salida .= '<div class="x-explorer">';

		if (isset($listado['favorites']) && count($listado['favorites']) > 0) {
			// Listado de favoritos
			$salida .= '<div class="x-favorites">';
			foreach ($listado['favorites'] as $k => $info) {
				$target = '';
				$color = 'bi-star-indirect';
				if (!$info['indirect']) {
					// No es un direccionamiento indirecto
					$target = ' target="xfav_' . $k . '"';
					$color = 'bi-star-fill';
				}
				$salida .= '<div><i class="bi ' . $color . '"></i> ' .
					// Enlace para abrir favorito
					'<a href="' . $info['url'] . '"' . $target . '>' . $info['title'] . '</a>' .
					// Enlace para remover de la lista
					' <a href="' . $info['rem'] . '" class="x-favlink" title="Retirar de favoritos"><i class="bi bi-dash-circle"></i></a>' .
					'</div>';
			}
			$salida .= '</div>' . PHP_EOL;
		}

		if (isset($listado['paths'])) {
			// Arma los paths asociados a la posición actual
			$salida .= '<p>';
			$primer_path = true;
			foreach ($listado['paths'] as $path => $enlace) {
				if ($path == '.') {
					$salida .= "[ <a href=\"{$enlace}\">Inicio</a> ] ";
				}
				else {
					// $salida .= ' &rsaquo; ';
					if ($enlace != '') {
						$enlace_path = "<a href=\"{$enlace}\">{$path}</a>";
						if ($primer_path) {
							// El primer enlace lo resalta
							$salida .= '<b>' . $enlace_path . '</b>';
							$primer_path = false;
						}
						else {
							$salida .= $enlace_path;
						}
						$salida .= ' / ';
					}
					else {
						$salida .= "<b>{$path}</b>";
					}
				}
			}
			if ($listado['type'] == 'file' && $listado['add-fav'] != '') {
				// Adiciona enlace para adicionar a favoritos
				$salida .= ' <a href="' . $listado['add-fav'] . '" class="x-favlink" title="Adicionar a favoritos"><i class="bi bi-plus-circle"></i></a>';
			}
			$salida .= '</p>' . PHP_EOL;
		}

		$totaldirs = 0;
		$totalfiles = 0;
		$errores = $this->getError();

		if ($errores !== false) {
			// Hay errores reportados
			if ($errores['code'] == 3) {
				$detalles = $errores['details'];
				$salida .= "<b class=\"x-error\">" . miframe_text('Referencia no encontrada') . "</b>" .
							"<p class=\"x-info\">{$detalles['param']} = {$detalles['value']}</p>";
			}
			elseif ($errores['code'] == 4) {
				$salida .= "<b class=\"x-error\">" . miframe_text('No pudo guardar enlaces favoritos') . "</b>" .
							"<p class=\"x-info\">" . miframe_text('Ubicación del archivo: $1', $errores['favorites']) . "</p>";
			}
			else {
				$salida .= "<b class=\"x-error\">" . miframe_text('Ha ocurrido un error ($1)', $errores['code']) . "</b>" .
							"<div style=\"margin-top:10px\" class=\"x-info\">" .
							miframe_text('Los siguientes detalles están disponibles: $1', "<pre>" . htmlspecialchars(print_r($errores['details'], true)) . "</pre>") .
							"</div>";
			}
			$salida .= "<a href=\"{$errores['main-url']}\">Volver al inicio</a>";
		}
		elseif (isset($listado['type']) && $listado['type'] == 'file') {
			// Muestra contenido de archivo
			$salida .= '<div class="x-info">' .
				'<table><tr><td><b>Creado en:</b></td><td>' . $listado['date-creation'] . '</td></tr>' .
				'<tr><td><b>Última modificación:</b></td><td>' . $listado['date-modified'] . '</td></tr>' .
				'<tr><td><b>Tamaño:</b></td><td>' . miframe_bytes2text($listado['size'], true) . '</td></tr></table>' .
				// '<tr><td><b>Encoding:</b></td><td>' . $listado['encoding'] . '</td></tr></table>' .
				'</div>';
			$salida .= '<div class="x-' . $listado['class'] . '">' . $listado['content'] . '</div>';
		}
		else {
			// Directorios y archivos
			if (isset($listado['dirs'])) {
				$totaldirs = count($listado['dirs']);
				foreach ($listado['dirs'] as $ufilename => $info) {
					// Listado de directorios
					$salida .= '<div class="x-folder"><i class="bi bi-folder-fill"></i> ' .
						'<a href="' . $info['url-content'] . '"> ' . $info['name'] . '</a>' .
						'</div>';
				}
			}

			if (isset($listado['files'])) {
				$totalfiles = count($listado['files']);
				foreach ($listado['files'] as $ufilename => $info) {
					// Listado de archivos
					$enlace = $info['file'];
					if ($info['url-content'] != '') {
						// Enlace para visualizar contenido
						$enlace = '<a href="' . $info['url-content'] . '">' . $enlace . '</a>';
					}
					if ($info['url'] != '') {
						// Enlace para ejecutar el archivo en el navegador (follow-link)
						$enlace .= ' <a href="' . $info['url'] . '" class="x-favlink" title="Ejecutar" target="_blank"><i class="bi bi-box-arrow-up-right"></i></a>';
					}
					if ($info['add-fav'] != '') {
						$enlace .= ' <a href="' . $info['add-fav'] . '" class="x-favlink" title="Adicionar a favoritos"><i class="bi bi-plus-circle"></i></a>';
					}
					$file_class = 'bi-file';
					if ($info['in-fav']) {
						$file_class = 'bi-file-check';
					}
					elseif ($info['class'] != '') {
						$file_class = 'bi-file-' . $info['class'];
					}
					$salida .= '<div class="x-file"><i class="bi ' . $file_class . '"></i> ' .
								$enlace .
								'</div>';
				}
			}
		}

		$salida .= '</div>';

		// Total de elementos
		if ($totaldirs + $totalfiles > 0) {
			$salida .= '<div class="x-totales">' . miframe_text('Encontrados $1 elemento(s)', ($totaldirs + $totalfiles));
			$salida .= ': ';
			if ($totaldirs > 0) {
				$salida .= ' ' . miframe_text('$1 directorio(s)', $totaldirs);
			}
			if ($totalfiles > 0) {
				$salida .= ' ' . miframe_text('$1 archivo(s)', $totalfiles);
			}
			$salida .= '</div>';
		}

		return $salida;
	}

	public function getStylesCSS() {
		return $this->html->getStylesCSS();
	}
}