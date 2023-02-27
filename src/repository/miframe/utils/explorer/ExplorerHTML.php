<?php

namespace miFrame\Utils\Explorer;

// Clase base
include_once __DIR__ . '/Explorer.php';

class ExplorerHTML extends Explorer {

	private $show_styles = true;

	// public function __construct() {	}

	/**
	 * Recupera el listado de archivos o contenido asociado a un archivo, en formato HTML.
	 *
	 * @param string $baselink Enlace principal. A este enlace se suman los parámetros para navegación en línea.
	 * @return string HTML.
	 */
	public function render(string $baselink) {

		$listado = $this->explore($baselink);

		// echo "<pre>"; print_r($listado); echo "</pre><hr>";

		if (!is_array($listado)) {
			$salida = "Error: Path indicado (" . $baselink .") no es valido.<br /><a href=\"$baselink\">Volver al Inicio</a>";
			return $salida;
		}

		$salida = $this->getStylesCSS(true);

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
			foreach ($listado['paths'] as $path => $enlace) {
				if ($path == '.') {
					$salida .= "[ <a href=\"{$enlace}\">Inicio</a> ]";
				}
				else {
					$salida .= ' &rsaquo; ';
					if ($enlace != '') {
						$salida .= "<a href=\"{$enlace}\">{$path}</a>";
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

		if ($listado['type'] == 'file') {
			// Muestra contenido de archivo
			$salida .= '<div class="x-info">' .
				'<table><tr><td><b>Creado en:</b></td><td>' . $listado['date-creation'] . '</td></tr>' .
				'<tr><td><b>Última modificación:</b></td><td>' . $listado['date-modified'] . '</td></tr>' .
				'<tr><td><b>Tamaño:</b></td><td>' . $this->formatBytes($listado['size']) . '</td></tr></table>' .
				'</div>';
			$salida .= '<div class="x-' . $listado['class'] . '">' . $listado['content'] . '</div>';
			return $salida;
		}

		foreach ($listado['dirs'] as $ufilename => $info) {
			// Listado de directorios
			$salida .= '<div class="x-folder"><i class="bi bi-folder-fill"></i> ' .
				'<a href="' . $info['url-content'] . '"> ' . $info['name'] . '</a>' .
				'</div>';
		}

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
			$salida .= '<div class="x-file"><i class="bi ' . $file_class . '"></i> ' . $enlace . '</div>';
		}

		$salida .= '</div>';

		// Total de elementos
		$totaldirs = count($listado['dirs']);
		$totalfiles = count($listado['files']);
		$salida .= '<div class="x-totales">Encontrados ' . ($totaldirs + $totalfiles) . ' elemento(s)';
		if ($totaldirs + $totalfiles > 0) {
			$salida .= ': ';
			$conector = '';
			if ($totaldirs > 0) {
				$salida .= $totaldirs . ' directorio(s)';
				$conector = ' y ';
			}
			if ($totalfiles > 0) {
				$salida .= $conector . $totalfiles . ' archivo(s)';
			}
		}
		$salida .= '</div>';

		return $salida;
	}

	/**
	 * Carga estilos.
	 * Puede personalizarse los estilos usando $this->stylesCSS. Si emplea un archivo externo, use: "url:(path)".
	 * Si incluye estilos CSS directamente, no debe usar el tag "<style>", solo el texto que iría dentro del tag.
	 *
	 * @return string Estilos o link a usar.
	 */
	public function getStylesCSS(bool $return = false) {

		$salida = '';

		$filename = __DIR__ . DIRECTORY_SEPARATOR . 'explorer-styles.css';
		if ($this->show_styles && file_exists($filename)) {
			// Existe ruta al archivo CSS
			if (!$return) {
				// REmueve el DOCUMENT_ROOT. Si no existe, no tiene nada que retornar pues
				// el archivo no sería accequible al navegador.
				$recurso = $this->url($filename);
				if ($recurso != '') {
					$salida = '<link rel="stylesheet" href="' . $recurso . '">' . PHP_EOL;
				}
			}
			else {
				// Retorna contenido de archivo
				$salida = '<style>' . PHP_EOL . file_get_contents($filename) . PHP_EOL . '</style>' . PHP_EOL;
			}
			$this->show_styles = false;
		}

		return $salida;
	}

	public function dontShowStyles() {
		$this->show_styles = false;
	}
}