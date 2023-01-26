<?php
/**
 * Librería para revisión de librerías externas requeridas por miCode.
 * Esta librería debe ser 100% autonoma para poder ejecutarse correctamente
 * dado que se ejecuta (idealmente) al inicio de todo el proceso.
 *
 * @author John Mejia
 * @since Noviembre 2022.
 */

namespace miFrame\Check;

// Reconstruir php-namespaces.ini <-- OK.
// Por facilidad al ejecutar autoload, dejar las rutas asociadas a DOCUMENT_ROOT?
// Validar al reconstruir que exista un valor de referencia (puede ser la ruta a este check o al index de "admin" o al directorio que contiene "AdminModules"?)
// Ej. @check=xxxx
// Esto para reducir dependencia del autoload a constantes externas.

if (count($_REQUEST) <= 0 || isset($_REQUEST['depok'])) {
	$check = new EvalMiCode();
	$check->checkExternals();
	$check->checkMiCode();
}

/**
 * Clase para evaluar instalación actual de "miCode".
 */
class EvalMiCode {

	private $dirbase = '';
	private $diradmin = '';
	private $app_name = '';
	private $mensajes = array();

	public function __construct() {

		$this->dirbase = realpath(__DIR__ . '/../../..');
		$this->diradmin = realpath($this->dirbase . '/admin');
		$this->app_name = 'micode-admin';
		$this->checkLocalPath();
	}

