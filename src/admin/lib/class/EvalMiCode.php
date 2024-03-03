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

define('MIFRAME_EVAL_BASEDIR', MIFRAME_ROOT);

// Reconstruir php-namespaces.ini <-- OK.
// Por facilidad al ejecutar autoload, dejar las rutas asociadas a DOCUMENT_ROOT?
// Validar al reconstruir que exista un valor de referencia (puede ser la ruta a este check o al index de "admin" o al directorio que contiene "AdminModules"?)
// Ej. @check=xxxx
// Esto para reducir dependencia del autoload a constantes externas.

/**
 * Clase para evaluar instalación actual de "miCode".
 */
class EvalMiCode {

	private $app_name = 'micode-admin';
	private $local_path = '';
	private $mensajes = array();

	public function __construct() {

		$this->local_path = str_replace(DIRECTORY_SEPARATOR, '/', MIFRAME_DATA) . "/projects/{$this->app_name}.path";
		$this->checkLocalPath();
	}

	private function checkLocalPath() {

		$local = '';
		$mensaje = '';

		if (file_exists($this->local_path)) {
			// Valida que apunte a un directorio valido
			$local = str_replace('..', '_', file_get_contents($this->local_path));
			if ($local == '' || $local !== MIFRAME_EVAL_BASEDIR) {
				$local = '';
			}
		}
		if ($local == '') {
			// Crea archivo con el path actual
			$local = MIFRAME_EVAL_BASEDIR;
			$dirname = dirname($this->local_path);
			if (!miframe_mkdir($dirname)) {
				if (!@file_put_contents($this->local_path, $local)) {
					$mensaje .= "<p>No pudo crear archivo requerido ({$this->local_path}).</p>" .
						"<p>Compruebe que el directorio existe y que se tienen los permisos necesarios para crear directorios/archivos.</p>";
				}
				else {
					$this->mensajes['ok'] = "Creado archivo requerido por el sistema ({$this->local_path})";
					$mensaje .= "<p>Referencia asociada al path <b>{$local}</b></p>";
				}
			}
			else {
				$mensaje .= "<p>No pudo crear directorio {$dirname}</p>";
			}
		}
		if ($mensaje != '') {
			// Salida a pantalla
			$this->openHTML('miCode - Path local ' . $this->app_name);
			echo $mensaje;
			$this->closeHTML();
		}
	}

	private function checkInstalledIni(string $inifile_modulos, string $inifile_tpl) {
		// Valida que los módulos mínimos requeridos estén instalados
		$retornar = true;
		$install_info = parse_ini_file($inifile_modulos, true, INI_SCANNER_TYPED);
		$startup_info = parse_ini_file($inifile_tpl, true, INI_SCANNER_TYPED);
		// array_flip() se segura que se usen los modulos como llaves
		$modulos_instalados = array_flip(array_keys($install_info));
		$modulos_requeridos = $startup_info['modules'];

		foreach ($modulos_requeridos as $modulo) {
			if (!isset($modulos_instalados[$modulo])) {
				$retornar = false;
				break;
			}
		}

		return $retornar;
	}

