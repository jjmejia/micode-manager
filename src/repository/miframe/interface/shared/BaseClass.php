<?php
/**
 * Clase base usada como soporte para la creación de clases similares.
 * Las características que esta clase aporta son:
 * - Manejo de arreglo de valores.
 * - Manejo de directorio y archivo principal.
 * - Permite manejo dinámico de métodos y propiedades.
 *
 * @author John Mejia
 * @since Mayo 2022
 */

namespace miFrame\Interface\Shared;

/**
 * Propiedades públicas:
 * - $debug: boolean. TRUE para incluir mensajes de depuración.
 */
class BaseClass {

	private $include_fun = false;
	private $secuencia = 0;

	protected $path_files = '';
	protected $color_debug = '';

	public $debug = false;

	/**
	 * Inicializa valores asociados a archivo y URI.
	 * Los valores de archivo y directorio base son tomados del script que invoca la función __construct() de la
	 * clase hija.
	 */
	public function initialize() {

		$script = miframe_server_get('SCRIPT_FILENAME');
		// El path para ubicar los script es por defecto el mismo del script principal
		$this->setPathFiles(dirname($script));
	}

	/**
	 * Imprime mensaje en pantalla solamente si $this->debug = true.
	 *
	 * @param string $message Mensaje a imprimir, permite texto en HTML
	 * @param string $footnote Mensaje adicional
	 */
	protected function printDebug(string $text, string $footnote = '') {

		$text = trim($text);
		if ($text != '') {
			// Marca en modo debug
			if ($this->debug) {
				$this->secuencia ++;
				$salida = '<div style="font-size:14px;padding:10px;margin:0;background:' . $this->color_debug . ';color:#fff;line-height:1">' .
						'[' . $this->secuencia . '] <b>DEBUG ' . get_class($this) . '</b> - ' .
						$text;
				if ($footnote != '') {
					$salida .=	PHP_EOL .
						'<div style="font-size:12px;padding:10px 0 5px 0;color:yellow">' .
						$footnote .
						'</div>';
				}
				$salida .= '</div>';
				if (!miframe_is_web()) {
					$salida = PHP_EOL . '---' . PHP_EOL .
						strip_tags($salida) .
						PHP_EOL . '---' . PHP_EOL;
				}
				echo $salida;
			}
			// Adiciona al log de errores de PHP
			error_log($text);
		}
	}

	public function setIncludeFun(callable $fun) {

		$this->include_fun = $fun;
	}

	protected function include(string $filename, string $text = '', string $footnote = '', bool $private = false) {

		if (!file_exists($filename)) {
			return false;
		}

		// Reporta uso en modo debug (lo hace antes del include en caso que contenga un "exit")
		$this->printDebug($text, $footnote);

		$include_fun = false;
		if ($private) {
			// La definición "static" aisla la función para no heredar uso de "this"
			$include_fun = static function ($filename) {
				include_once $filename;
			};
		}
		elseif ($this->include_fun !== false) {
			// Ejecuta la función definida por el usuario
			$include_fun =& $this->include_fun;
		}
		else {
			// En este escenario, puede accederse a este objeto en $filename usando $this
			$include_fun = function ($filename) {
				include_once $filename;
			};
		}

		call_user_func($include_fun, $filename);

		return true;
	}

	/**
	 * Asigna directorio a usar para ubicar los scripts.
	 *
	 * @param string $path Path.
	 */
	public function setPathFiles(string $path) {

		if (is_dir($path)) {
			// Recibe el path completo
			$this->path_files = realpath($path);
		}
		else {
			// Recibe el path asociado a la posición actual
			$path = miframe_path(getcwd(), $path);
			// $path = miframe_path($this->path_files, $path);
			if (is_dir($path)) {
				$this->path_files = realpath($path);
			}
		}
	}

	public function getPathFiles() {
		return $this->path_files;
	}
}