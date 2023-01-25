<?php
/**
 * Librería de funciones requeridas para manejo de funciones
 *
 * PENDIENTES dirbase:
 * - (todos) Los nombres de archivo deben respetarse como esten en disco.
 * - Al copiar no ubica los "require" correctamente
 * - Marca diferencias entre modulos de proyecto y original aunque tienen la misma fecha.
 * - Marcar en menu "modulos" cuando estén en un directorio diferente al repositorio (mostrarlo en pantalla
 *   para todos?)
 *
 * Todos los modulos requiere se defina un "dirbase" que es de dónde tomará los archivos indicados en "require".
 * El nombre del modulo es [miframe/modules/vendor]/[destino] o [miframe/modules/vendor]/[destino]/[submodulo].
 * El directorio destino se crea con [miframe/modules/vendor]/[destino]. El campo "submodulo" solamente se
 * necesita cuando se tiene un modulo cuyo "dirbase" es el mismo de otro. Todos los modulos que empiecen con el
 * mismo [miframe/modules/vendor]/[destino] deben tener el mismo dirbase, esto es para evitar que se defina en
 * un dirbase diferente y puedan remplazarse archivos con nombres similares, generando conflictos.
 *
 * AVISO: No guardar en los path la parte hasta antes del "micode" (para portabilidad)
 * Guardar listado de los archivos copiados para cuando se vayan a retirar modulos (no debe borrar
 * archivos si son compartidos, ojo!)
 *
 * SUGERENCIA MEJORA (2023):
 * "php-namespaces" agrupar por el archivo destino y no la clase, ya que existen casos (nusoap) donde un mismo
 * archivo contiene multiples clases y eso ocuparía menos memoria. También, indicar por ejemplo "claseB --> :claseA
 * indicando que la "claseB" lee el mismo archivo de "claseA".
 *
 * @author John Mejia
 * @since Junio 2022
 * @version 1.0.0
 */

namespace miFrame\Local;

// Funciones de serialización usadas por AdminModules
include_once MIFRAME_LOCALMODULES_PATH . '/miframe/file/serialize.php';

class AdminModules {

	// private $documentar = false;
	private $listado = false;
	private $listabase = false;
	private $locales = false;
	private $app_name_local = '';
	private $externas = false;
	private $repositories = false;

	public $clase_manejador = false;
	public $manejadores = array();

	// public function __construct() { ... }

	public function loadManager(string $filename) {

		$extension = miframe_extension($filename);
		if ($extension != '') {
			if (!isset($this->manejadores[$extension])) {
				$this->manejadores[$extension] = micode_modules_class(substr($extension, 1), false);
			}
			$this->clase_manejador = &$this->manejadores[$extension];
		}

		return ($this->clase_manejador !== false);
	}

	private function moduleCRC(string $desc, array $info) {

		$text = '';
		$items = array('dirbase', 'datetime', 'size', 'sha', 'require', 'require-total');
		foreach ($items as $k => $name) {
			if (isset($info[$name])) {
				if (is_array($info[$name])) { $text .= implode(',', $info[$name]); }
				else { $text .= $info[$name]; }
			}
			$text .= '|';
		}

		return md5($text);
	}

	public function clearRepos() {
		$this->repositories = false;
	}

	public function getAllRepos(string $name = '') {

		if (!is_array($this->repositories)) {
			$filename = miframe_path(MIFRAME_ROOT, 'data/repositories.ini');
			$this->repositories = miframe_inifiles_get_data($filename);
			// Adiciona repositorio estándar
			$spath = micode_modules_repository_path('miframe');
			// Remueve el document-root
			$lpath = strtolower(str_replace("\\", '/', $spath)) . '/';
			$root = str_replace("\\", '/', $_SERVER['DOCUMENT_ROOT']) . '/';
			$len = strlen($root);
			if (substr($lpath, 0, $len) === strtolower($root)) {
				$spath = substr($spath, $len);
			}
			$this->repositories['miframe'] = array(
				'description' => miframe_text('Repositorio para módulos incluídos con miCode'),
				'path' => $spath
				);
			// Ordena repositorios
			ksort($this->repositories);
		}

		// Aplica filtro sobre los repositorios
		if ($name != '') {
			if (isset($this->repositories[$name])) {
				$this->repositories = array($name => $this->repositories[$name]);
			}
			else {
				$this->repositories = array();
			}
		}

		return $this->repositories;
	}

