<?php
/**
 * Administrador de miFrame - Editar proyectos.
 *
 * Este script se utiliza tanto al editar como al crear un nuevo proyecto (invocado desde
 * "create.php").
 *
 * Los archivos de configuración de un proyecto y que se almacenan localmente (todo en "miproyecto"), se conforma con:
 *
 * - sistema: Atributos globales del sistema (autor, etc.)
 *   Los valores en desarrollo deben tomarse del sistema.ini global.
 * 		+ user
 * 		+ user-email
 * - miproyecto: Atributos básicos generales del proyecto comunes a todos los proyectos. <-- Directo al proyecto.
 * 		+ project-name
 *		+ project-title
 *		+ project-desc
 *		+ project-client
 *		+ micode-local-path
 *		+ since
 * - type: Atributos propios del tipo de proyecto.
 *   Los valores por defecto se definen en el arhivo xxx-type-cfg. Directo al proyecto.
 *   Por ej. para PHP:
 * 		+ temp-path
 * 		+ php-timezone
 * 		+ php-charset
 * 		+ php-namespaces
 * - modules: Atributos que dependen de los módulos usados en el proyecto. <-- Directo al proyecto.
 * - local: Atributos propios del proyecto. <-- Directo al proyecto
 *
 * Adicionalmente se tiene un archivo en el repositorio para ubicar cada proyecto (no incluido en "miproyecto")
 * y contiene:
 *
 * - app-name-original: Solo como referencia en caso que originalmente tuviera caracteres prohibidos
 * - path
 * - app-modules
 * - type
 * - startup
 * - temporal
 * - since
 * - license (pendiente)
 *
 * @author John Mejia
 * @since Abril 2022.
 */

include_once MIFRAME_LIBADMIN_PATH . '/modules/edit-support.php';

miframe_app()->startEditConfig();

$app_name = '';
$app_path = '';
$app_module_sub = '';
$path_modulos = '';
$type = '';
$startup = '';
$lista_modulos = array();
$startup_info = array();
$notype = true;

$file_repo = '';
$temporal = false;
$data_repo = array();
$inifile = '';

