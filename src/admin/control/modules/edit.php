<?php
/**
 * Edición de módulos.
 *
 * @author John Mejia
 * @since Enero 2023
 */

include_once MIFRAME_LIBADMIN_PATH . '/modules/edit-support.php';

if (!isset($modulo_nuevo)) { $modulo_nuevo = false; }

$modulo = '';
$listado = array();
$m = new \miFrame\Local\AdminModules(true);

// El nombre del repositorio lo tiene el Router
$repo_name = $this->router->param('name');
$repo_info = false;
$modulo_name = '';

if (!$modulo_nuevo) {
	// Clase selecta
	$modulo = $this->post->getString('module');
	$listado = $m->getAllModules('', $modulo);
	if ($modulo == '' || !isset($listado[$modulo])) {
		$this->router->abort(
			miframe_text('Parámetros incompletos'),
			miframe_text('No se ha definido un nombre de módulo válido ($1)', $modulo)
			);
	}
	// Captura la información original en el archivo .ini (cuando se invoca desde "modulos" no se tiene el repositorio)
	$repo_name = $listado[$modulo]['repo-local'];
	$modulo_name = $listado[$modulo]['module-name'];
}

if ($repo_name == '') {
	$this->router->abort(
		miframe_text('Parámetros incompletos'),
		miframe_text('No se ha definido un nombre de repositorio válido')
		);
}

// Recupera información original del .ini
$dataini = $m->readRepositoryIni($repo_name);
$repo_info = $m->getAllRepos($repo_name);
$datamodulo = array();
if ($modulo_name != '' && isset($dataini[$modulo_name])) {
	$datamodulo = $dataini[$modulo_name];
}

// Si no está definido el tipo se asume PHP
if (!isset($datamodulo['type']) || $datamodulo['type'] == '') {
	$datamodulo['type'] = 'php';
}

$this->startEditConfig();

// Define valores iniciales
$this->config->setDataValues($datamodulo, true);

$repo_path = miframe_path($_SERVER['DOCUMENT_ROOT'], $repo_info[$repo_name]['path']);

// Configura validadores y helpers del objeto EditConfig
$this->config->addValidator('newmodule', $modulo_nuevo);
$this->config->addHelper('REPOSITORY_PATH', $repo_path);
$this->config->addHelper('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']);

$inifile = micode_modules_dataconfig_path('modules-repo-cfg.ini');
$this->config->addConfigFile('miclase', $inifile);

// print_r($listado[$modulo]); echo "<hr>";
// print_r($datamodulo); echo "<hr>";
// print_r($this->config); echo "<hr>";

