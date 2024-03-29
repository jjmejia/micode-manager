<?php
/**
 * Configuración de módulos PHP.
 *
 * @micode-uses vendor-parsedown Markdown Parser, tomado de https://github.com/erusev/parsedown/. Usado para generar
 *      un texto HTML debidamente formateado. Si no existe esta librería, genera un texto HTML de formato limitado.
 * @micode-uses miframe-inifiles
 * @author John Mejia
 * @since Abril 2022
 */

namespace miFrame\Manager;

include_once __DIR__ . '/../miframe/file/serialize.php'; // Serializar archivos de documentación

class phpManager extends \miFrame\Manager\Shared\MiBaseManager {

	private $pathCache = '';

	public function __construct() {
		$this->type = 'php';
		// Path para archivos caché
		$this->pathCache = miframe_temp_dir('cache-docs', true);
	}

	/**
	 * Inicializa interprete de documentación.
	 * Intenta cargar la librería "vendor/parser/parsedown" (tomado de https://github.com/erusev/parsedown/) para
	 * interpretar el texto como si fuera "Markdown" y generar así un texto HTML equivalente. Si no existe esta librería,
	 *
	 */
	private function initialize_doc() {

		if ($this->doc !== false) { return; }

		$this->doc = new \miFrame\Utils\DocSimple\DocSimpleHTML();

		// Define opciones para serialización de archivos
		$this->doc->serializeFunction = array($this, 'serializeDocumentation');
		$this->doc->unserializeFunction = array($this, 'unserializeDocumentation');

		// Función para mostrar @micode-uses con enlace

		// @micode-uses ... siempre se guarda como arreglo
		$this->doc->setTagFunEval('micode-uses', function (&$bloquedoc, $linea) {
			$arreglo = explode(' ', $linea . ' ', 2);
			$arreglo[0] = strtolower(trim($arreglo[0]));
			$arreglo[1] = trim($arreglo[1]);
			if (!is_array($bloquedoc)) { $bloquedoc = array(); }
			$bloquedoc[$arreglo[0]] = $arreglo[1];
		});

		$this->doc->setTagFunHTML('micode-uses', function ($main, $clickable) {
			foreach ($main as $modulo => $info) {
				if ($this->doc !== false) {
					if ($modulo != '' && $clickable) {
						// Recupera información en miFrame
						$infomodulo = '';
						$filename = $this->doc->filename();
						$fileuses = miframe_path(dirname($filename), '..', $modulo . miframe_extension($filename));
						if (file_exists($fileuses)) {
							$summary = $this->doc->getSummary($fileuses);
							$infomodulo = trim($summary['summary'] . "\n\n" . $infomodulo);
						}
						$tipo = 'php'; // $_REQUEST['type']
						$main[$modulo] = $this->doc->parserLink($modulo, $modulo, 'module');
						if ($infomodulo != '') {
							$main[$modulo] .= $this->doc->parserText($infomodulo);
						}
					}
					else {
						$main[$modulo] = '<b>' . htmlspecialchars(strtolower($modulo)) . '</b>';
						if ($info != '') {
							$main[$modulo] .= ' ' . $this->doc->parserText($info);
						}
					}
				}
			}
			return '<b>' . miframe_text('Requisitos') . ':</b>' .
					'<ul style="margin-left:20px"><li>' . implode('</li><li>', $main) . '</li></ul>';
		});

		// Función para realizar Parser
		$parser = miframe_class_load('\Parsedown');
		if ($parser !== false) {
			// Escape HTML even in trusted input
			$parser->setMarkupEscaped(true);
			$this->doc->parserTextFunction = array($parser, 'text');
		}
	}

	/**
	 * Acción a seguir cuando se copian los módulos a un proyecto ubicado en la
	 * copia de trabajo (dentro de "miCode/projects")
	 */
	protected function exportWorkCopyLocal(string $module, string $src, string $dest) {

		// NOTA: El DOCUMENT_ROOT de la aplicación miCode puede no ser el mismo del proyecto.
		$contenido = '<?php' . PHP_EOL .
			'// MICODE/REPOSITORY ' . $module . PHP_EOL .
			'// ' . miframe_text('Importante! No modifique este archivo. Creado sólo para esta copia de trabajo.') . PHP_EOL .
			'// ' . miframe_text('Será remplazado por el contenido original al generar la copia de distribución/producción.') . PHP_EOL .
			'// ' . miframe_text('Creado en') . ': ' . date('Y-m-d H:i:s') . PHP_EOL .
			'include_once \'' . str_replace('\\', '/', $src) . '\';' . PHP_EOL;

		// echo "COPIA PHPMANAGER: $src a $dest<hr>"; $retornar = true;

		return @file_put_contents($dest, $contenido);
	}