// $data_proyecto puede ser creado previamente desde "create.php"
$proyecto_nuevo = (isset($data_proyecto) && isset($data_proyecto['nuevo']));
if (!$proyecto_nuevo) {
	// *************************************************************
	// EDICION DE PROYECTOS EXISTENTES
	// *************************************************************
	$app_name_local = strtolower(miframe_app()->router->param('app'));
	if ($app_name_local == '') {
		miframe_app()->router->abort(
			miframe_text('Parámetros incompletos'),
			miframe_text('No se ha definido nombre del Proyecto a actualizar')
		);
	}

	// $app_name no debe contener carácteres restringidos
	$app_name = miframe_only_alphanum($app_name_local, '-');
	// Construye paths
	$data_repo = micode_modules_repo($app_name);
	$file_repo = $data_repo['inifile'];
	$type = $data_repo['type'];

	$data_proyecto = micode_modules_project_data($app_name, $data_repo);

	// Remueve uso posterior
	unset($data_repo['inifile']);

	// Permite editar tipo si no se ha declarado o si no hay modulos adicionados
	$notype = ($type == '' || $type == '?' || count($data_proyecto['modules']['pre']) <= 0);
	// Invierte llaves y valor para agilizar las busquedas
	$lista_modulos = array_flip(array_keys($data_proyecto['modules']['pre']));

	$path_modulos = micode_modules_path($app_name, false, $data_repo);

	if ($path_modulos == '') {
		miframe_app()->router->abort(
			miframe_text('Acceso restringido al servidor'),
			miframe_text('No pudo crear directorio para copiar los archivos al proyecto')
			);
	}

	// Archivo de proyecto
	$inifile = miframe_path($path_modulos, 'config', 'miproyecto.ini');
}
elseif (miframe_app()->config->formSubmitted('configok')) {
	// *************************************************************
	// CREACION DE NUEVOS PROYECTOS
	// *************************************************************
	// Proyecto nuevo, toma el nombre del proyecto y el path destino.
	$app_name_local = miframe_app()->post->getString(miframe_app()->config->formName('app-name-original'));
	// Opcional, si no se indica se crea directorio destino en el DOCUMENT_ROOT
	$app_path = str_replace('..', '', miframe_app()->post->getString(miframe_app()->config->formName('path')));
	// Opcional, si no se indica usa "micode"
	$app_module_sub = str_replace('..', '', miframe_app()->post->getString(miframe_app()->config->formName('app-modules')));

	if ($app_name_local == '') {
		miframe_app()->config->setMessage(miframe_text('No se indicó nombre del proyecto'));
	}
	elseif ($app_path == '') {
		miframe_app()->config->setMessage(miframe_text('No se indicó el directorio para ubicar el proyecto'));
	}
	else {
		// Consideraciones:
		// $app_name no debe contener carácteres restringidos
		$app_name = miframe_only_alphanum($app_name_local, '-');
		$app_module_sub = miframe_only_alphanum($app_module_sub, '-');
		// Construye paths
		$base_final = str_replace('/', DIRECTORY_SEPARATOR, $app_path . '/' . $app_name);
		$dir_final = miframe_path($_SERVER['DOCUMENT_ROOT'], $base_final);
		$file_repo = micode_modules_repo_filename($app_name, false, $dir_final);

		// El proyecto no debe ya existir en el repositorio local
		if (file_exists($file_repo)) {
			$data = micode_modules_repo($app_name, $file_repo);
			$temporal = (isset($data['temporal']) && $data['temporal'] == true);
			if (!$temporal) {
				miframe_app()->config->setMessage(miframe_text('El nombre de proyecto "$1" ya está en uso', $app_name));
				$app_name = '';
			}
		}
		else {
			$temporal = true;
		}
		// El path destino debe estar referido al DOCUMENT_ROOT y no debe existir
		if (is_dir($dir_final)) {
			if (!$temporal) {
				miframe_app()->config->setMessage(miframe_text('El directorio destino "$1" ya existe', $base_final));
				$app_name = '';
			}
		}
		// Crea directorio destino
		$path_repo = dirname($file_repo);

		if (!miframe_mkdir($path_repo)) {
			miframe_app()->config->setMessage(miframe_text('No pudo crear directorio $1.', $path_repo));
			$app_name = '';
		}
		if ($app_name != '') {
			// Inicializa $data_proyecto
			$data_proyecto['config']['path'] = $dir_final;
			$data_proyecto['readme-path'] = micode_modules_readme($base_final);
			$since = miframe_app()->post->getString(miframe_app()->config->formName('since'));
			if ($since == '') { $since = date('Y-m-d'); }
			// Guarda archivo con los datos actuales.
			$data_repo = array(
				'app-name-original' => $app_name_local, // Solo como referencia en caso que originalmente tuviera caracteres prohibidos
				'path' => $dir_final,
				'app-modules' => $app_module_sub,
				'type' => $type,
				'startup' => '',
				'temporal' => $temporal, // Debe removerse al terminar la creación
				'since' => $since
			);

			// Valida configuración destino
			$path_modulos = micode_modules_path($app_name, false, $data_repo);
			$inifile = miframe_path($path_modulos, 'config', 'miproyecto.ini');

			$dirname = dirname($inifile);
			if (!is_dir($dirname)) {
				// Crea directorio destino
				if (!miframe_mkdir($dirname)) {
					miframe_app()->config->setMessage(miframe_text('No pudo crear directorio $1.', $dirname));
				}
			}
			elseif (file_exists($inifile) && !$temporal) {
				// Archivo ya creado y no está haciendo una actualización de datos ($temporal = true)
				miframe_app()->config->setMessage(miframe_text('El archivo de proyecto ya existe en el servidor.'));
			}

			// Todo OK, preserva configuración actual (no guarda el path)
			$copia = $data_repo;
			unset($copia['path']);

			miframe_inifiles_save_data($file_repo, $copia, false);
			// Valida exista entrada en el repositorio de proyectos
			$filepath = miframe_path(MIFRAME_PROJECTS_REPO, strtolower($app_name) . '.path');
			file_put_contents($filepath, $base_final);
		}
	}
}

// miframe_debug_box($_REQUEST, 'REQUEST');
// miframe_debug_box($mensajes, 'MENSAJES');
// miframe_debug_box($data_proyecto, 'DATAPROYECTO');
// miframe_debug_box($data_repo, 'DATAREPO');
// miframe_debug_pause($inifile);