	public function readRepositoryIni(string $base) {

		$listado = array();

		if (!is_array($this->repositories)) {
			$this->getAllRepos($base);
		}
		if (isset($this->repositories[$base])) {
			$info = $this->repositories[$base];
			$filename = miframe_path($_SERVER['DOCUMENT_ROOT'], $info['path'], 'micode-repository.ini');
			$listado = miframe_inifiles_get_data($filename);
		}

		return $listado;
	}

	public function getAllModules(string $type = '', string $module = '') { // }, bool $documentar = null) {

		$retornar = array();

		// Recupera arreglo de repositorios creados
		$this->getAllRepos();

		// Busca en repositorios
		if (!is_array($this->listado)) {
			$this->listado = array();
			$this->listabase = array();
			foreach ($this->repositories as $base => $info) {
				$listado = $this->readRepositoryIni($base);
				if (is_array($listado) && count($listado) > 0) {
					ksort($listado);
					$this->repositories[$base]['modules-count'] = count($listado);
					// Adiciona al listado global
					foreach ($listado as $k => $v) {
						$k = str_replace(array(':', "\\", '@', '>', '<', '?', '"', '|', '*', '#'), '-', $k);
						$arreglo = explode('/', $k, 2);
						$modulo = $base . '/' . $arreglo[0];
						$modbase = $modulo;
						if (isset($arreglo[1])) {
							// Si hay un elemento "2" lo ignora
							$modulo .= '/' . str_replace('/', '-', $arreglo[1]);
						}
						// Recupera path real del directorio asociado al modulo
						$dirbase = $this->evalDirBase($modulo, $v);
						// Redefine "dirbase" para apuntar al repositorio comun
						if (!isset($this->listado[$modulo])) {
							// if (isset($v['dirbase'])) { unset($v['dirbase']); }
							$v['repo-local'] = $base;
							$v['module-name'] = $k;
							$v['module-base'] = $modbase;
							$this->listado[$modulo] = $v;
						}
						else {
							miframe_error('El módulo "$1" ya está definido', $modulo);
						}
						if ($dirbase !== 'external') {
							if (!isset($this->listabase[$modbase])) {
								$this->listabase[$modbase] = $dirbase;
							}
							elseif ($this->listabase[$modbase] != $dirbase) {
								miframe_error('El directorio base para el módulo "$1" es compartido con otros modulos, pero su valor ($2) es diferente al esperado ($3)',
									$modulo, $dirbase, $this->listabase[$modbase]);
							}
						}
					}
				}
			}
		}

		// miframe_debug_box($this->listabase, 'listabase');
		// miframe_debug_box($this->listado, 'modulos-pre');
		// exit;

		if ($module !== '') {
			$module = strtolower(trim($module));
			$this->buildModuleArray($module, $retornar);
		}
		else {
			$type = strtolower(trim($type));
			foreach ($this->listado as $modulo2 => $info) {
				// $extension = strtolower(pathinfo($modulo2, PATHINFO_EXTENSION));
				$tipo_modulo = 'php';
				if (isset($info['type'])) {
					$tipo_modulo = strtolower($info['type']);
				}
				if ($type == '' || ($type == $tipo_modulo)) {
					$this->buildModuleArray($modulo2, $retornar);
				}
			}
		}

		return $retornar;
	}

	private function buildModuleArray(string $module, array &$data) {

		if ($module != '' && isset($this->listado[$module])) {
			$this->evalModuleInfo($module);
			$data[$module] = $this->listado[$module];
		}
	}