	public function checkMiCode() {

		// Determina templates usados para configurar el administrador (por defecto "micode-admin")
		$startup = $this->app_name;
		// Determina directorio usados para instalar módulos (por defecto "micode")
		$app_modules = 'micode';

		// Obtiene datos del repo.ini
		$file_repo = $this->createRepoIni();

		// Recupera los datos registrados
		$repo_info = parse_ini_file($file_repo, true, INI_SCANNER_TYPED);
		if (isset($repo_info['startup']) && $repo_info['startup'] != '') {
			$startup = trim($repo_info['startup']);
		}
		if (isset($repo_info['app-modules']) && $repo_info['app-modules'] != '') {
			$app_modules = trim($repo_info['app-modules']);
		}

		// Valida que exista php-namespaces.ini y modules-installed.ini
		$inifile_namespaces = MIFRAME_EVAL_BASEDIR . "/{$app_modules}/config/php-namespaces.ini";
		$inifile_modulos = dirname($file_repo) . '/modules-installed.ini';

		// Carga definiciones, incluidas en uno de los templates del sistema
		$filename = MIFRAME_SRC . "/repository/templates/startup/{$startup}/tpl-config.ini";
		// echo "!$inifile_modulos = " . file_exists($inifile_modulos) . " || !$inifile_namespaces = " . file_exists($inifile_namespaces) . "<hr>"; exit;

		if (!file_exists($filename)) {
			$this->mensajes['error'] = 'No pudo encontrar archivo ' . $filename;
			$this->checkMiCodeShow();
		}

		// Procesa si no existe alguno de los archivos indicados o si el archivo de instalados es mas antiguo
		// que  el archivo guia (template).
		if (!file_exists($inifile_modulos)
			|| !file_exists($inifile_namespaces)
			|| (file_exists($inifile_modulos) && filemtime($inifile_modulos) < filemtime($filename))
			|| !$this->checkInstalledIni($inifile_modulos, $filename)
			|| filemtime($this->local_path) > filemtime($inifile_modulos)
			) {

			$startup_info = parse_ini_file($filename, true, INI_SCANNER_TYPED);

			// Si no se han creado los archivos de arranque (micode/miframe/xxx) lo que se detecta porque no
			// existe "modules-installed.ini", debe apuntar directamente al repositorio

			// Directorio para ubicar los módulos asociados al proyecto
			define('MIFRAME_LOCALMODULES_PATH', MIFRAME_SRC . '/repository');

			// Carga el resto de las librerías
			include_once MIFRAME_EVAL_BASEDIR . '/micode/initialize.php';
			include_once MIFRAME_BASEDIR . '/lib/modules/admin.php';
			include_once MIFRAME_BASEDIR . '/lib/class/AdminModules.php';

			// Incluye manualmente librerá de documentación en caso que no se hayan creado las relaciones
			// entre paths y clases (php-namespaces.ini)
			include_once MIFRAME_LOCALMODULES_PATH . '/miframe/utils/ui/HTMLSupport.php';
			include_once MIFRAME_LOCALMODULES_PATH . '/miframe/utils/docsimple/DocSimple.php';
			include_once MIFRAME_LOCALMODULES_PATH . '/miframe/utils/docsimple/DocSimpleHTML.php';

			$m = new \miFrame\Local\AdminModules(true);

			// Obtiene modulos disponibles (esto requiere que existan los archivos de referencia en "micode")
			// Usar una lista predefinida para crear los archivos de arranque! Validar que el listado de modulos
			// instalados tenga una fecha > a la de dicho archivo para que refresque si es necesario!

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
		}
	}

	private function createRepoIni() {

		$file_repo = str_replace(DIRECTORY_SEPARATOR, '/', MIFRAME_EVAL_BASEDIR) . '/micode.private/repo.ini';

		// Reconstruye archivo si falta
		if (!file_exists($file_repo)) {
			$contenido = '; REPO.INI

; Proyecto
app-name-original="miCode/Admin"

; Tipo (Lenguaje de programación usado)
type="php"

; Directorio para módulos
; Subdirectorio dentro del directorio de proyecto donde serán copiados los módulos instalados desde miCode.
app-modules="micode"

; Creado en
since="2022/09/17"

; Plantilla usada para la asignación de módulos inicial del proyecto
startup="micode-admin"

; No incluir los siguientes archivos
ignore-files=

; Minimizar archivos
; Intenta reducir el tamaño de los archivos fuente removiendo comentarios y líneas en blanco.
; Aplica solamente en los tipos de archivo habilitados para esta acción.
minimize="0"

; Creado en ' . date('Y/m/d H:i:s'). PHP_EOL;


			// Librerias requeridas
			define('MIFRAME_LOCALMODULES_PATH', MIFRAME_SRC . '/repository');
			include_once MIFRAME_LOCALMODULES_PATH . '/miframe/common/functions.php';


			$dirname = dirname($file_repo);
			if (miframe_mkdir($dirname)) {
				if (!file_put_contents($file_repo, $contenido)) {
					$this->mensajes['error'] = 'No pudo crear archivo ' . $file_repo;
				}
				else {
					$this->mensajes['error'] = 'Archivo ' . $file_repo . ' creado con éxito';
				}
			}
			else {
				$this->mensajes['error'] = 'No pudo crear el directorio ' . $dirname;
			}
			$this->checkMiCodeShow();
		}

		return $file_repo;
	}