	private function checkLocalPath() {

		$inifile = str_replace(DIRECTORY_SEPARATOR, '/', $this->dirbase) . '/projects/micode-admin.path';
		$local = '';
		if (file_exists($inifile)) {
			// Valida que apunte a un directorio valido
			$contenido = str_replace('..', '_', file_get_contents($inifile));
			$local = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $contenido;
			if ($contenido == '' || realpath($local) !== $this->diradmin) {
				$local = '';
			}
		}
		if ($local == '') {
			// Crea archivo con el path actual
			$root = realpath($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR;
			$len = strlen($root);
			if (substr($this->diradmin, 0, $len) == $root) {
				$local = str_replace('/', DIRECTORY_SEPARATOR, substr($this->diradmin, $len));
				$dirname = dirname($inifile);
				if (!is_dir($dirname)) { @mkdir($dirname); }
				$contenido = '';
				if (!@file_put_contents($inifile, $local)) {
					$contenido = "<p>No pudo crear archivo requerido ($inifile).</p>" .
						"<p>Compruebe que el directorio existe y que se tienen los permisos necesarios para crear directorios/archivos.</p>";
				}
				else {
					$this->mensajes['ok'] = "Creado archivo requerido por el sistema ($inifile)";
					$contenido = "<p>Referencia asociada al path <b>{$local}</b></p>";
				}
				// Salida a pantalla
				$this->openHTML('miCode - Path local micode-admin');
				echo $contenido;
				$this->closeHTML();
			}
			exit;
		}
	}

	private function checkInstalledIni(string $inifile_modulos, string $inifile_tpl) {
		// Valida que los módulos mínimos requeridos estén instalados
		$retornar = true;
		$install_info = parse_ini_file($inifile_modulos, true, INI_SCANNER_TYPED);
		$startup_info = parse_ini_file($inifile_tpl, true, INI_SCANNER_TYPED);
		$modulos_instalados = array_keys($install_info);
		$modulos_requeridos = $startup_info['modules'];

		foreach ($modulos_requeridos as $modulo) {
			if (!in_array($modulo, $modulos_instalados)) {
				$retornar = false;
				break;
			}
		}

		return $retornar;
	}

	public function checkMiCode() {

		// Valida que exista php-namespaces.ini y modules-installed.ini
		$inifile_modulos = $this->dirbase . '/admin/micode.private/modules-installed.ini';
		$inifile_namespaces = $this->diradmin . '/micode/config/php-namespaces.ini';
		// Carga definiciones, incluidas en uno de los templates del sistema
		$filename = $this->dirbase . '/repository/templates/startup/micode-admin/tpl-config.ini';
		// echo "!$inifile_modulos = " . file_exists($inifile_modulos) . " || !$inifile_namespaces = " . file_exists($inifile_namespaces) . "<hr>"; exit;
		// Procesa si no existe alguno de los archivos indicados o si el archivo de instalados es mas antiguo
		// que  el archivo guia (template).
		if (!file_exists($inifile_modulos)
			|| !file_exists($inifile_namespaces)
			|| (file_exists($inifile_modulos) && filemtime($inifile_modulos) < filemtime($filename))
			|| !$this->checkInstalledIni($inifile_modulos, $filename)
			) {

			$startup_info = parse_ini_file($filename, true, INI_SCANNER_TYPED);

			// Si no se han creado los archivos de arranque (micode/miframe/xxx) lo que se detecta porque no
			// existe "modules-installed.ini", debe apuntar directamente al repositorio

			// Directorio para ubicar los módulos asociados al proyecto
			define('MIFRAME_LOCALMODULES_PATH', $this->dirbase . '/repository');

			// Carga el resto de las librerías
			include_once $this->diradmin . '/micode/initialize.php';
			include_once $this->diradmin . '/lib/modules/admin.php';
			include_once $this->diradmin . '/lib/class/AdminModules.php';

			// Incluye manualmente librerá de documentación en caso que no se hayan creado las relaciones
			// entre paths y clases (php-namespaces.ini)
			include_once MIFRAME_LOCALMODULES_PATH . '/miframe/utils/DocSimple.php';


			// Recupera solo información del proyecto local
			// $data_proyecto = micode_modules_proyecto_ini($this->app_name, $data_repo);

			$m = new \miFrame\Local\AdminModules(true);
			// Obtiene modulos disponibles (esto requiere que existan los archivos de referencia en "micode")
			// Usar una lista predefinida para crear los archivos de arranque! Validar que el listado de modulos
			// instalados tenga una fecha > a la de dicho archivo para que refresque si es necesario!

			// $listado = $m->getAllModules('', $startup_info['modules']);

			$startup = 'micode-admin';

			$datamodules = $m->exportRemoteFiles($this->app_name, $startup_info['modules'], $startup);

			$total = 0;
			$mensajes_ok = array();
			foreach ($datamodules['modules'] as $modulo => $subtotal) {
				$total += $subtotal;
			}
			if (count($datamodules['modules']) > 1) {
				$mensajes_ok[] = miframe_text('Copiados en total $1 archivos durante esta actualización', $total);
			}
			if ($datamodules['ini-installed']) {
				$mensajes_ok[] = $datamodules['result'];
			}
			else {
				$this->mensajes['error'] =  $datamodules['result'] .
					'<br \>' .
					miframe_text('La copia de archivos requeridos se suspende hasta que sea solucionado el problema encontrado.');
			}

			if (count($mensajes_ok) > 0) {
				$this->mensajes['ok'] = implode('<br \>', $mensajes_ok);
			}

			$this->checkMiCodeShow($datamodules['modules']);
			exit;
		}
	}

	public function checkExternals() {

		// echo "VALIDANDO EXTERNALS<hr>";

		// Valida primero se hayan definido los repositorios básicos: "miframe" y "vendor"
		$datarepos = array();
		$repos_pendientes = array();
		// Captura listado de archivos básicos
		$filename = $this->dirbase . '/data/repositories.ini';
		if (file_exists($filename)) {
			$datarepos = parse_ini_file($filename, true, INI_SCANNER_TYPED);
		}
		// Adiciona repositorio incluido
		$datarepos['miframe'] = array('path' => $this->dirbase . '/repository/miframe');
		// Captura listado de archivos básicos
		$filename = $this->dirbase . '/data/lib-externals.ini';
		if (!file_exists($filename)) {
			// exit('ERROR/CHECK: No se encuentra archivo ' . str_replace("\\", '/', $filename));
			// Nada qué hacer
			return;
		}
		$dataini = parse_ini_file($filename, true, INI_SCANNER_TYPED);
		ksort($dataini);
		$clase = '';
		$locales = array();
		$total_pendientes = 0;
		$root = $_SERVER['DOCUMENT_ROOT'] . '/';

		foreach ($dataini as $modulo => $info) {
			$arreglo = explode('/', $modulo, 2);
			if (!isset($arreglo[1])) { continue; }
			$arreglo[0] = trim($arreglo[0]);
			$arreglo[1] = trim($arreglo[1]);
			// El primer elemento es la clase
			if ($arreglo[0] != $clase) {
				$clase = $arreglo[0];
				if (!isset($datarepos[$arreglo[0]])) {
					$inforepo = '';
					if (isset($info[$clase . '-info'])) {
						$inforepo = trim($info[$clase . '-info']);
					}
					if (!isset($repos_pendientes[$clase]) || $repos_pendientes[$clase]['description'] != $inforepo) {
						$repos_pendientes[$clase] = array(
							'description' => $inforepo,
							'ctl' => md5($clase),
							'pendiente' => true
							);
					}
					continue;
				}
				else {
					// $filelocal = $this->dirbase . '/repository/data/lib-'. $clase . '.ini';
					$filelocal = $this->getRepositoriesIni($datarepos[$clase]['path']);
					if (file_exists($filelocal)) {
						$locales[$clase] = parse_ini_file($filelocal, true, INI_SCANNER_TYPED);
					}
				}
				// echo "<pre>"; print_r($locales[$clase]); exit;
			}
			$pendiente = true;
			$info['dirbase'] = '';
			if (isset($locales[$clase][$arreglo[1]])) {
				// Ya fue declarado, lo remueve de los pendientes
				// pero valida primero la ubicación actual
				$infolocal = $locales[$clase][$arreglo[1]];
				if (isset($infolocal['dirbase'])) {
					$info['dirbase'] = trim($infolocal['dirbase']);
					if ($info['dirbase'] != '' && is_dir($root . $datarepos[$clase]['path'] . DIRECTORY_SEPARATOR . $info['dirbase'])) {
						$pendiente = false;
					}
				}
			}
			if ($pendiente) {
				$total_pendientes ++;
				$locales[$clase][$arreglo[1]] = $info;
			}
			$dataini[$modulo]['ctl'] = 'ctl' . md5($modulo);
			$dataini[$modulo]['pendiente'] = $pendiente;
			$dataini[$modulo]['clase'] = $clase;
			$dataini[$modulo]['modulo'] = $arreglo[1];
		}
		// Valida si falta configurar repositorios
		if (count($repos_pendientes) > 0) {
			// print_r($repos_pendientes); exit;
			$this->updateRepositories($datarepos, $repos_pendientes);
			$this->checkRepositoriesShow($datarepos, $repos_pendientes);
		}

		// echo "<pre>"; print_r($dataini); exit;
		// En $dataini quedan los locales pendientes por definir
		if ($total_pendientes > 0) {
			$this->updateExternals($dataini, $locales, $datarepos);
			$this->checkExternalsShow($dataini, $locales, $datarepos);
		}
	}

	private function updateRepositories(array &$datarepos, array &$repos_pendientes) {

		$guardar = false;

		if (isset($_REQUEST['depok'])) {
			// echo "<pre>"; print_r($_REQUEST); echo "</pre>"; exit;
			// NOTA: No generaliza $lroot en minúsculas en caso de ejecutar en Linux, donde los
			// path si se afectan según sean en mayúsculas o minúsculas.4
			$lroot = str_replace("\\", '/', $_SERVER['DOCUMENT_ROOT']) . '/';
			$len = strlen($lroot);
			foreach ($repos_pendientes as $clase => $info) {
				if (!$info['pendiente']) { continue; }
				$valor = '';
				if (isset($_REQUEST[$info['ctl']])) {
					$valor = str_replace(array('..', '<', '#'), '_', trim($_REQUEST[$info['ctl']]));
				}
				// Valida que $valor contenga DOCUMENT_ROOT
				if ($valor != '') {
					$repos_pendientes[$clase]['valor'] = $valor;
					$valor = str_replace("\\", '/', $valor);
					$lvalor = strtolower($valor);
					// El directorio recibido DEBE estar contenido en $_SERVER['DOCUMENT_ROOT']
					if (substr($lvalor, 0, $len) === strtolower($lroot)) {
						$valor = substr($valor, $len);
					}
					if ($valor == '' || !is_dir($lroot . $valor)) {
						$repos_pendientes[$modulo]['error'] = 'Directorio no valido';
					}
					else {
						$repos_pendientes[$clase]['pendiente'] = false;
						$datarepos[$clase]['description'] = $repos_pendientes[$clase]['description'];
						$datarepos[$clase]['path'] = $valor;
						$guardar = true;
					}
				}
			}

			// echo "<pre>"; print_r($repos_pendientes); echo "</pre><hr>";
			// echo "<pre>"; print_r($datarepos); echo "</pre><hr>"; exit;

			// Valida si debe guardar archivo
			if ($guardar) {

				include_once $this->dirbase . '/repository/miframe/file/inifiles.php';

				$filename = $this->dirbase . '/data/repositories.ini';
				ksort($datarepos);
				if (miframe_inifiles_save_data($filename, $datarepos)) {
					$this->mensajes['ok'] = 'Listado de repositorios actualizados con éxito: ' . $clase;
				}
				else {
					$this->mensajes['error'] = 'No pudo actualizar archivo ' . $filename;
				}
			}
		}

		// TRUE si no existe mensaje de error
		return (!isset($this->mensajes['error']));
	}

	private function checkRepositoriesShow(array $datarepos, array $repos_pendientes) {

		$this->openHTML('miCode - Dependencias');

		echo "<p>Algunos directorios son requeridos para poder continuar.</p>".
			"Tenga presente que todos los directorios indicados <b>deben</b> ser subdirectorios de <i>{$_SERVER['DOCUMENT_ROOT']}</i>.</p>" .
			"<form method=\"POST\"><ul>";

		$total_pendientes = 0;

		ksort($repos_pendientes);

		foreach ($repos_pendientes as $clase => $info) {
			echo "<li><b>{$clase}</b>";
			if (isset($info['description']) && trim($info['description']) != '') {
				$info['description'] = trim($info['description']);
				echo "<p>{$info['description']}</p>";
			}
			$valor = '';
			if (isset($datarepos[$clase])) {
				$valor = htmlspecialchars(trim($datarepos[$clase]['path']));
			}
			elseif (isset($info['valor'])) {
				$valor = htmlspecialchars(trim($info['valor']));
			}
			echo "<p>Directorio: ";
			if ($info['pendiente']) {
				$total_pendientes ++;
				$mensaje = 'Valor requerido';
				if (isset($info['error']) && $info['error'] != '') {
					$mensaje = trim($info['error']);
				}
				echo "<input type=\"text\" size=\"50\" value=\"{$valor}\" name=\"{$info['ctl']}\"> <b class=\"check-error\">&lt; {$mensaje}</b>";
			}
			else {
				echo "<b class=\"check-ok\">{$valor}</b>";
			}
			echo "</p></li>";
		}
		echo "</ul>";

		if ($total_pendientes > 0) {
			echo "<p style=\"margin-top:20px\"><input type=\"submit\" name=\"depok\" value=\"Actualizar repositorios\" style=\"padding:5px 10px\"></p>";
		}
		echo "</form>";

		$this->closeHTML();
	}

	private function updateExternals(array &$dataini, array &$locales, array $datarepos) {

		$guardar = false;

		if (isset($_REQUEST['depok'])) {
			// echo "<pre>"; print_r($_REQUEST); echo "</pre>";
			// NOTA: No generaliza $lroot en minúsculas en caso de ejecutar en Linux, donde los
			// path si se afectan según sean en mayúsculas o minúsculas.
			$lroot = str_replace("\\", '/', $_SERVER['DOCUMENT_ROOT']) . '/';
			$len = strlen($lroot);
			foreach ($dataini as $modulo => $info) {
				if (!$info['pendiente']) { continue; }
				$valor = '';
				if (isset($_REQUEST[$info['ctl']])) {
					$valor = str_replace(array('..', '<', '#'), '_', trim($_REQUEST[$info['ctl']]));
				}
				// Valida que $valor contenga DOCUMENT_ROOT
				if ($valor != '') {
					$dataini[$modulo]['valor'] = $valor;
					$valor = str_replace("\\", '/', $valor);
					$lvalor = strtolower($valor);
					// El directorio recibido DEBE estar contenido en $_SERVER['DOCUMENT_ROOT']
					if (substr($lvalor, 0, $len) === strtolower($lroot)) {
						$valor = substr($valor, $len);
						$lvalor = substr($lvalor, $len);
					}
					// Valida si contiene el directorio indicado para esta clase
					$lclase = str_replace("\\", '/', $datarepos[$info['clase']]['path']) . '/';
					$lenc = strlen($lclase);
					// El directorio recibido DEBE estar contenido en $_SERVER['DOCUMENT_ROOT']
					if (substr($lvalor, 0, $lenc) === strtolower($lclase)) {
						$valor = substr($valor, $lenc);
					}
					if ($valor == '' || !is_dir($lroot . $lclase . $valor)) {
						$dataini[$modulo]['error'] = 'Directorio no valido';
					}
					else {
						// Valida archivo dependencia
						$archivo = array_shift($info['require']);
						if (!file_exists($lroot . $lclase . $valor . '/' . $archivo)) {
							$dataini[$modulo]['error'] = "El archivo de referencia ({$archivo}) no fue encontrado";
						}
						else {
							$dataini[$modulo]['pendiente'] = false;
							$locales[$info['clase']][$info['modulo']]['dirbase'] = $valor;
							$guardar = true;
						}
					}
				}
			}

			// echo "<pre>"; print_r($dataini); echo "</pre><hr>";
			// echo "<pre>"; print_r($locales); echo "</pre><hr>"; exit;

			// Valida si debe guardar archivo
			if ($guardar) {

				include_once $this->dirbase . '/repository/miframe/file/inifiles.php';

				foreach ($locales as $clase => $infoclase) {
					$filename = $this->getRepositoriesIni($datarepos[$clase]['path']);
					ksort($infoclase);
					if (miframe_inifiles_save_data($filename, $infoclase)) {
						if (isset($this->mensajes['ok'])) {
							$this->mensajes['ok'] .= ', ' . $clase;
						}
						else {
							$this->mensajes['ok'] = 'Archivo(s) de repositorio actualizados con éxito: ' . $clase;
						}
						// return true;
					}
					else {
						$this->mensajes['error'] = 'No pudo actualizar archivo ' . $filename;
					}
				}
			}
		}

		// TRUE si no existe mensaje de error
		return (!isset($this->mensajes['error']));
	}

	private function checkExternalsShow(array $dataini, array $locales, array $datarepos) {

		$this->openHTML('miCode - Dependencias');

		echo "<p>Algunas librerías son requeridas para poder continuar.</p>".
			"<p>Descargue los scripts del repositorio indicado, instalelos en su equipo e indique la ruta del " .
			"archivo de referencia indicado en cada caso.<br />" .
			// "Tenga presente que todos los directorios indicados <b>deben</b> ser subdirectorios de <i>{$_SERVER['DOCUMENT_ROOT']}</i>.</p>" .
			"<form method=\"POST\"><ul>";

		$total_pendientes = 0;

		foreach ($dataini as $modulo => $info) {
			echo "<li><b>{$modulo}</b>";
			if (isset($info['description']) && trim($info['description']) != '') {
				$info['description'] = trim($info['description']);
				echo "<p>{$info['description']}</p>";
			}
			if (isset($info['repo']) && trim($info['repo']) != '') {
				$info['repo'] = trim($info['repo']);
				echo "<p>Repositorio: <a href=\"{$info['repo']}\" target=\"_blank\">{$info['repo']}</a></p>";
			}
			$valor = '';
			if (isset($locales[$info['clase']][$info['modulo']]['dirbase'])) {
				$valor = htmlspecialchars(trim($locales[$info['clase']][$info['modulo']]['dirbase']));
			}
			elseif (isset($info['valor'])) {
				$valor = htmlspecialchars(trim($info['valor']));
			}
			if (!is_array($info['require'])) {
				$info['require'] = explode("\n", str_replace("\r", '', $info['require']));
			}
			$archivo = array_shift($info['require']);
			echo "<p>Archivo de referencia: <i>{$archivo}</i></p>";
			echo "<p>Directorio base: ";
			if ($info['pendiente']) {
				$total_pendientes ++;
				$mensaje = 'Valor requerido';
				if (isset($info['error']) && $info['error'] != '') {
					$mensaje = trim($info['error']);
				}
				echo "<input type=\"text\" size=\"50\" value=\"{$valor}\" name=\"{$info['ctl']}\"> <b class=\"check-error\">&lt; {$mensaje}</b>";
				echo "<div><small>Debe ser un subdirectorio de <i>" . str_replace('/', DIRECTORY_SEPARATOR, $_SERVER['DOCUMENT_ROOT'] . '/' . $datarepos[$info['clase']]['path']) . "</i>";
				if (isset($info['help']) && $info['help'] != '') {
					echo "<br />{$info['help']}";
				}
				echo "</small></div>";
			}
			else {
				echo " <b class=\"check-ok\">{$valor}</b>";
			}
			echo "</p></li>";
		}
		echo "</ul>";

		if ($total_pendientes > 0) {
			echo "<p style=\"margin-top:20px\"><input type=\"submit\" name=\"depok\" value=\"Actualizar dependencias\" style=\"padding:5px 10px\"></p>";
		}
		echo "</form>";

		$this->closeHTML();
	}

	private function checkMiCodeShow(array $listado) {

		$this->openHTML('miCode - Módulos iniciales de proyecto');

		echo "<p>Los siguientes módulos han sido encontrados:</p><ul>";

		foreach ($listado as $modulo => $subtotal) {
			$mensajes = '';
			if ($subtotal > 1) {
				$mensajes = miframe_text('Módulo **$1** instalado con éxito ($2 archivos)', $modulo, $subtotal);
			}
			else {
				$mensajes = miframe_text('Módulo **$1** instalado con éxito (1 archivo)', $modulo);
			}
			echo "<li>$mensajes</li>";
		}

		echo "</ul>";

		$this->closeHTML();
	}

	private function openHTML(string $titulo) {

		include_once $this->dirbase . '/tests/lib/testfunctions.php';

		miframe_test_start($titulo);

		// Estilos propios
		echo "<style>" .
			"body { font-family:Segoe-UI,Tahoma; font-size:14px; }" .
			".msg-ok { border:1px solid darkgreen; color:darkgreen; padding:10px; margin:10px 0; } " .
			".msg-error { border:1px solid darkred; color:darkred; padding:10px; margin:10px 0; } " .
			".check-error { color:darkred; } " .
			".check-ok { color:darkgreen; } " .
			"input[type=text] { padding:5px;10px; } " .
			"</style>" . PHP_EOL;

		foreach ($this->mensajes as $tipo => $mensaje) {
			if ($tipo == 'ok' && !isset($this->mensajes['error'])) {
				$mensaje .= '<p>Recargue la página o <a href="">haga click aquí para continuar</a>.</p>';
			}
			echo "<div class=\"msg-{$tipo}\">{$mensaje}</div>";
		}

	}

	private function closeHTML() {

		echo "<div class=\"foot\">";
		echo "<b>miFrame</b> &copy; " .  date('Y');
		echo "</div></body></html>";
		exit;

	}

	private function getRepositoriesIni(string $local) {

		return $filename = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $local . DIRECTORY_SEPARATOR . 'micode-repository.ini';
	}
}