	public function getModulesApp(string $app_name, array $data_repo, bool $return_missings = false) { // }, bool $documentar = null) {

		// Completa path de proyecto local
		$path = micode_modules_path($app_name, false, $data_repo);

		if (!is_array($this->locales) || $this->app_name_local != $app_name) {
			$this->locales = array(
				// 'new' => array(),
				'pre' => false,
				'add' => array(),
				'del' => array(),
				'changes' => 0
			);
			$this->faltantes = array();
			$this->app_name_local = $app_name;
			$listado = $this->getAllModules();

			// Busca los modulos instalados en el .ini. Si no lo encuentra, intenta reconstruirlo
			$inifile = miframe_path(dirname($data_repo['inifile']), 'modules-installed.ini');
			$total_validos_pre = 0;
			if (file_exists($inifile)) {
				$this->locales['pre'] = miframe_inifiles_get_data($inifile, true);
				// Valida si el INI tenia la información completa o requiere ser completado
				// (ej. Cuando se modifica estructura)
				$total_validos_pre = count($this->locales['pre']);
				foreach ($this->locales['pre'] as $modulo => $info) {
					if (!isset($listado[$modulo])) {
						// Módulo no existente (pudo haber sido retirado?)
						$total_validos_pre --;
					}
				}
			}
			if (!is_array($this->locales['pre'])
				// Hace revisión manual si no hay valores en "pre" o si existe el .ini pero todo está errado
				|| ($total_validos_pre <= 0 && count($this->locales['pre']) > 0)
				) {
				// No pudo leer el archivo .ini
				if (!is_array($this->locales['pre'])) {
					$this->locales['pre'] = array();
				}

				foreach ($listado as $modulo => $info) {
					// Busca localmente
					if (isset($this->locales['pre'][$modulo])
					 	&& (!isset($this->locales['pre'][$modulo]['auto-recover']))
					) {
						// Módulo pre-existente, no autorecuperado
						continue;
					}

					$dirbase = $this->getDirBase($modulo);
					$dirdestino = $this->getDirRemote($modulo, $path);
					$requeridos = $this->addFiles($modulo, $info['require'], $dirbase);

					foreach ($requeridos as $basename => $filename) {
						$fileremote = miframe_path($dirdestino, $basename);
						if (file_exists($fileremote)) {
							// El archivo existe localmente en el proyecto
							$inforeq = array(
								'datetime' => filemtime($fileremote),
								'size' => filesize($fileremote),
								'sha' => sha1_file($fileremote),
								'require-total' => 1,
								'changed' => false,
								'auto-recover' => true,
								'files' => array($basename)
							);
							if (!isset($this->locales['pre'][$modulo])) {
								$this->locales['pre'][$modulo] = $inforeq;
							}
							else {
								// No procesa los ya existentes
								$this->acumModuleInfo($this->locales['pre'][$modulo], $inforeq);
								$this->locales['pre'][$modulo]['require-total'] ++;
								$this->locales['pre'][$modulo]['files'][] = $basename;
							}
						}
					}
				}

				// miframe_debug_box($this->locales['pre'], 'Instalados'); exit;
			}

			// Valida información
			foreach ($this->locales['pre'] as $modulo => $infolocal) {
				if (isset($this->listado[$modulo])) {
					$info = $this->listado[$modulo];
					// Complementa valores
					$this->locales['pre'][$modulo]['description'] = $info['description'];
					$this->locales['pre'][$modulo]['uses'] = array();
					$this->locales['pre'][$modulo]['require-total'] = $info['require-total'];
					$this->locales['pre'][$modulo]['sysdata'] = array(
						// 'path' => $info['path'],
						'datetime' => $info['datetime'],
						'size' => $info['size'],
						'sha' => $info['sha']
					);
					// Valida si los "uses" estan definidos
					if (isset($info['uses']) && is_array($info['uses'])) {
						$this->locales['pre'][$modulo]['uses'] = $info['uses'];
						foreach ($info['uses'] as $u => $umodulo) {
							if (!isset($this->locales['pre'][$umodulo])
								&& !in_array($umodulo, $this->locales['add'])) {
								$this->locales['add'][] = $umodulo;
							}
						}
					}

					// echo $modulo . '<br>' ; print_r($info) ; echo '<br>' ; print_r($infolocal) ; echo "<hr>";
					// Valida cambios generales
					if ($info['sha'] != $infolocal['sha']
						|| $info['datetime'] != $infolocal['datetime']
						|| $info['size'] != $infolocal['size']
						|| $info['require-total'] != $infolocal['require-total']
						) {
						$this->locales['pre'][$modulo]['changed'] = true;
						$this->locales['changes'] ++;
					}
				}
				else {
					// Posible modulo removido
					$this->locales['del'][$modulo] = $infolocal;
					unset($this->locales['pre'][$modulo]);
				}
			}
		}

		// miframe_debug_box($this->locales, 'locales'); exit;

		return $this->locales;
	}