	public function checkExternals() {

		// echo "VALIDANDO EXTERNALS<hr>";

		// Valida primero se hayan definido los repositorios básicos: "miframe" y "vendor"
		$datarepos = array();
		$repos_pendientes = array();
		// Captura listado de archivos básicos
		$filename = MIFRAME_DATA . '/base/repositories.ini';
		if (file_exists($filename)) {
			$datarepos = parse_ini_file($filename, true, INI_SCANNER_TYPED);
		}
		// Adiciona repositorio incluido
		$datarepos['miframe'] = array('path' => realpath(MIFRAME_SRC . '/repository/miframe'));

		// echo "<pre>"; print_r($datarepos); echo "</pre>";

		// Captura listado de archivos básicos
		$filename = MIFRAME_DATA . '/base/lib-externals.ini';
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

		// echo "<pre>"; print_r($dataini); echo "</pre>";

		foreach ($dataini as $modulo => $info) {
			$arreglo = explode('/', $modulo, 2);
			if (!isset($arreglo[1])) { continue; }
			$arreglo[0] = trim($arreglo[0]); // Clase
			$arreglo[1] = trim($arreglo[1]); // Modulo asociado
			// El primer elemento es la clase
			if ($arreglo[0] != $clase) {
				$clase = $arreglo[0];
				$registrar_clase = true;
				if (isset($datarepos[$clase])) {
					$filelocal = $this->getRepositoriesIni($datarepos[$clase]['path']);
					if (file_exists($filelocal)) {
						$locales[$clase] = parse_ini_file($filelocal, true, INI_SCANNER_TYPED);
						$registrar_clase = false;
					}
				}
				if ($registrar_clase) {
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
					if ($info['dirbase'] != ''
						// && is_dir($root . $datarepos[$clase]['path'] . DIRECTORY_SEPARATOR . $info['dirbase'])
						&& is_dir($datarepos[$clase]['path'] . DIRECTORY_SEPARATOR . $info['dirbase'])
						) {
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

		// En $dataini quedan los locales pendientes por definir
		if ($total_pendientes > 0) {
			$this->updateExternals($dataini, $locales, $datarepos);
			$this->checkExternalsShow($dataini, $locales, $datarepos);
		}
	}

	private function updateRepositories(array &$datarepos, array &$repos_pendientes) {

		$guardar = false;

		if (isset($_REQUEST['check-externals-update'])) {
			// echo "<pre>"; print_r($_REQUEST); echo "</pre>"; exit;
			// NOTA: No generaliza $lroot en minúsculas en caso de ejecutar en Linux, donde los
			// path si se afectan según sean en mayúsculas o minúsculas.
			/* REMOVIDA VALIDACION DE DOCUMENT_ROOT!
			Dificulta proceso de adaptación al cambiar el DOCUMENT_ROOT de miCode pero
			no el de los demás scripts.
			$lroot = str_replace("\\", '/', $_SERVER['DOCUMENT_ROOT']) . '/';
			$len = strlen($lroot);
			*/
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
					/*
					$lvalor = strtolower($valor);
					// El directorio recibido DEBE estar contenido en $_SERVER['DOCUMENT_ROOT']
					if (substr($lvalor, 0, $len) === strtolower($lroot)) {
						$valor = substr($valor, $len);
					}
					if ($valor == '' || !is_dir($lroot . $valor)) {
					*/
					if ($valor == '' || !is_dir($valor)) {
						$repos_pendientes[$clase]['error'] = 'Directorio no valido';
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

				include_once MIFRAME_SRC . '/repository/miframe/file/inifiles.php';

				$filename = MIFRAME_DATA . '/base/repositories.ini';
				ksort($datarepos);
				// Remueve path de "miframe" (se fija automáticamente)
				unset($datarepos['miframe']['path']);
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

		echo "<p>Algunos directorios usados como repositorios de código y/o recursos son requeridos para poder continuar.</p>".
			// "Tenga presente que todos los directorios indicados <b>deben</b> ser subdirectorios de <i>{$_SERVER['DOCUMENT_ROOT']}</i>.</p>" .
			"<p>Favor indicar el path completo para prevenir posibles interpretaciones erroneas, ejemplo: <i>C:\\subdir1\\subdir2</i>.</p>" .
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
			echo "<p style=\"margin-top:20px\"><input type=\"submit\" name=\"check-externals-update\" value=\"Actualizar repositorios\" style=\"padding:5px 10px\"></p>";
		}
		echo "</form>";

		$this->closeHTML();
	}

	private function updateExternals(array &$dataini, array &$locales, array $datarepos) {

		$guardar = false;

		if (isset($_REQUEST['check-externals-update'])) {
			foreach ($dataini as $modulo => $info) {
				if (!$info['pendiente']) { continue; }
				$valor = '';
				if (isset($_REQUEST[$info['ctl']])) {
					$valor = str_replace(array('..', '<', '#'), '_', trim($_REQUEST[$info['ctl']]));
				}

				if ($valor != '') {
					$dataini[$modulo]['valor'] = $valor;
					$valor = str_replace("\\", '/', $valor);
					$lvalor = strtolower($valor);
					// Valida si contiene el directorio indicado para esta clase
					$lclase = str_replace("\\", '/', $datarepos[$info['clase']]['path']) . '/';
					$lenc = strlen($lclase);
					// El directorio recibido DEBE estar contenido en el directorio asociado a la clase
					if (substr($lvalor, 0, $lenc) === strtolower($lclase)) {
						$valor = substr($valor, $lenc);
					}
					if ($valor == '' || !is_dir($lclase . $valor)) {
						$dataini[$modulo]['error'] = 'Directorio no valido';
					}
					else {
						// Valida archivo dependencia
						if (!is_array($info['require'])) {
							$info['require'] = explode("\n", str_replace("\r", '', $info['require']));
						}
						$archivo = array_shift($info['require']);
						if (!file_exists($lclase . $valor . '/' . $archivo)) {
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

				include_once MIFRAME_SRC . '/repository/miframe/file/inifiles.php';

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

		echo "<p>Algunas librerías especializadas son requeridas para poder continuar.</p>".
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
				echo "<div><small>Debe ser un subdirectorio de <i>" . $datarepos[$info['clase']]['path'] . "</i>";
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
			echo "<p style=\"margin-top:20px\"><input type=\"submit\" name=\"check-externals-update\" value=\"Actualizar dependencias\" style=\"padding:5px 10px\"></p>";
		}
		echo "</form>";

		$this->closeHTML();
	}

	private function checkMiCodeShow(array $listado = array()) {

		$this->openHTML('miCode - Módulos iniciales de proyecto');

		if (count($listado) > 0) {
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
		}
		else {
			echo "<p>No se han podido evaluar los módulos iniciales requeridos por este proyecto.</p>";
		}

		$this->closeHTML();
	}

	private function openHTML(string $titulo) {

		include_once MIFRAME_ROOT . '/tests/lib/testfunctions.php';

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

		// return $filename = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $local . DIRECTORY_SEPARATOR . 'micode-repository.ini';
		return $filename = $local . DIRECTORY_SEPARATOR . 'micode-repository.ini';
	}
}