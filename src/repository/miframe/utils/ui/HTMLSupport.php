<?php
/**
 * Librería de soporte para clases usadas para generar texto HTML.
 *
 * @author John Mejia
 * @since Febrero 2023
 */

namespace miFrame\Utils\UI;

class HTMLSupport {

	private $show_styles = false;
	private $filenameCSS = '';

	// public function __construct() {
	// }

	/**
	 * Fija la ubicación del archivo de estilos CSS a usar.
	 * El sistema valida que el archivo exista en disco. También puede indicarse una URL directamente (no será
	 * validada su existencia) adicionando el prefijo "url:".
	 *
	 * @param string $filename Ubicación del archivo CSS.
	 */
	public function setFilenameCSS(string $filename) {
		$this->filenameCSS = trim($filename);
		$this->show_styles = ($this->filenameCSS != '');
	}

	/**
	 * Retorna segmento del path de un archivo sin incluir DOCUMENT ROOT, para usar como URL.
	 *
	 * @param string $path Ruta de archivo o directorio a validar.
	 * @return string URL.
	 */
	private function getURLFromPath(string $path) {

		$real = '';
		$path = trim($path);
		if ($path != '') {
			// Maneja todos los separadores como "/"
			// Si el $path inicia con "url:" no valida el archivo como tal, asume es correcta la URL indicada.
			if (strtolower(substr($path, 0, 4)) == 'url:') {
				$real = trim(substr($path, 4));
			}
			else {
				// Valida SIEMPRE contra el DOCUMENT_ROOT
				$document_root = str_replace("\\", '/', realpath($_SERVER['DOCUMENT_ROOT']));
				$base = str_replace("\\", '/', realpath($path));
				$len = strlen($document_root);
				if ($base != '' && substr($base, 0, $len) == $document_root) {
					$real = substr($base, $len);
					if (is_dir($base)) {
						// Es directorio, adiciona separador al final. Sino, corresponde a un archivo.
						$real .= '/';
					}
				}
			}
		}

		return $real;
	}

	/**
	 * Estilos CSS incluidos con esta librería.
	 *
	 * @param  bool   $return Fijar a true para retornar los estilos, false para generar link al archivo CSS.
	 * @return string Estilos o link a usar.
	 */
	public function getStylesCSS(bool $return = false) {

		$salida = '';

		if ($this->show_styles && $this->filenameCSS != '') {
			// Existe ruta al archivo CSS
			if (!$return) {
				// REmueve el DOCUMENT_ROOT. Si no existe, no tiene nada que retornar pues
				// el archivo no sería accequible al navegador.
				$recurso = $this->getURLFromPath($this->filenameCSS);
				if ($recurso != '') {
					$salida = '<link rel="stylesheet" href="' . $recurso . '">' . PHP_EOL;
				}
			}
			elseif (file_exists($this->filenameCSS)) {
				// Retorna contenido de archivo
				$salida = '<style>' . PHP_EOL . file_get_contents($this->filenameCSS) . PHP_EOL . '</style>' . PHP_EOL;
			}
			else {
				$salida = '<!-- Archivo CSS no encontrado: ' . $this->filenameCSS . ' -->' . PHP_EOL;
			}
			// Actualiza para no repetir inclusión de estilos
			$this->show_styles = false;
		}

		return $salida;
	}

	/**
	 * Previene el uso de los estilos incluidos en esta librería.
	 * Puede usarse cuando se define una hoja de estilos propia para su uso.
	 */
	public function ignoreLocalStyles() {
		$this->show_styles = false;
	}
}