	public function updateRemoteModules(array $data, string $app_name = '') {
		// Guarda archivo remoto con actualizaciones

		$resultado = false;
		$namespaces = array();
		if ($app_name != '') {
			$this->app_name_local = $app_name;
		}
		if ($this->app_name_local !== '') {
			$data_repo = micode_modules_repo($this->app_name_local);
			$path = micode_modules_path($this->app_name_local, false, $data_repo);
			// Elementos a remover
			$mantener = array('datetime', 'size', 'sha', 'require-total', 'files'); // 'path'
			foreach ($data as $modulo => $info) {
				// Se asegura que existan los valores minimos y remueve el resto
				$nuevo = array();
				foreach ($mantener as $k => $param) {
					$nuevo[$param] = '';
					if (isset($info[$param])) {
						$nuevo[$param] = $info[$param];
					}
				}
				$data[$modulo] = $nuevo;
				// Recupera namespaces
				if (isset($this->listado[$modulo]['php-namespaces'])
					&& count($this->listado[$modulo]['php-namespaces']) > 0
				) {
					foreach ($this->listado[$modulo]['php-namespaces'] as $class => $filename) {
						$namespaces[strtolower($class)] = $filename;
					}
				}
			}
			// Guarda arreglo resultante
			$inifile = miframe_path(dirname($data_repo['inifile']), 'modules-installed.ini');
			ksort($data);
			// echo "$inifile<hr>"; print_r($data); echo "<hr>"; exit;
			$resultado = miframe_inifiles_save_data($inifile, $data);
			// Guarda archivo con namespaces (si aplica)
			if (count($namespaces) > 0) {
				$inifile = miframe_path($path, 'config', 'php-namespaces.ini');
				ksort($namespaces);
				miframe_inifiles_save_data($inifile, $namespaces);
			}
		}

		return $resultado;
	}

	public function getModulesNotInstalled() {

		if (!is_array($this->listado)) {
			miframe_error('Listado de módulos no cargado');
		}

		$nuevos = array();
		// Busca en los archivos existentes, cuales quedan por instalar
		foreach ($this->listado as $modulo => $info) {
			if (!isset($this->locales['pre'][$modulo])
				&& !isset($this->locales['add'][$modulo])
				&& isset($info['description'])
				) {
				// Para adicionar
				$nuevos[$modulo] = array(
					// 'path' => $info['path'],
					'description' => $info['description'],
					'datetime' => $info['datetime'],
					'size' => $info['size'],
					'sha' => $info['sha'],
					'uses' => $info['uses'],
					'require-total' => $info['require-total']
				);
			}
		}

		return $nuevos;
	}

