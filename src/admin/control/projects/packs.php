<?php
/**
 * Administrador de miFrame - Listar modulos.
 *
 * @author John Mejia (C) Abril 2022.
 */

$app_name = strtolower($this->router->param('app'));
if ($app_name == '') {
	$this->router->abort(
		miframe_text('Parámetros incompletos'),
		miframe_text('No se ha definido nombre del Proyecto a actualizar')
	);
}

$data_proyecto = micode_modules_project_data($app_name);
$data_repo =& $data_proyecto['mirepo'];
$path_modulos = micode_modules_path($app_name, true, $data_repo);
$type = $data_repo['type'];
$path_packs = miframe_path(dirname($data_repo['inifile']), 'packs');
// print_r($data_repo); exit;

if ($this->post->exists('down')) {
	$basename = $this->post->getString('down');
	$filename = miframe_path($path_packs, $basename);
	if ($basename == '' || !file_exists($filename)) {
		$this->router->abort(
			miframe_text('Parámetros incorrectos'),
			miframe_text('La descarga de paquete solicitada no está disponible')
		);
	}

	$this->router->allowDetour = true;
	$this->router->detour($filename, true);
}
elseif ($this->post->exists('new')) {
	// Nuevo paquete
	// $filepack = miframe_path(MIFRAME_PROJECTS_REPO, $app_name, 'packs', strtolower($app_name) . '-' . date('Ymd') . '.zip');
	$basepack = strtolower($app_name) . '-' . date('Ymd');
	$filepack = miframe_path($path_packs, $basepack . '.zip');
	$dirname = dirname($filepack);
	if (!is_dir($dirname)) {
		if (!@mkdir($dirname, 0777, true)) {
			miframe_error('No pudo crear directorio requerido: $1', $dirname);
		}
	}
	// $filesha = miframe_path(miframe_temp_dir(), $basepack . '.sha');

	$conteo = 0;

	$estado = '';
	$errorfile = false;

	// Valida que no hayan modulos no actualizados
	if (!$data_proyecto['modules']['changes']) {

		// Recupera todos los archivos del directorio

		$path = miframe_path($data_proyecto['config']['path']);

		$dirs = miframe_tree_directory($path, '', true);

		$files = array();
		$requeridos = array();

		// Obtiene listado de archivos instalados

		$data_proyecto_pre = &$data_proyecto['modules']['pre'];

		// miframe_debug_box($data_proyecto_pre, $path_modulos);

		$m = new \miFrame\Local\AdminModules();

		foreach ($data_proyecto_pre as $modulo => $info) {
			// $listado = $m->getAllModules('', $modulo);
			$requeridos_local = $m->getRequiredFiles($modulo);
			// miframe_debug_box($requeridos, $modulo);
			foreach ($requeridos_local as $basename => $pathrepo) {
				$dmodulo = $m->getDirRemote($modulo, '', $basename);
				$requeridos[$dmodulo] = $pathrepo;
			}
		}

		// Obtiene archivos a ignorar (no incluir en el paquete)
		$ignorar = array();
		$minimizar = false;

		/*
		Leer de ignore-files.ini
		Crear .gitignore con la lista de archivos de micode y micode.private
		(complementa el existente si alguno)
		No remover lineas (a menos que el archivo relacionado en las mismas no exista)
		*/

		if (isset($data_repo['ignore-files'])) {
			$arreglo = explode("\n", str_replace(',', "\n", $data_repo['ignore-files']));
			foreach ($arreglo as $linea) {
				$linea = trim($linea);
				if ($linea != '') {
					$linea = strtolower(str_replace('/', DIRECTORY_SEPARATOR, $linea));
					if (substr($linea, -1, 1) == '*') {
						// Remueve directorios/archivos completos
						$linea = trim(substr($linea, 0, -1));
						if ($linea != '') {
							$ignorar['parcial'][] = $linea;
						}
					}
					else {
						$ignorar['full'][] = $linea;
					}
				}
			}
		}
		// Ignora siempre el archivo sha
		// $ignorar['full'][] = basename($filesha);
		// Ignora todo el contenido de "micode.private"
		$ignorar['parcial'][] = 'micode.private' . DIRECTORY_SEPARATOR;

		if (isset($data_repo['minimize'])) {
			$minimizar = ($data_repo['minimize'] > 0);
			// exit('PENDIENTE HABILITAR MINIMIZADO');
		}

		// PENDIENTE GENERAR .gitignore

		// miframe_debug_box($ignorar, 'IGNORAR ' . $minimizar);

		// Obtiene los archivos por directorio

		$lmodulos = strtolower($path_modulos);
		$lenmodulos = strlen($lmodulos);
		$lenpath = strlen($path) + 1;
		$sha_acum = '';

		foreach ($dirs as $subdir) {
			// Si el $subdir contiene $path_modulos lo guarda aparte
			$fileList = glob($subdir . DIRECTORY_SEPARATOR . '*', GLOB_NOSORT);
			if (count($fileList) > 0) {
				foreach ($fileList as $k => $filename) {
					if (!is_dir($filename)) {
						// Valida que no esté en la lista para ignorar
						$referencia = substr($filename, $lenpath);
						$lreferencia = strtolower($referencia);
						$ignorarlocal = (isset($ignorar['full']) && in_array($lreferencia, $ignorar['full']));
						if (!$ignorarlocal && isset($ignorar['parcial'])) {
							foreach ($ignorar['parcial'] as $buscar) {
								if (substr($lreferencia, 0, strlen($buscar)) == $buscar) {
									$ignorarlocal = true;
									break;
								}
							}
						}
						if ($ignorarlocal) {
							// echo "IGNORA $filename<hr>";
							continue;
						}

						// Registra archivo
						$origen = $filename;
						if (strtolower(substr($filename, 0, $lenmodulos)) == $lmodulos) {
							$dmodulo = substr($filename, $lenmodulos);
							if (isset($requeridos[$dmodulo])) {
								$origen = $requeridos[$dmodulo];
							}
						}
						// Recupera el SHA de cada archivo
						$crc = sha1_file($origen);
						$sha_acum = sha1($crc . '/' . $sha_acum);
						// Archivo regular
						$files[] = array(
							'path' => $referencia,
							'src' => $origen,
							// 'sha' => $crc
						);
					}
				}
			}
		}

		// Archivo a contener el SHA del ZIP actual
		$origensha = miframe_path($path_packs, 'lastpack.sha');

		if (count($files) <= 0) {
			$estado = 'NOFILES';
		}
		else {
			// Crea paquete de archivos (no almacenar todo en memoria). Usar miframe-packs.

			// Valida si existe el archivo y si el sha es el mismo actual. En ese caso
			// no realiza nada mas.
			if (file_exists($origensha)) {
				$contenido = file_get_contents($origensha);
				$arreglo = explode("\n", $contenido);
				if (isset($arreglo[1])) {
					$previo_path = miframe_path($path_packs, $arreglo[1]);
					if (file_exists($previo_path) && trim($arreglo[0]) == 'SHA:' . $sha_acum) {
						// El archivo actual es valido, no necesita reescribirlo
						$estado = 'ZIP_VALIDO';
						$filepack = $previo_path;
					}
				}

				/*$zip = new ZipArchive();
				$res = $zip->open($filepack);
				if ($res) {
					$res = $zip->extractTo(dirname($filesha), basename($filesha));
					$zip->close();
					// El archivo puede no existir o fallar en la extracción
					if ($res && file_exists($filesha)) {
						$sha_previo = file_get_contents($filesha);
						unlink($filesha);
						if (substr($sha_previo, 4) == $sha_acum) {
							// El archivo actual es valido, no necesita reescribirlo
							$estado = 'ZIP_VALIDO';
						}
					}
					// echo "PREVIO $res : $filesha : " . file_exists($filesha) . "<br>$sha_previo<br>$sha_acum<hr>";
				}*/
			}
		}

		if ($estado == '') {
			// Hasta ahora todo bien, prosigue
			$zip = new ZipArchive();
			if ($zip->open($filepack, ZipArchive::CREATE) !== true) {
				// miframe_error('No pudo crear archivo $1', $filepack);
				$estado = 'ZIP_NOCREADO';
			}
			else {
				// Adiciona archivo con "sha"
				file_put_contents($origensha, 'SHA:' . $sha_acum . "\n" . basename($filepack));
				// $files[] = array(
				// 	'path' => basename($filesha),
				// 	'src' => $origensha
				// 	// 'sha' => $crc
				// );

				foreach ($files as $datafile) {
					if (!@$zip->addFile($datafile['src'], $datafile['path'])) {
						// No pudo adicionar un elemento, cancela proceso
						$errorfile = $datafile;
						break;
					}
				}

				$zip->close();

				if ($errorfile !== false) {
					unlink($filepack);
					$estado = 'ZIP_FALLIDO';
				}
				else {
					$estado = 'ZIP_OK';
				}
			}
		}

		// Actualizar .ini?

		// miframe_debug_box($dirs, $path);
	}
	else {
		$estado = 'CAMBIOS_PENDIENTES';
	}

	$data_proyecto['pack-status'] = $estado;
	$data_proyecto['pack-file'] = $filepack;
	$data_proyecto['pack-errorfile'] = $errorfile;

	$this->startView('projects\newpack.php', $data_proyecto);

	return;
}

$patron = miframe_path($path_packs, '*.zip');

$listado = array();
$fileList = glob($patron, GLOB_NOSORT);
if (count($fileList) > 0) {
	foreach ($fileList as $k => $filename) {
		$nombre = basename($filename);
		$listado[$nombre] = array(
			'datetime' => filemtime($filename),
			'size' => filesize($filename),
			'url' => $this->router->getFormAction('projects/packs/' . $app_name, true, array('down' => $nombre))
		);
	}
}

// Ordena listado en reversa
krsort($listado);

$data_proyecto['listado'] = $listado;

$this->startView('projects\packs.php', $data_proyecto);