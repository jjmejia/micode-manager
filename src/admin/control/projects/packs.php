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

	$this->router->exportFile($filename);
}
elseif ($this->post->exists('new')) {
	// Nuevo paquete
	$basepack = strtolower($app_name) . '-' . date('Ymd');
	$filepack = miframe_path($path_packs, $basepack . '.zip');
	$dirname = dirname($filepack);

	// Crea directorio, si falla genera un error
	miframe_mkdir($dirname, true, true);

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

		$m = new \miFrame\Local\AdminModules();

		$requeridos = $m->exportRemoteFiles($app_name, array_keys($data_proyecto_pre), '', true);

		// Obtiene archivos a ignorar (no incluir en el paquete)
		$ignorar = array();
		$minimizar = false;

		/*
		PENDIENTE:
		Crear .gitignore con la lista de archivos de micode y micode.private
		(complementa el existente si alguno)
		No remover lineas (a menos que el archivo relacionado en las mismas no exista)
		*/

		// Valida lista de archivos a ignorar
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

		// Ignora todo el contenido de "micode.private"
		$ignorar['parcial'][] = 'micode.private' . DIRECTORY_SEPARATOR;

		if (isset($data_repo['minimize'])) {
			$minimizar = ($data_repo['minimize'] > 0);
			// exit('PENDIENTE HABILITAR MINIMIZADO');
		}

		// PENDIENTE MANEJO DEL MINIMIZAR
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
						$dmodulo = md5(strtolower($filename));
						if (isset($requeridos[$dmodulo])) {
							$origen = $requeridos[$dmodulo]['src'];
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

		// echo '<pre>'; print_r($requeridos); exit;
		// echo '<pre>'; print_r($files); exit;

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
			'url' => $this->router->createRouteURL('projects-packs', array( 'down' => $nombre, 'app' => $app_name))
		);
	}
}

// Ordena listado en reversa
krsort($listado);

$data_proyecto['listado'] = $listado;

$this->startView('projects\packs.php', $data_proyecto);