	private function evalDirBase(string $module, array &$info) {

		$dirbase = '';
		$arreglo = explode('/', $module);

		// Asocia directorio base si no existe (debe haber indicado al menos 3 elementos en $module)
		if (!isset($info['dirbase']) || $info['dirbase'] == '') {
			$info['dirbase'] = $arreglo[1];
		}

		if (trim($info['dirbase']) != '') {
			// Si el directorio base comienza con "/" adiciona DOCUMENT_ROOT, si no, adiciona
			// la ruta local del repositorio.
			$dirbase = trim(str_replace('..', '_', $info['dirbase']));
			if (strtolower($dirbase) == ':external') {
				// Busca definiciones externas
				if (!is_array($this->externas)) {
					$filename = miframe_path(MIFRAME_PROJECTS_REPO, 'micode-admin', 'externals-installed.ini');
					$this->externas = miframe_inifiles_get_data($filename);
					// miframe_debug_box($this->externas, 'Externas ' . $filename);
				}
				// $module = "miframe/...", cualquier otro es ignorado
				$modulo_externo = str_replace('miframe/', '', $module);
				if (isset($this->externas[$modulo_externo])) {
					$external = miframe_path($_SERVER['DOCUMENT_ROOT'], $this->externas[$modulo_externo]);
					if (is_dir($external)) {
						// Retorna referencia a path en repositorio (asumido)
						// $arreglo = explode('/', $module);
						// $dirbase = micode_modules_repository_path($arreglo[0] . '/' . $arreglo[1]);
						// echo $dirbase;
						return 'external';
					}
				}
			}
		}

		$tipodir = '';
		if ($dirbase != '') {
			$root = strtolower(str_replace(DIRECTORY_SEPARATOR, '/', $_SERVER['DOCUMENT_ROOT'])) . '/';
			$ldirbase = strtolower(str_replace(DIRECTORY_SEPARATOR, '/', $dirbase));
			$inicio = substr($ldirbase, 0, 1);
			if ($inicio == '/') {
				// Referido al document-root
				$dirbase = miframe_path($_SERVER['DOCUMENT_ROOT'], $dirbase);
				$tipodir = 'document-root';
			}
			elseif (substr($ldirbase, 0, strlen($root)) == $root && is_dir($dirbase)) {
				// Es un directorio valido (debe contener document-root)
				$dirbase = realpath($dirbase);
				$tipodir = 'realpath';
			}
			elseif (isset($this->repositories[$arreglo[0]])) {
				// Tomado del repositorio local, complementa
				// $dirbase = micode_modules_repository_path($arreglo[0] . '/' . $dirbase);
				$dirbase = miframe_path($_SERVER['DOCUMENT_ROOT'], $this->repositories[$arreglo[0]]['path'], $dirbase);
				$tipodir = 'Repositorio';
			}
		}
		if ($dirbase == '' || !is_dir($dirbase)) {
			// Siempre requiere el dirbase para "vendor" y "modules"
			// (puede definir un dirbase por defecto? Pendiente validar)
			miframe_error('Módulo local "$1" no contiene definición de directorio base valido', $module);
		}

		// Finalmente, valida el directorio base para todos los casos
		if (!is_dir($dirbase)) {
			miframe_error('El directorio base "$1" para el módulo local "$2" no existe', $dirbase, $module);
		}
		elseif (strpos(strtolower($dirbase), strtolower(miframe_path($_SERVER['DOCUMENT_ROOT']))) === false) {
			miframe_error('El directorio base para el módulo local "$1" no es valido', $module);
		}

		// echo "$tipodir: $dirbase<hr>";

		return $dirbase;
	}

	public function getDirBase(string $module) {

		$dirbase = false;
		$arreglo = explode('/', $module);
		$modulo = $arreglo[0] . '/' . $arreglo[1];
		$modulo_externo = str_replace('miframe/', '', $module);
		if (isset($this->externas[$modulo_externo])) {
			$dirbase = miframe_path($_SERVER['DOCUMENT_ROOT'], $this->externas[$modulo_externo]);
		}
		elseif (isset($this->listabase[$modulo])) {
			$dirbase = $this->listabase[$modulo];
		}
		else {
			miframe_error('Directorio base no encontrado para "$1"', $module);
		}

		return $dirbase;
	}

	public function getDirRemote(string $module, string $path = '', string $basename = '') {

		$arreglo = explode('/', $module);
		// Solamente usa dos elementos de $module
		$path = miframe_path($path, $arreglo[0], $arreglo[1], $basename);

		return $path;
	}

	private function acumModuleInfo(&$info, $inforeq) {

		$info['size'] += $inforeq['size'];
		$info['sha'] = sha1($info['sha'] . '/' . $inforeq['sha']);
		if ($info['datetime'] < $inforeq['datetime']) {
			$info['datetime'] = $inforeq['datetime'];
		}
		if (isset($inforeq['uses'])) {
			$this->acumUses($info, $inforeq['uses']);
		}
	}

	private function acumUses(&$info, array $inforeq) {

		foreach ($inforeq as $usemod) {
			$usemod = trim(strtolower($usemod));
			if ($usemod != '' && !in_array($usemod, $info['uses'])) {
				$info['uses'][] = $usemod;
			}
		}
	}

