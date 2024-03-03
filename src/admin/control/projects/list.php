<?php
/**
 * Administrador de miFrame.
 *
 * @author John Mejia (C) Abril 2022.
 */

// Recupera listado de aplicaciones existentes. Recupera subdirectorios de "projects".

$listado = array();

$filter = '*.path';

$fileList = glob(miframe_path(MIFRAME_PROJECTS_REPO, $filter), GLOB_NOSORT);
$listado = array();

if (count($fileList) > 0) {
	foreach ($fileList as $k => $path) {
		$app_name = str_replace('.path', '', strtolower(basename($path)));
		$filename = micode_modules_repo_filename($app_name, false);
		$data = false;
		if (file_exists($filename)) {
			$data_repo = micode_modules_repo($app_name, $filename);
			// miframe_debug_box($data_repo, $filename);
			$data = micode_modules_proyecto_ini($app_name, $data_repo);
			// Complementa con los enlaces
			$data['url'] = micode_modules_enlace($app_name, $data_repo);
			$data['url-detail'] = $this->router->createRouteURL("projects-info", [ 'app' => $app_name ]);
		}
		// Registra
		$listado[$app_name] = $data;
	}
}

$this->startView('projects/list.php', [ 'listado' => $listado ]);