	/**
	 * Acción a seguir cuando se copian los módulos a un paquete para distribución
	 */
	public function exportDistCopy() {

	}

	/**
	 * @param mixed $config Objeto del tipo EditConfig
	 */
	public function helpersConfig(mixed &$config) {
		// Constantes a usar para PHP
		$config->addHelper('PHP_DATETIMEZONE', date_default_timezone_get());
		$config->addHelper('PHP_CHARSET', ini_get('default_charset'));
	}

	/**
	 * Genera una instancia de documentacion.
	 * Consulte la clase miFrame\Utils\DocSimple para mayor información.
	 * Los items de documentación que debe retornar son:
	 * - description (alias de "summary"): Descripción del script.
	 * - author
	 * - since: Usualmente, fecha o versión inicial.
	 * - php-namespaces
	 * - uses: Módulos de los que se tiene alguna dependencia.
	 *
	 * @param string $filename Nombre del archivo.
	 * @return array Arreglo con los items de documentación requeridos.
	 */
	public function getSummary(string $filename) {

		$sumario = array();
		$this->initialize_doc();
		if ($this->doc !== false) {
			$documento = $this->doc->getDocumentation($filename);
			$sumario =& $documento['main'];
		}

		// Evalua clases y namespaces
		$namespace = '';
		$path_modulos = strtolower(micode_modules_repository_path());
		$len = strlen($path_modulos);
		if (strtolower(substr($filename, 0, $len)) == $path_modulos) {
			$filename = substr($filename, $len + 1);
		}
		foreach ($documento['docs'] as $k => $info) {
			if ($info['type'] == 'namespace') {
				$namespace = trim($info['name']);
				if ($namespace != '') {
					$namespace .=  '\\';
				}
			}
			elseif ($info['type'] == 'class' || $info['type'] == 'trait') {
				// Hay al menos una clase y un namespace asociados
				$sumario['php-namespaces'][$namespace . $info['name']] = $filename;
			}
		}
		// Dependencias (micode-uses)
		if (isset($sumario['micode-uses']) && count($sumario['micode-uses']) > 0) {
			$sumario['uses'] = array_keys($sumario['micode-uses']);
		}

		// "summary" es un alias para el valor de "description" requerido
		// (la description en la documentación estándar es mucho más extensa).
		$sumario['description'] = $sumario['summary'];

		return $this->evalSummaryItems($sumario, $filename);
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

		$this->initialize_doc();
		if ($this->doc !== false) {
			return $this->doc->getDocumentation($filename, $search_function);
		}

		return false;
	}

	/**
	 * Retorna la documentación encontrada en formato HTML.
	 * Consulte la clase miFrame\Utils\DocSimple para mayor información.
	 *
	 * @param string $filename
	 * @param bool $clickable TRUE para hacer el documento navegable.
	 * @param bool $show_errors TRUE para incluir listado de errores encontrados. FALSE los omite.
	 * @param bool $with_styles TRUE para incluir estilos css (sólo lo hace la primera vez que se invoca esta función).
	 *             FALSE no los incluye.
	 * @return string Texto HTML.
	 */
	public function getDocumentationHTML(string $filename, bool $clickable = false, bool $show_errors = true, bool $with_styles = true) {

		$this->initialize_doc();
		if ($this->doc !== false) {
			$this->doc->clickable = $clickable;
			$this->doc->showErrors = $show_errors;
			return $this->doc->render($filename);
		}
	}

	public function serializeDocumentation($filename, $data) {

		$resultado = false;

		if ($this->pathCache != ''
			&& is_dir($this->pathCache)
			// && isset($data['file'])
			// && function_exists('miframe_serialize')
			) {
			// Adiciona control para actualizar caché si se modifica este archivo
			// $data['docmtime'] = filemtime(__FILE__);
			$filecache = $this->pathCache . '/docsimple-' . md5($filename);
			$resultado = miframe_serialize($filecache, $data);
		}

		return $resultado;
	}

	public function unserializeDocumentation($filename) {

		$info = false;

		if ($this->pathCache != ''
			&& is_dir($this->pathCache)
			// && function_exists('miframe_unserialize')
			) {
			// $encache es un arreglo con los datos del arreglo de documentacion
			$filecache = $this->pathCache . '/docsimple-' . md5($filename);
			// El archivo en disco debe tener fecha mayor o a la del original
			if (file_exists($filecache)
				&& filemtime($filecache) > filemtime($filename)
				) {
				// Usar miframe_data_call()?
				$info = miframe_unserialize($filecache);
			}
		}

		return $info;
	}
}