	private function evalModuleInfo(string $module) {

		$modulo_valido = false;

		// Realiza copia para no afectar el valor original
		$info = array();

		if (isset($this->listado[$module])) {
			$info = $this->listado[$module];
		}

		if (!isset($info['sha'])) {
			if (!isset($info['type'])) {
				$info['type'] = 'php'; // Por defecto asume PHP
			}

			if (!micode_modules_eval_type($info['type'])) {
				// miframe_error('Módulo local "$1" definido para un tipo no soportado ($2)', $module, $info['type']);
			}
			elseif (!isset($info['require']) || trim($info['require']) == '') {
				miframe_error('Módulo local "$1" definido sin archivos asociados', $module);
			}
			else {
				// Procesa información

				$info['php-namespaces'] = array();

				$dirbase = $this->getDirBase($module);

				$requeridos = $this->addFiles($module, $info['require'], $dirbase, true);

				$documentar_params = [ 'description', 'author', 'since' ];
				foreach ($documentar_params as $dparam) {
					if (!isset($info[$dparam])) { $info[$dparam] = ''; }
				}
				if (isset($info['micode-uses']) && trim($info['micode-uses']) != '') {
					// Convierte en arreglo
					$modulos_usados = explode("\n", $info['micode-uses']);
					$info['uses'] = array();
					$this->acumUses($info, $modulos_usados);
					// Libera memoria
					unset($info['micode-uses']);
				}

				$info['datetime'] = 0;
				$info['size'] = 0;
				$info['sha'] = '';
				$info['uses'] = array();
				$info['require-total'] = count($requeridos);

				$primero = true;

				$docfile = '';
				// Archivo con documentación
				if (isset($info['docfile']) && $info['docfile'] != '') {
					$docfile = strtolower(miframe_path($dirbase, $info['docfile']));
				}

				$total_requeridos = count($requeridos);

				foreach ($requeridos as $modulo => $inforeq) {
					// Recupera los namespaces asociados (solo para PHP)
					if (isset($inforeq['php-namespaces'])) {
						$info['php-namespaces'] = $inforeq['php-namespaces'] + $info['php-namespaces'];
					}
					$this->acumModuleInfo($info, $inforeq);
					// captura información del archivo
					if ($total_requeridos == 1
						|| ($docfile != '' && strtolower($inforeq['path']) == $docfile)
						// || ($docfile == '' && $primero && $inforeq['type'] == $info['type'])
						) {
						foreach ($documentar_params as $dparam) {
							if ($info[$dparam] == '') { $info[$dparam] = $inforeq[$dparam]; }
						}
						$info['#docfile'] = $inforeq['path'];
						$primero = false;
					}
				}

				$this->listado[$module] = $info;
			}
		}

		/*
		exit;

		if (!isset($info['sha'])) {
			// No se ha complementado la información en $this->listado, procede a hacerlo.
			$filename = $this->getModuleFilename($module, $info);
			if ($this->loadManager($filename)) {
				if (file_exists($filename)) {
					$modulo_valido = true;
					// Adiciona archivo principal
					$this->listado[$module] = $this->addItem($module, $filename, $info);
				}
			}

			if (!$modulo_valido) {
				// No es un modulo valido, puede ser que se haya retirado fisicamente el archivo?
				miframe_error('El módulo local "$1" no corresponde a un archivo o un directorio valido.', $module, debug: $filename);
			}
		}
		*/

		return $modulo_valido;
	}

	/**
	 * RETIRADO: Si define en el .ini "ignore-uses", no incluye ese campo (especialmente para externals)
	 */
	private function addItem(string $filename, string $extension, array $info_base = null) {

		// $extension = pathinfo($filename, PATHINFO_EXTENSION);

		$listado = array(
			'type' => $extension,
			'path' => $filename, // Path local, si existe
			// 'basename' => '',
			'description' => '',
			'author' => '',
			'since' => '',
			'datetime' => filemtime($filename),
			'size' => filesize($filename),
			'sha' => sha1_file($filename),
			'uses' => array(),
			// 'require-total' => 0
			);

		$info = $this->readCacheModule($filename, $listado['type']);

		// Si recibe base, la adiciona
		if (!is_array($info)) { $info = array(); }
		if (is_array($info_base)) {
			$info = $info + $info_base;
		}

		$actualizar = false;
		// Valida si debe actualizar información de modulo y por ende, actualizar el archivo
		// asociado (externals/locals)
		if (!isset($info['crc']) || $info['crc'] != $this->moduleCRC('eval', $info)
			|| !isset($info['sha']) || $info['sha'] !== $listado['sha']
			|| !isset($info['datetime']) || $info['datetime'] !== $listado['datetime']
			|| !isset($info['size']) || $info['size'] !== $listado['size']
			|| (isset($info['require']) && !isset($info['require-total']))
			) {
			$actualizar = true;
		}

		// Asigna valores previos de los datos ya leidos
		$items = array('description', 'author', 'since', 'uses', 'require-total', 'php-namespaces');
		foreach ($items as $k => $name) {
			if (isset($info[$name])) {
				$listado[$name] = $info[$name];
			}
		}

		if ($actualizar) {
			// Recupera información directamente de la documentación en cada archivo
			$clase_manejador = micode_modules_class($extension, false);
			$documento = $clase_manejador->getSummary($filename);
			// Limpia arreglo para prevenir mantenga valores anteriores
			$listado['php-namespaces'] = array();
			if (is_array($documento)) {
				$listado['description'] = $documento['description'];
				$listado['author'] = $documento['author'];
				$listado['since'] = $documento['since'];
				$listado['php-namespaces'] = $documento['php-namespaces'];
				$listado['uses'] = $documento['uses'];
			}

			// Actualiza datos de este modulo
			$this->updateCacheModule($filename, $listado);
		}

		// Recupera valores manuales de $info_base
		$items = array('repo', 'test', 'require'); // 'dirbase',
		foreach ($items as $k => $name) {
			if (isset($info[$name])) {
				$listado[$name] = $info[$name];
			}
		}

		return $listado;
	}

