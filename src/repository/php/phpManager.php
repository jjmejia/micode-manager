<?php
/**
 * Configuraciòn de módulos PHP.
 *
 * Escrito por John Mejia (C) Abril 2022.
 */

namespace miFrame;

include_once MIFRAME_SYSMODULES_PATH . '/php/modules/utils/docsimple.php';

class phpManager { // pendiente definir como implementacion de interface miProjectManager

	private $infoerror = '';
	private $doc = false;

	public function __construct() {

		$this->doc = new \miFrame\DocSimple();
	}

	/**
	 * Título/Descripción a mostrar en el administrador
	 */
	public function Title() {
		return 'PHP Script';
	}
	/**
	 * Retorna la extensión asociada a este tipo de archivos
	 */
	public function Extension() {
		return '.php';
	}

	/**
	 * Atributos de configuración adicionales para proyectos de este tipo
	 */
	public function configDefaults() {

	}

	/**
	 * Acción a seguir cuando se copian los módulos a un proyecto ubicado en la
	 * copia de trabajo (dentro de "miCode/projects")
	 */
	public function exportWorkCopy(string $module, string $src, string $dest) {

		$retornar = false;

		if (file_exists($src)) {
			$destino_base = dirname($dest);
			if (!is_dir($destino_base)) {
				@mkdir($destino_base, 0777, true);
			}
			if (!is_dir($destino_base)) {
				$this->infoerror = 'Módulo ' . $module . ': No pudo crear directorio ' . $destino_base;
			}
			else {
				// No copia el archivo como tal sino que crea un acceso al original
				$contenido = '<?php' .
					PHP_EOL .
					"include_once '$src';" .
					PHP_EOL .
					'// No modifique este archivo. Creado sólo para esta copia de trabajo.' .
					PHP_EOL;
				$retornar = @file_put_contents($dest, $contenido);
				if (!$retornar) {
					$this->infoerror = 'Módulo ' . $module . ': No pudo crear archivo destino ' . $dest;
				}
			}
		}
		else {
			$this->infoerror = 'Módulo ' . $module . ': No existe archivo origen ' . $src;
		}

		return $retornar;
	}

	/**
	 * Acción a seguir cuando se copian los módulos a un paquete para distribución
	 */
	public function exportDistCopy() {

	}

	/**
	 * Retorna último error generado
	 */
	public function getError() {
		return $this->infoerror;
	}

	/**
	 * Genera una instancia de documentacion
	 */
	public function getSummary(string $filename, mixed $required = array()) {
		return $this->doc->getSummary($filename, $required);
	}

	/**
	 * Genera una instancia de documentacion completa
	 */
	public function getDocumentation(string $filename) {
		return $this->doc->getDocumentation($filename);
	}

	public function getDocumentationHTML(string $filename, bool $clickable = false, bool $with_styles = true) {
		return $this->doc->getDocumentationHTML($filename, $clickable, $with_styles);
	}

}