if ($inifile != '') {
	miframe_app()->config->createDirbase = true;
	if (!miframe_app()->config->loadData('miproyecto', $inifile, true) && !$temporal) {
		miframe_app()->router->abort(
			miframe_text('Acceso restringido al servidor'),
			miframe_text('No pudo acceder al archivo/directorio de configuración de proyecto **$1**', $inifile)
			);
	}
	miframe_app()->config->createDirbase = false;

	// Complementa los datos de "miproyecto" con la data registrada en sistema
	if (!miframe_app()->config->loadData('mirepo', $file_repo)) {
		miframe_app()->router->abort(
			miframe_text('Acceso restringido al servidor'),
			miframe_text('No pudo acceder al archivo/directorio de configuración global **$1**', $file_repo)
			);
	}

	// Datos locales (opcionales)
	// Complementa los datos de "miproyecto" con la data registrada en sistema
	$inifile = miframe_path($path_modulos, 'config', 'localconfig.ini');
	miframe_app()->config->loadData('localconfig', $inifile);
}

$m = new \miFrame\Local\AdminModules();

// Configura validadores y helpers del objeto EditConfig
miframe_app()->config->addValidator('newproject', $proyecto_nuevo);
miframe_app()->config->addValidator('notype', $notype);
miframe_app()->config->addHelper('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']);

// Configuración de campos en $file_repo
// Si está editando el repo Administrador, previene ediciones en línea
// para no modificar el archivo ya versionado.
$inifile = micode_modules_dataconfig_path('mirepo-cfg.ini');
miframe_app()->config->addConfigFile('mirepo', $inifile, ($app_name == 'micode-admin'));

// Configuración de los campos básicos de miproyecto.ini
$inifile = micode_modules_dataconfig_path('miproyecto-cfg.ini');
miframe_app()->config->addConfigFile('miproyecto', $inifile);

if (!$proyecto_nuevo) {
	// Campos de configuración propios de cada proyecto (si aplican)
	$inifile = miframe_path($path_modulos, 'data', 'localconfig-cfg.ini');
	miframe_app()->config->addConfigFile('miproyecto', $inifile);
	miframe_app()->config->setDataValue('path', micode_modules_remove_root($data_repo['path']), true);
}

// Configuración de campos asociados a tipo del proyecto (si hay tipo selecto)
if (!$notype) {
	// Path al archivo de configuración por tipo asociado
	$inifile = micode_modules_dataconfig_path("type-{$type}-cfg.ini");
	miframe_app()->config->addConfigFile('miproyecto', $inifile);
	if ($m->loadManager('basename.' . $type)) {
		// echo "$type<hr>"; print_r($m->clase_manejador); echo "<hr>";
		$m->clase_manejador->helpersConfig($this->config);
	}
}

if (count($lista_modulos) > 0) {
	// Configuración de parámetros por modulo
	$inifile = micode_modules_dataconfig_path('modules-cfg.ini');
	miframe_app()->config->addConfigFile('miproyecto', $inifile);
	$llaves = miframe_app()->config->getNames('miproyecto');

	foreach ($llaves as $k => $name) {
		$modulo = strtolower(trim(miframe_app()->config->getConfigAttrib($name, 'module')));
		// if ($modulo != '' && !in_array(strtolower(trim($modulo)), $lista_modulos)) {
		// array_key_exists will definitely tell you if a key exists in an array,
		// whereas isset will only return true if the key/variable exists and is not null.
		if ($modulo != '' && !isset($lista_modulos[$modulo])) {
			// echo "REMOVER $modulo --> $name<hr>";
			miframe_app()->config->removeConfig($name);
		}
	}
}

// Comentarios adicionales para el ini
miframe_app()->config->commentData('miproyecto', miframe_text('Archivo de configuración de proyecto'));

$since = '';
if (isset($data_repo['since'])) { $since = $data_repo['since']; }
// Fija valores definidos en los cfg como "private" o "readonly" o que requieren
// asegurar un formato especifico.
miframe_app()->config->setDataValue('project-name', $app_name, true);
miframe_app()->config->setDataValue('since', $since, true);
miframe_app()->config->setDataValue('temp-path', miframe_temp_dir(), true);

