<?php
/**
 * Definición de la interface a usar al crear Managers.
 *
 * @author John Mejia
 * @since Junio 2022
 */

namespace miFrame\Manager\Shared;

class MiBaseManager {

	private $last_error = '';

	protected $doc = false;
	protected $type = '';

	// public function __construct() { ...	}

	/**
	 * Atributos de configuración adicionales para proyectos de este tipo
	 */
	/*public function configDefaults() {

	}*/

	/**
	 * Inicializa interprete de documentación.
	 * Intenta cargar la librería "vendor/parser/parsedown" (tomado de https://github.com/erusev/parsedown/) para
	 * interpretar el texto como si fuera "Markdown" y generar así un texto HTML equivalente. Si no existe esta librería,
	 *
	 */
	private function initialize_doc() {

		if ($this->doc !== false) { return; }
	}

	/**
	 * Acción particular al copiar un archivo local a la copia de trabajo.
	 * Esta función se invoca luego de validar que los valores de origen y destino son correctos,
	 * por tanto no requieren ser validados nuevamente.
	 * Si el archivo destino ya existe, no realiza nueva copia si son identicos.
	 * Esta es la función a redefinir en cada clase Manager personalizada.
	 */
	protected function exportWorkCopyLocal(string $module, string $src, string $dest) {

		$resultado = false;
		if (file_exists($dest) && sha1_file($src) === sha1_file($dest)) {
			$resultado = true;
		}
		else {
			$resultado = @copy($src, $dest);
		}

		return $resultado;
	}

	/**
	 * Acción a seguir cuando se copian los módulos a un proyecto ubicado en la
	 * copia de trabajo (dentro de "miCode/projects").
	 * Realiza las validaciones básicas de archivo de origen y directorio destino, así como
	 * del resultado de la copia realizada por $this->exportWorkCopyLocal().
	 */
	public function exportWorkCopy(string $module, string $src, string $dest) {

		$retornar = false;

		if (file_exists($src)) {
			// Estandariza separadores de directorio
			$src = miframe_path($src);
			$dest = miframe_path($dest);
			// Valida destino
			$destino_base = dirname($dest);

			if (!miframe_mkdir($destino_base)) {
				$this->last_error = miframe_text('Módulo $1: No pudo crear directorio "$2"', $module, $destino_base);
			}
			else {
				// Realiza copia completa del archivo.
				$retornar = $this->exportWorkCopyLocal($module, $src, $dest);
				if (!$retornar) {
					$this->last_error = miframe_text('Módulo $1: No pudo copiar "$2" a "$3"', $module, $src, $dest);
				}
			}
		}
		else {
			$this->last_error = miframe_text('Módulo $1: No existe archivo origen "$2"', $module, $src);
		}

		return $retornar;
	}

	public function helpersConfig(mixed &$config) {
	}

	/**
	 * Acción a seguir cuando se copian los módulos a un paquete para distribución
	 */
	public function exportDistCopy() {

	}

	/**
	 * Retorna último error generado
	 */
	public function getLastError() {
		return $this->last_error;
	}

	/**
	 * Genera una instancia de documentacion
	 * Consulte la clase miFrame\Utils\DocSimple para mayor información.
	 * Los items de documentación que debe retornar son:
	 * - description (alias de "summary"): Descripción del script.
	 * - author
	 * - since: Usualmente, fecha o versión inicial.
	 * - php-namespaces
	 * - uses: Módulos de los que se tiene alguna dependencia.
	 *
	 * NOTA: Use siempre $this->evalSummaryItems() al retornar para garantizar estos items.
	 *
	 * @param string $filename Nombre del archivo.
	 * @return array Arreglo con los items de documentación.
	 */
	public function getSummary(string $filename) {

		$data = array();

		// Cada manager procesa datos y genera su arreglo de resultados

		return $this->evalSummaryItems($data, $filename);
	}

	protected function evalSummaryItems(array $data, string $filename) {

		$required = array(
			'description' => '',
			'author' => '',
			'since' => '',
			'php-namespaces' => array(),
			'uses' => array()
			);

		// @param array $required Elementos mínimos a incluir en el arreglo de respuesta.

		// Garantiza existencia de valores minimos
		foreach ($required as $llave => $inicial) {
			if (array_key_exists($llave, $data)) {
				$required[$llave] = $data[$llave];
			}
		}

		if ($required['since'] == '') {
			// Asume el de la fecha de creación del archivo
			$required['since'] = miframe_filecreationdate($filename) . ' (A)';
		}

		return $required;
	}

	/**
	 * Genera una instancia de documentacion completa
	 * Consulte la clase miFrame\Utils\DocSimple para mayor información.
	 *
	 * @param string $filename Nombre del script del que se va a recuperar la documentación.
	 * @param string $search_function Nombre de la función a buscar.
	 * @return array Arreglo con todos los documentos recuperados.
	 */
	public function getDocumentation(string $filename, string $search_function = '') {

		return array();
	}

	/**
	 * Retorna la documentación encontrada en formato HTML.
	 * Consulte la clase miFrame\Utils\DocSimple para mayor información.
	 *
	 * @param string $filename Nombre del script del que se va a recuperar la documentación.
	 * @param bool $clickable TRUE para hacer el documento navegable.
	 * @param bool $show_errors TRUE para incluir listado de errores encontrados. FALSE los omite.
	 * @param bool $with_styles TRUE para incluir estilos css (sólo lo hace la primera vez que se invoca esta función).
	 *             FALSE no los incluye.
	 * @return string Texto HTML.
	 */
	public function getDocumentationHTML(string $filename, bool $clickable = false, bool $show_errors = true, bool $with_styles = true) {

		return '';
	}
}