if ($this->config->checkformRequest('configok') && ($modulo_name != '' || $modulo_nuevo)) {

	$redirigir = true;
	$mensaje = '';
	$modulo_name_nuevo = $modulo_name;

	if ($this->config->unsaved('miclase')) {
		// Actualiza instancia actual

		$arreglo = $this->config->getValues('miclase');
		$dirbase = trim(str_replace('..', '_', $arreglo['dirbase']));

		// module-name solo permite el "/" como carácter no alfanumerico
		$modulo_name_nuevo = $arreglo['module-name'];
		$arr_nombre = explode('/', strtolower($modulo_name_nuevo), 2);
		// Solamente se permiten 2 elementos
		$modulo_name_nuevo = miframe_only_alphanum($arr_nombre[0]);
		if ($dirbase == '') {
			$dirbase = $modulo_name_nuevo;
		}
		if (isset($arr_nombre[1])) {
			// Complementa nombre
			$modulo_name_nuevo .= '/' . miframe_only_alphanum($arr_nombre[1]);
		}
		// if ($modulo_name_nuevo != $arreglo['module-name']) {
		// 	$mensaje = miframe_text('El nombre a usar para este módulo ($1) es diferente al ingresado($2).', $clase);
		// }
		if ($modulo_name_nuevo != $modulo_name) {
			// Valida que el nuevo nombre no haya sido ya asignado
			$listado_eval = $m->getAllModules('', $modulo_name_nuevo);
			if (isset($listado_eval[$modulo_name_nuevo])) {
				$mensaje = miframe_text('El nombre dado al módulo ($1) ya está en uso.', $clase);
				$redirigir = false;
			}
		}

		// "dirbase" en un subdirectorio del directorio del repositorio
		if (!is_dir(miframe_path($repo_path, $dirbase))) {
			$mensaje = miframe_text('El directorio de origen no es valido ($2). Debe ser un subdirectorio de *$1*',
				$repo_path,
				$dirbase
				);
			$redirigir = false;
		}

		// "type" debe estar entre los tipos validos
		// Recupera tipos validos
		$validos = micode_modules_types();
		$type = trim($arreglo['type']);
		if ($type == '' || !isset($validos[$type])) {
			$mensaje = miframe_text('El tipo de módulo no ha sido asignado o no es un tipo valido.');
			$redirigir = false;
		}

		// "require" se convierte a arreglo para poder guardar y se debe validar que
		// generen al menos un archivo valido
		$require = explode("\n", $arreglo['require']);
		foreach ($require as $k => $add_path) {
			$add_path = trim($add_path);
			$fileList = array();
			if ($add_path != '') {
				$filepath = miframe_path($repo_path, $dirbase, $add_path);
				$fileList = glob($filepath, GLOB_NOSORT);
			}
			if (count($fileList) <= 0) {
				unset($require[$k]);
			}
		}
		if (count($require) <= 0) {
			$mensaje = miframe_text('No se encontraron archivos que coincidan con la lista de archivos requeridos dada.');
			$redirigir = false;
		}

		// "docfile" si se define, debe existir
		$docfile = trim(str_replace('..', '_', $arreglo['docfile']));
		if ($docfile != '' && !file_exists(miframe_path($repo_path, $dirbase, $docfile))) {
			$mensaje = miframe_text('El archivo indicado para recuperar la documentación no existe.');
			$redirigir = false;
		}

		// echo "REPO $repo_name : Continuar? $redirigir<hr>"; print_r($arreglo); echo "<hr>"; print_r($dataini); exit;

		// Valida si es nuevo para asignar la $clase
		/*if ($repo_nuevo) {
			$clase = miframe_only_alphanum(strtolower($arreglo['repo-name']), '-');
			if ($clase != '') {
				$listado = $m->getAllRepos($clase);
				if (isset($listado[$clase])) {
					// Ya existe el repositorio con ese nombre
					$mensaje = miframe_text('El nombre del nuevo repositorio ($1) ya está en uso.', $clase);
					$redirigir = false;
				}
			}
		}*/
		if ($redirigir) {
			// Continua con el proceso, no han ocurrido errores previos
			// Actualiza TODOS los listados ($dataini) para poder actualizar el .ini
			// Actualiza datos
			$arreglo['require'] = implode(PHP_EOL, $require);
			$arreglo['docfile'] = $docfile;
			// Fecha de creación/modificado
			if (!isset($listado[$clase])) {
				$arreglo['date-created'] = date('Y-m-d H:i:s');
			}
			else {
				// Mantiene la fecha de creación
				if (isset($dataini[$modulo_name_nuevo]['date-created'])) {
					$arreglo['date-created'] = $dataini[$modulo_name_nuevo]['date-created'];
				}
				// Adiciona la fecha de modificado
				$arreglo['date-modified'] = date('Y-m-d H:i:s');
			}
			// Actualiza modulo actual
			$dataini[$modulo_name_nuevo] = $arreglo;
			// print_r($listado); exit;
			$filename = miframe_path($repo_path, 'micode-repository.ini');
			$resultado = miframe_inifiles_save_data($filename, $dataini);
			if ($resultado) {
				$mensaje = miframe_text('Listado de módulos actualizado con éxito.');
			}
			else {
				$mensaje = miframe_text('No pudo actualizar listado de módulos.');
				$redirigir = false;
			}
		}
	}
	else {
		$mensaje = miframe_text('Nada que actualizar');
	}

	$this->config->setMessage($mensaje);

	if ($redirigir) {
		// Envia a detalle (fija $_REQUEST['app'] para que sea capturado al invocar $Router->param)
		$cmd = 'modules/detail';
		$params = array('module' => $repo_name . '/' . $modulo_name_nuevo );
		$data = false;
		// Guarda en temporal los mensajes y retorna un valor de caché
		if ($this->config->existsMessages()) {
			$data = array( 'msg' => $this->config->getMessages() );
		}
		// Crea pagina a recargar
		$enlace = $this->router->reload($cmd, $params, $data);
	}
}

/*
// Captura todos los modulos
$modulos = $m->getAllModules();

foreach ($modulos as $modulo => $data) {
	$basename = urlencode($modulo);
	$tipo_encode = urlencode($data['type']);
	$urlbase = "modules/detail/{$tipo_encode}?module={$basename}";
	$modulos[$modulo]['url'] = $this->router->getFormAction($urlbase, true);
	$modulos[$modulo]['dirbase'] = $m->getDirBase($modulo);
	// $listado[$modulo]['dirdest'] = $m->getDirRemote($modulo);
}

// miframe_debug_box($modulos);

// $this->startView('modules/list.php', [ 'listado' => $modulos, 'reponame' => $clase ]);
*/

if ($modulo == '') { $modulo = '?'; } // Se asegura muestre menu de repo

$data_proyecto = array('nuevo' => $modulo_nuevo, 'module' => $modulo, 'form-hidden' => array('module' => $modulo));
if (isset($listado[$modulo])) {
	// $data_proyecto['repodata'] = $listado[$modulo];
}

$this->startView('projects/edit.php', $data_proyecto);