// Visualiza datos bloqueados para edición al consultar el repo Administrador
if ($app_name == 'micode-admin') {
	$mostrar = miframe_text('No');
	if (array_key_exists('minimize', $data_repo) && $data_repo['minimize']) { $mostrar = miframe_text('Si'); }
	miframe_app()->config->setDataValue('minimize', $mostrar, true);

}

// checkformRequest() captura la data recibida via POST, por ello se valida primero.
if (miframe_app()->config->checkformRequest('configok') && $app_name != '') {

	$startup = $data_repo['startup']; // Predefine con el valor existente previamente

	if ($notype) {
		$arreglo = explode('/', miframe_app()->post->getString(miframe_app()->config->formName('type'), $type) . '/');
		$type = strtolower(trim($arreglo[0]));
		$startup = strtolower(trim($arreglo[1]));
		miframe_app()->config->setDataValue('type', $type);
	}

	// Valida modulos a adicionar según el tipo de inicio
	if ($startup != '') {
		$startup_info = micode_modules_startup_data($startup);
		if (!isset($startup_info['title'])) {
			// miframe_error('No encontró información para el tipo de inicio indicado ($1/$2)', $type, $startup);
			miframe_app()->router->abort(
				miframe_text('Modelo inicial no valido'),
				miframe_text('No encontró información para el modelo de inicio indicado ($1/$2)', $type, $startup)
				);
		}
	}

	$items_guardados = 0;

	$startup_files = micode_edit_startups_files($startup, $startup_info, $data_proyecto, $path_modulos);
	foreach ($startup_files as $k => $info) {
		$destino = $info['dest'];
		$origen = $info['src'];
		$path = $info['path'];

		// echo "$origen --> $destino<hr>";

		$evalpost = miframe_app()->post->getBoolean('startup-' . md5($path));

		if ($notype || $evalpost) {
			$mensaje = '';
			if (!file_exists($destino) || $evalpost) {
				$dirname = dirname($destino);

				// Crea directorio destino
				miframe_mkdir($dirname);

				$extension = miframe_extension($origen);
				// Lee contenido del archivo si es del tipo permitido
				if ($extension == '.php') {
					$contenido = file_get_contents($origen);
					$params = miframe_app()->config->getValues() + $data_proyecto['system'];
					$params['app-modules'] = $data_repo['app-modules'];
					micode_edit_template($contenido, $params);

					if (@file_put_contents($destino, $contenido)) {
						$mensaje = miframe_text('Archivo $1 copiado y adaptado con éxito', $path);
						$items_guardados ++;
					}
					else {
						$mensaje = miframe_text('No pudo crear archivo $1', $path);
					}
				}
				else {
					// Realiza copia directa del archivo
					if (@copy($origen, $destino)) {
						$mensaje = miframe_text('Archivo $1 copiado con éxito', $path);
						$items_guardados ++;
					}
					else {
						$mensaje = miframe_text('No pudo copiar archivo $1', $path);
					}
				}
			}
			else {
				$mensaje = miframe_text('Archivo $1 ya existe en el proyecto', $path);
			}
			miframe_app()->config->setMessage($mensaje);
		}
	}

	if ($notype && isset($startup_info['modules'])) {
		$datamodules = $m->exportRemoteFiles($app_name, $startup_info['modules'], $startup);
		// Muestra resultados
		$total = 0;
		foreach ($datamodules['modules'] as $modulo => $subtotal) {
			$total += $subtotal;
		}
		if (count($datamodules['modules']) > 1) {
			miframe_app()->config->setMessage(miframe_text('Copiados en total $1 archivos durante esta actualización', $total));
		}
		miframe_app()->config->setMessage($datamodules['result']);
	}

	if ($data_proyecto['readme-path'] != ''
		&& miframe_app()->post->getBoolean('readmeok')
		) {
		// Crea archivo README.md
		$contenido = '# ' . miframe_app()->config->getSingleValue('project-title') . PHP_EOL . PHP_EOL .
			miframe_app()->config->getSingleValue('project-desc-info') . PHP_EOL . PHP_EOL .
			'---' . PHP_EOL .
			date('Y/m/d');

		$mensaje = '';
		if (file_put_contents($data_proyecto['readme-path'], $contenido)) {
			$mensaje = miframe_text('Creado archivo $1', basename($data_proyecto['readme-path']));
			$items_guardados ++;
		}
		else {
			$mensaje = miframe_text('No pudo crear archivo $1', basename($data_proyecto['readme-path']));
		}
		miframe_app()->config->setMessage($mensaje);
	}

	if (miframe_app()->config->unsaved('miproyecto')) {
		$guardado = miframe_app()->config->putData('miproyecto');
		$mensaje = '';
		if ($guardado) {
			$items_guardados ++;
			if ($proyecto_nuevo) {
				$mensaje = miframe_text('Proyecto  **$1** creado con éxito.', $app_name);
			}
			else {
				$mensaje = miframe_text('Proyecto  **$1** actualizado con éxito.', $app_name);
			}
		}
		else {
			$mensaje = miframe_text('No pudo actualizar archivo para configuración de proyecto.');
		}
		miframe_app()->config->setMessage($mensaje);
	}

	// Actualiza archivo $file_repo
	$data_repo['temporal'] = false;
	$data_repo['type'] = $type;
	$data_repo['startup'] = $startup;
	$data_repo['minimize'] = miframe_app()->config->getSingleValue('minimize');

	foreach ($data_repo as $k => $v) {
		miframe_app()->config->setDataValue($k, $v);
	}
	// miframe_debug_box($data_repo, 'UNSAVED?');
	if (miframe_app()->config->unsaved('mirepo')) {
		$guardado = miframe_app()->config->putData('mirepo', [ 'path' ]);
		if ($guardado) {
			$items_guardados ++;
		}
		else {
			miframe_error('No pudo habilitar archivo de proyecto $1', miframe_app()->config->getFilename('mirepo'));
		}
	}

	/*
	$guardado = miframe_app()->config->putData('modulos');
	if ($guardado > 0) {
		$mensajes[] = miframe_text('Actualizado archivo de configuración para parámetros de módulos.');
	}
	elseif ($guardado === false) {
		$mensajes[] = miframe_text('No pudo actualizar archivo de configuración para parámetros de módulos.');
	}
	*/

	if ($items_guardados <= 0) {
		miframe_app()->config->setMessage(miframe_text('Nada que actualizar'));
	}
	else {
		// Envia a detalle (fija $_REQUEST['app'] para que sea capturado al invocar $Router->param)
		$cmd = 'projects-info';
		$params = array( 'app' => $app_name );
		// Crea pagina a recargar
		$enlace = $this->reload($cmd, $params);
	}
}

