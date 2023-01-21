<?php
/**
 * Administrador de miFrame - Listar modulos.
 *
 * @author John Mejia (C) Abril 2022.
 */


// opcion para reconstruir solo los modulos modificados
// el nombre del parametro rebuildxxx debiera fijarse aca para evitar inconvenientes
// y asi con todos los parametros

$app_name = strtolower($this->router->param('app'));
if ($app_name == '') {
	$this->router->abort(
		miframe_text('Parámetros incompletos'),
		miframe_text('No se ha definido nombre del Proyecto a actualizar')
		);
}

$m = new \miFrame\Local\AdminModules();

$data_proyecto = micode_modules_project_data($app_name, false, $m);
$data_repo =& $data_proyecto['mirepo'];
$path_modulos = micode_modules_path($app_name, true, $data_repo);
$type = $data_proyecto['mirepo']['type'];

if ($path_modulos == '') {
	$this->router->abort(
		miframe_text('Acceso restringido al servidor'),
		miframe_text('No pudo crear directorio para copiar los archivos al proyecto **$1**', $app_name)
		);
}

// Mensajes a mostrar en pantalla
$mensajes = array();

/*
// REVISAR ESTE MANEJO!!!
// Archivo para control de versiones con los listados en "pre"
$inifile = miframe_path($path_modulos, '..', 'config', 'mvc.ini');

$modificados = array();

if (file_exists($inifile)) {
	// Valida listado actual
	$data_proyecto['modules']['changes'] = micode_modules_versioncontrol($inifile, $data_proyecto['modules']['pre'], true);
	$data_proyecto['ini_datetime'] = filemtime($inifile);
}
*/

// Evalua $_REQUEST.
// Acciones:
// - removido de "pre" lo pasa a "new"
// - seleccionado en "new" lo pasa a "pre" y crea archivos locales
// - seleccionado en "add" lo pasa a "pre" y crea archivos locales
// - seleccionado en "del" elimina directorio local (ya no existen en el repositorio global)

// PENDIENTE: Actualizar la configuración del proyecto
// $post = new \miFrame\Interface\Request();

$proceder = ($this->post->getString('modulok') != '');

//**********************************************************
// CODIGO A REUSAR AL CREAR PROYECTOS!!

$data_proyecto_add = &$data_proyecto['modules']['add'];

