<?php
/**
 * Edición de repositorio.
 *
 * @author John Mejia
 * @since Enero 2023
 */

if (!isset($repo_nuevo)) { $repo_nuevo = false; }

$clase = '';
$listado = array();
$m = new \miFrame\Local\AdminModules(true);

if (!$repo_nuevo) {
	// Clase selecta
	$clase = $this->router->param('name');
	$listado = $m->getAllRepos($clase);
	if ($clase == '' || !isset($listado[$clase])) {
		$this->router->abort(
			miframe_text('Parámetros incompletos'),
			miframe_text('No se ha definido un nombre de repositorio válido')
			);
	}
}

$this->startEditConfig();

// Define valores iniciales
if (!$repo_nuevo && isset($listado[$clase])) {
	$this->config->setDataValues($listado[$clase], true);
}

// Configura validadores y helpers del objeto EditConfig
$this->config->addValidator('newrepo', $repo_nuevo);
$this->config->addHelper('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']);

$inifile = micode_modules_dataconfig_path('repositories-cfg.ini');
$this->config->addConfigFile('miclase', $inifile);

if ($this->config->checkformRequest('configok') && ($clase != '' || $repo_nuevo)) {

	$redirigir = true;
	$mensaje = '';

	if ($this->config->unsaved('miclase')) {
		// Actualiza instancia actual
		$arreglo = $this->config->getValues('miclase');
		$arreglo['path'] = trim(str_replace('..', '_', $arreglo['path']));
		$repo_path = miframe_path($_SERVER['DOCUMENT_ROOT'], $arreglo['path']);
		if ($arreglo['path'] == ''
			|| !is_dir($repo_path)
			) {
			$mensaje = miframe_text('Ubicación dada al repositorio no es valida. Debe ser un subdirectorio de *$1*', $_SERVER['DOCUMENT_ROOT']);
			$redirigir = false;
		}
		else {
			// Valida si es nuevo para asignar la $clase
			if ($repo_nuevo) {
				$clase = miframe_only_alphanum(strtolower($arreglo['repo-name']), '-');
				if ($clase != '') {
					$listado = $m->getAllRepos($clase);
					if (isset($listado[$clase])) {
						// Ya existe el repositorio con ese nombre
						$mensaje = miframe_text('El nombre del nuevo repositorio ($1) ya está en uso.', $clase);
						$redirigir = false;
					}
				}
			}
			if ($redirigir) {
				// Continua con el proceso, no han ocurrido errores previos
				// Recupera TODOS los listados para poder actualizar el .ini
				$m->clearRepos();
				$listado = $m->getAllRepos('', true);
				// Fecha de creación/modificado
				if (!isset($listado[$clase])) {
					$arreglo['date-created'] = date('Y-m-d H:i:s');
				}
				else {
					// Mantiene la fecha de creación
					if (isset($listado[$clase]['date-created'])) {
						$arreglo['date-created'] = $listado[$clase]['date-created'];
					}
					// Adiciona la fecha de modificado
					$arreglo['date-modified'] = date('Y-m-d H:i:s');
				}
				// Actualiza clase actual
				$listado[$clase] = $arreglo;
				$filename = miframe_path(MIFRAME_ROOT, 'data/repositories.ini');
				$resultado = miframe_inifiles_save_data($filename, $listado);
				if ($resultado) {
					$mensaje = miframe_text('Listado de repositorios actualizado con éxito.');
					if ($repo_nuevo) {
						// Valida si existe en el destino el archivo "micode-repository.ini"
						$filename = miframe_path($repo_path, 'micode-repository.ini');
						if (file_exists($filename)) {
							// Preserva mensaje previo
							$this->config->setMessage($mensaje);
							// Nuevo mensaje
							$mensaje = miframe_text('Este repositorio ya contiene un listado de módulos creados.');
						}
					}
				}
				else {
					$mensaje = miframe_text('No pudo actualizar listado de repositorios.');
					$redirigir = false;
				}
			}
		}
	}
	else {
		$mensaje = miframe_text('Nada que actualizar');
	}

	$this->config->setMessage($mensaje);

	if ($redirigir) {
		// Envia a detalle (fija $_REQUEST['app'] para que sea capturado al invocar $Router->param)
		$cmd = 'repositories/list';
		$data = false;
		// Guarda en temporal los mensajes y retorna un valor de caché
		if ($this->config->existsMessages()) {
			$data = array( 'msg' => $this->config->getMessages() );
		}
		// Crea pagina a recargar
		$enlace = $this->router->reload($cmd, null, $data);
	}
}

if ($clase == '') { $clase = '?'; } // Se asegura muestre menu de repo

$data_proyecto = array('reponame' => $clase, 'nuevo' => $repo_nuevo);
if (isset($listado[$clase])) {
	$data_proyecto['repodata'] = $listado[$clase];
}

$this->startView('projects/edit.php', $data_proyecto);