// Revisa archivos a actualizar
$actualizar_files = array();
if ($data_proyecto['readme-path'] != '') {
	if (!file_exists($data_proyecto['readme-path'])) {
		$actualizar_files['readmeok'] = miframe_text('Adicionar archivo *README.md*');
	}
	else {
		$actualizar_files['readmeok'] = miframe_text('Restaurar archivo *README.md*');
	}
}

// Archivos del starup
if (isset($data_proyecto['startup'])
	&& is_array($data_proyecto['startup'])
	&& isset($data_proyecto['startup']['files'])
	) {
	$startup = $data_proyecto['mirepo']['startup'];
	$startup_info = $data_proyecto['startup'];
	$startup_files = micode_edit_startups_files($startup, $startup_info, $data_proyecto, $path_modulos);
	foreach ($startup_files as $k => $info) {
		$destino = $info['dest'];
		$origen = $info['src'];
		$path = $info['path'];
		if (file_exists($destino)) {
			$info_origen = filemtime($origen) . '/' . filesize($origen);
			$info_destino = filemtime($destino) . '/' . filesize($destino);
			if ($info_origen != $info_destino) {
				// echo "$origen --> $destino<hr>";
				$actualizar_files['startup-' . md5($path)] = miframe_text('Modelo Inicial: Restaurar *$1*', $path);
			}
		}
		else {
			$actualizar_files['startup-' . md5($path)] = miframe_text('Modelo Inicial: Crear *$1*', $path);
			// echo "NO EXISTE $destino<hr>";
		}
	}
}

$data_proyecto['update-files'] = $actualizar_files;

// miframe_debug_box(miframe_app()->config->getConfigFiles(true));

miframe_app()->startView('projects/edit.php', $data_proyecto);