// POST (modulok) se usa como control
if ($proceder || count($data_proyecto_add) > 0) {

	$user['pre'] = $this->post->getArray('pre');
	$user['new'] = $this->post->getArray('new');
	$user['del'] = $this->post->getArray('del');

	// Alias para elementos de $data_proyecto
	$data_proyecto_pre = &$data_proyecto['modules']['pre'];
	$data_proyecto_new = &$data_proyecto['modules']['new'];
	$data_proyecto_del = &$data_proyecto['modules']['del'];

	if (!$proceder) {
		// No ha enviado datos del formulario, redefine $user para evitar retirar modulos por accidente.
		$user['pre'] = array_keys($data_proyecto_pre);
		$user['del'] = array_keys($data_proyecto_del);
	}

	$requeridos = array();
	$siempre = array();

	// Reconstruir los archivos actuales (si desmarca alguno de los instalados, no lo incluye en la
	// actualización).
	$rebuildall = strtolower($this->post->getString('rebuild'));
	if ($rebuildall != '') {
		foreach ($user['pre'] as $k => $modulo) {
			if ($rebuildall == 'all' || (
				$rebuildall == 'changed'
				&& isset($data_proyecto_pre[$modulo])
				&& isset($data_proyecto_pre[$modulo]['changed'])
				&& $data_proyecto_pre[$modulo]['changed'] !== false
				)) {
				$listado = $m->getAllModules('', $modulo);
				$user['new'][] = $modulo;
				$data_proyecto_new[$modulo] = $listado[$modulo];
				// Remueve item del "pre"
				unset($user['pre'][$k]);
			}
		}
	}

	// Revisa los items en "pre" y "add" para validar que se hayan incluido todos los "uses" relacionados
	$add = array('pre', 'new');
	foreach ($add as $a => $tipo) {
		// Complementa "pre" y "new"
		foreach ($user[$tipo] as $k => $modulo) {
			// miframe_debug_box($user[$tipo], $tipo . '/' . $k);
			// $modulo = $user[$tipo][$k];
			$listado = $m->getAllModules('', $modulo);
			if (isset($listado[$modulo])) {
				if (count($listado[$modulo]['uses']) > 0) {
					foreach ($listado[$modulo]['uses'] as $p => $umodulo) {
						if (!in_array($umodulo, $user['pre']) && !in_array($umodulo, $user['new'])) {
							// Valida si ya está registradp
							if (isset($data_proyecto_pre[$umodulo])) {
								$user['pre'][] = $umodulo;
								$mensajes[] = miframe_text('Mantiene $1, requerido por $2.', $umodulo, $modulo);
							}
							else {
								$user['new'][] = $umodulo;
								$mensajes[] = miframe_text('Incluye $1, requerido por $2.', $umodulo, $modulo);
							}
						}
					}
				}
			}
			else {
				// Remueve elemento
				$mensajes[] = miframe_text('Módulo $1 no encontrado.', $modulo);
				unset($user[$tipo][$k]);
			}
		}
		// Recupera archivos
		foreach ($user[$tipo] as $k => $modulo) {
			// Recupera los archivos requeridos
			$requeridos_local = $m->getRequiredFiles($modulo);
			foreach ($requeridos_local as $basename => $path) {
				$dmodulo = $m->getDirRemote($modulo, '', $basename);
				if ($tipo == 'new') { $siempre[$dmodulo] = true; }
				$requeridos[$dmodulo] = $path;
			}
			// Adiciona listado a los nuevos
			if (isset($data_proyecto_new[$modulo])) {
				$data_proyecto_new[$modulo]['files'] = array_keys($requeridos_local);
			}
		}
	}

	// Los items en $data_proyecto_add deben ser adicionados
	foreach ($data_proyecto_add as $k => $modulo) {
		if (!in_array($modulo, $user['pre']) && !in_array($modulo, $user['new'])) {
			$user['new'][] = $modulo;
			$mensajes[] = miframe_text('Incluye $1, requerido por el sistema.', $modulo);
		}
	}

	// Los items preinstalados que no aparezcan en "pre" deben ser removidos?
	// SEP/2022: Ya no se detectan modulos "retirados" toda vez que no se explora cada
	// archivo en los directorios. La razón es que el usuario podría tener archivos
	// adicionados o los "require" pueden copiar archivos individuales dentro de un paquete.
	// Aun asi pueden aparecer modulos a retirar, de modulos que hayan sido retirados pero
	// estén instalados en cliente.

	$eliminar = array();
	foreach ($data_proyecto_del as $modulo => $info) {
		if (!in_array($modulo, $user['del'])) {
			$requeridos_del = $info['files'];
			// Valida que exista el archivo en el directorio de proyecto y que no esté en los
			// requeridos de otros proyectos
			if (is_array($requeridos_del)) {
				foreach ($requeridos_del as $dmodulo) {
					$dmodulo = $m->getDirRemote($modulo, '', $dmodulo);
					$filename = miframe_path($path_modulos, $dmodulo);
					if (!isset($requeridos[$dmodulo]) && file_exists($filename)) {
						$eliminar[$dmodulo] = $filename;
					}
				}
			}
			// Remueve modulo para no incluirlo en el .ini
			unset($data_proyecto_del[$modulo]);
		}
	}

	$ordenar = false;
	// Hace lo mismo para los "pre" que no esten selectos
	foreach ($data_proyecto_pre as $modulo => $info) {
		if (!in_array($modulo, $user['pre'])) {
			$requeridos_del = $info['files'];
			if (is_array($requeridos_del)) {
				foreach ($requeridos_del as $dmodulo) {
					$dmodulo = $m->getDirRemote($modulo, '', $dmodulo);
					$filename = miframe_path($path_modulos, $dmodulo);
					if (!isset($requeridos[$dmodulo]) && file_exists($filename)) {
						$eliminar[$dmodulo] = $filename;
					}
				}
			}
			// Remueve elemento, lo mueve a "disponibles"
			if (!isset($data_proyecto_new[$modulo])) {
				$data_proyecto_new[$modulo] = $data_proyecto_pre[$modulo];
			}
			unset($data_proyecto_pre[$modulo]);
			$ordenar = true;
		}
	}

	if ($ordenar) {
		ksort($data_proyecto_new);
	}

	// Valida de los requeridos, cuáles faltan por instalar
	foreach ($requeridos as $dmodulo => $filebase) {
		$filename = miframe_path($path_modulos, $dmodulo);
		if (!isset($siempre[$dmodulo]) && file_exists($filename)) {
			unset($requeridos[$dmodulo]);
		}
	}

	// Elimina archivos no requeridos
	$eliminados = 0;
	foreach ($eliminar as $modulo => $filename) {
		if (micode_modules_remove($modulo, $filename)) {
			$eliminados++;
		}
		else {
			$mensajes[] = miframe_text('No pudo eliminar archivo $1', $modulo);
		}
	}
	if ($eliminados > 0) {
		$mensajes[] = miframe_text('Retirados $1 archivos (de $2)', $eliminados, count($eliminar));
	}

	$ordenar = false;
	// Adiciona modulos nuevos
	foreach ($user['new'] as $k => $modulo) {
		if (isset($data_proyecto_new[$modulo])) {
			if (!isset($data_proyecto_pre[$modulo]) || $rebuildall) {
				// Valida, en caso que sea un pre (rebuild-all)
				$mensajes[] = miframe_text('Adiciona $1 con éxito', $modulo);
			}
			else {
				$mensajes[] = miframe_text('Actualiza $1 con éxito', $modulo);
			}

			$data_proyecto_pre[$modulo] = $data_proyecto_new[$modulo];
			unset($data_proyecto_new[$modulo]);
			$ordenar = true;
		}
		else {
			$mensajes[] = miframe_text('Modulo $1 no existe.', $modulo);
		}
	}

	if ($ordenar) {
		ksort($data_proyecto_pre);
	}

	// Adiciona requeridos
	$creados = 0;
	foreach ($requeridos as $modulo => $origen) {
		$destino = miframe_path($path_modulos, $modulo);
		// echo "$destino<hr>";
		$resultado = false;
		$infoerror = '';
		if ($m->loadManager($origen)) {
			$resultado = $m->clase_manejador->exportWorkCopy($modulo, $origen, $destino);
			// Valida si ocurrió un error y no pudo realizar el cambio
			$infoerror = $m->clase_manejador->getLastError();
		}
		else {
			// Copia el archivo manualmente
			$resultado = @copy($origen, $destino);
			$infoerror = miframe_text('Error al copiar archivo');
		}
		if ($resultado) {
			$creados ++;
		}
		else {
			if ($infoerror != '') { $infoerror = ' (' . $infoerror . ')'; }
			$mensajes[] = miframe_text('Falló adición del módulo $1 $2', $modulo, $infoerror);
		}
	}

	if ($creados > 0) {
		$mensajes[] = miframe_text('Copiados $1 archivos (de $2)', $creados, count($requeridos));
	}

	// Actualiza control de versiones
	if ($m->updateRemoteModules($data_proyecto_pre + $data_proyecto_del)) {
		$mensajes[] = miframe_text('Archivo para control de versiones creado/actualizado.');
		$data_proyecto['modules']['changes'] = 0;
		$data_proyecto['ini_datetime'] = time(); // Recien actualizado
	}
	else {
		$mensajes[] = miframe_text('No pudo crear archivo para control de versiones.');
	}
}

$data_proyecto['mensajes'] = $mensajes;

$this->startView('projects/modules.php', $data_proyecto);