	private function readCacheModule(string $module, string $type) {

		$info = false;

		$prefijo = 'nn';
		if ($type != '') { $prefijo = $type; }

		$dirname = miframe_temp_dir('micode-cache-modules', false);
		$filename = miframe_path($dirname , $prefijo . '-' . md5($module));
		if (file_exists($filename)) {
			// $info = miframe_inifiles_get_data($filename);
			$info = miframe_unserialize($filename);
			// Realiza confirmación de la información leida
			if (!is_array($info)
				|| !isset($info['type'])
				|| !isset($info['#module'])
				|| $info['type'] != $type
				|| $info['#module'] != $module) {
				$info = false;
			}
			else {
				// Remueve item no necesario
				unset($info['#module']);
			}
		}

		return $info;
	}

	private function updateCacheModule(string $module, array $info) {

		$prefijo = 'nn';
		if (isset($info['type'])) { $prefijo = $info['type']; }
		// Adiciona item de control
		$info['crc'] = $this->moduleCRC('new', $info);
		$info['#module'] = $module;

		$dirname = miframe_temp_dir('micode-cache-modules', true);
		$filename = miframe_path($dirname , $prefijo . '-' . md5($module));

		// IMPORTANTE! Si cambia la definición de caché este archivo queda inservible!

		return miframe_serialize($filename, $info);
	}

	public function getRequiredFiles(string $module, bool $full = false) {

		$requeridos = array();

		$this->getAllModules();
		if (isset($this->listado[$module])) {
			$info = $this->listado[$module];
			$dirbase = $this->getDirBase($module);
			$requeridos = $this->addFiles($module, $info['require'], $dirbase, $full);
		}

		return $requeridos;
	}

	private function addFiles(string $module, string $require, string $path, bool $full = false) {

		// Busca archivos asociados (algunos se indican con "*")
		$requeridos = array();
		$require = explode("\n", $require);
		if (count($require) > 0 && $path != '') {
			$lenpath = strlen($path);
			$subdir = dirname($module);
			foreach ($require as $a => $add_path) {
				// Limpia linea y valida
				$add_path = trim($add_path);
				if ($add_path == '') { continue; }
				// Valida que el archivo exista y/o interpreta comodines
				$fileList = glob(miframe_path($path, $add_path), GLOB_NOSORT);
				foreach ($fileList as $k => $filename) {
					if (!is_dir($filename)) {
						$add_modulo = substr($filename, $lenpath + 1);
						$extension = pathinfo($filename, PATHINFO_EXTENSION);
						if ($full) {
							// Valida archivos a ignorar (solo scripts)
							if (micode_modules_types($extension) !== false) {
								$requeridos[$add_modulo] = $this->addItem($filename, $extension);
							}
							else {
								// No es un archivo valido
								$requeridos[$add_modulo] = array(
									'type' => $extension,
									'path' => $filename,
									// 'basename' => $add_modulo,
									'size' => filesize($filename),
									'sha' => sha1_file($filename),
									'datetime' => filemtime($filename)
								);
							}
							$requeridos[$add_modulo]['require-id'] = $a;
						}
						else {
							$requeridos[$add_modulo] = $filename; // substr($filename, $len_repo);
						}
					}
				}
			}
		}

		return $requeridos;
	}
}