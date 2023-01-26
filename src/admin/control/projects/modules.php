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

// Evalua $_REQUEST.
// Acciones:
// - removido de "pre" lo pasa a "new"
// - seleccionado en "new" lo pasa a "pre" y crea archivos locales
// - seleccionado en "add" lo pasa a "pre" y crea archivos locales
// - seleccionado en "del" elimina directorio local (ya no existen en el repositorio global)

$proceder = ($this->post->getString('modulok') != '');

//**********************************************************

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
	$data_proyecto_remoto = array(); // Acumula modulos de "pre" y "new"

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
			$listado = $m->getAllModules('', $modulo);
			if (isset($listado[$modulo])) {
				if (count($listado[$modulo]['uses']) > 0) {
					foreach ($listado[$modulo]['uses'] as $p => $umodulo) {
						if (!in_array($umodulo, $user['pre']) && !in_array($umodulo, $user['new'])) {
							// Valida si ya está registrado
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
	}

	// Los items en $data_proyecto_add deben ser adicionados (actualiza $user['new']
	// para su siguiente visualización)
	foreach ($data_proyecto_add as $k => $modulo) {
		if (!in_array($modulo, $user['pre']) && !in_array($modulo, $user['new'])) {
			$user['new'][] = $modulo;
			$mensajes[] = miframe_text('Incluye $1, requerido por el sistema.', $modulo);
		}
	}

	// $eliminar = array();
	$ordenar = false;
	// Hace lo mismo para REMOVER los "pre" que YA no esten selectos
	foreach ($data_proyecto_pre as $modulo => $info) {
		if (!in_array($modulo, $user['pre']) && !in_array($modulo, $user['new'])) {
			// $requeridos_del = $info['files'];
			// if (is_array($requeridos_del)) {
			// 	foreach ($requeridos_del as $dmodulo) {
			// 		$dmodulo = $m->getDirRemote($modulo, '', $dmodulo);
			// 		$filename = miframe_path($path_modulos, $dmodulo);
			// 		if (!isset($requeridos[$dmodulo]) && file_exists($filename)) {
			// 			$eliminar[$dmodulo] = $filename;
			// 		}
			// 	}
			// }
			$requeridos['modulos-del'][$modulo] = true;
		}
	}
	// Adiciona todos los modulos nuevos (los "pre" se mantienen, no hay nada que hacerles)
	foreach ($user['new'] as $k => $modulo) {
		$requeridos['modulos-add'][$modulo] = true;
		// Si estaba en lista para eliminar, lo retira
		if (isset($requeridos['modulos-del'][$modulo])) {
			$mensajes[] = miframe_text('Módulo $1 no puede ser removido, es requerido por el proyecto.', $modulo);
			unset($requeridos['modulos-del'][$modulo]);
		}
	}

	/*
	PENDIENTE REVISAR! ESTOS SON MODULOS QUE NO EXISTEN Y SE DEBE VALIDAR EL LISTADO DE ARCHIVOS A ELIMINAR!
	// NO elimina cualquier modulo que se encuentre en $requeridos['modulos-add'].
	// $data_proyecto_del contiene los módulos QUE YA NO EXISTEN EN LOS REPOSITORIOS.
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
	*/

	$modulos_eliminados = array();
	// Elimina archivos previos
	if (isset($requeridos['modulos-del'])) {
		$eliminar = $m->exportRemoteFiles($app_name, array_keys($requeridos['modulos-del']), '', true);
		// print_r($requeridos); print_r($eliminar); exit;
		// Elimina archivos no requeridos.
		// "src" indica el archivo original (repositorio) y "dest" el destino en el proyecto,
		// es decir, el archivo a eliminar.
		$eliminados = 0;
		foreach ($eliminar as $filereq) {
			if (micode_modules_remove($filereq['dest'])) {
				$eliminados++;
				if (!array_key_exists($filereq['module'], $modulos_eliminados)) {
					// Si ya existe, respeta el valor actual, sea true o false (ocurrión un error)
					// FALSE para remover el modulo.
					$modulos_eliminados[$filereq['module']] = false;
				}
			}
			else {
				$mensajes[] = miframe_text('Módulo **$1**: No pudo eliminar archivo $2.', $filereq['module'], $filereq['dest']);
				// TRUE para mantener el modulo.
				$modulos_eliminados[$filereq['module']] = true;
			}
		}
		$total_modulos = 0;
		foreach ($modulos_eliminados as $modulo => $resultado) {
			if (!$resultado) {
				$mensajes[] = miframe_text('Módulo **$1** eliminado del proyecto.', $modulo);
				// Remueve elemento, lo mueve a "disponibles"
				if (!isset($data_proyecto_new[$modulo]) && isset($data_proyecto_pre[$modulo])) {
					$data_proyecto_new[$modulo] = $data_proyecto_pre[$modulo];
				}
				if (isset($data_proyecto_pre[$modulo])) {
					unset($data_proyecto_pre[$modulo]);
				}
				$ordenar = true;
				$total_modulos ++;
			}
			else {
				$mensajes[] = miframe_text('Módulo **$1**: No pudo remover todos los archivos asociados.', $modulo);
				unset($modulos_eliminados[$modulo]);
			}
		}
		if ($eliminados > 0) {
			$mensajes[] = miframe_text('Retirados $1 archivos (de $2) usados en $3 módulos.',
				$eliminados,
				count($eliminar),
				$total_modulos
				);
			// Actualiza archivo .ini (aunque pueda que sea actualizado de nuevo al adicionar módulos)
			if (!$m->updateRemoteModules($modulos_eliminados, $app_name, true)) {
				$mensajes[] = miframe_text('Archivo para control de versiones: No pudo actualizar módulos retirados.');
			}
			else {
				$mensajes[] = miframe_text('Archivo para control de versiones actualizado (módulos retirados).');
				$data_proyecto['ini_datetime'] = time(); // Recien actualizado
			}
		}
	}
	// Archivos a adicionar
	if (isset($requeridos['modulos-add'])) {
		$datamodules = $m->exportRemoteFiles($app_name, array_keys($requeridos['modulos-add']));
		$total = 0;
		foreach ($datamodules['modules'] as $modulo => $subtotal) {
			if ($subtotal > 1) {
				$mensajes[] = miframe_text('Módulo **$1** instalado con éxito ($2 archivos)', $modulo, $subtotal);
			}
			else {
				$mensajes[] = miframe_text('Módulo **$1** instalado con éxito (1 archivo)', $modulo);
			}
			$total += $subtotal;
			// Actualiza listados
			if (isset($data_proyecto_new[$modulo])) {
				$data_proyecto_pre[$modulo] = $data_proyecto_new[$modulo];
				unset($data_proyecto_new[$modulo]);
				$ordenar = true;
			}
		}

		if (count($datamodules['modules']) > 1) {
			$mensajes[] = miframe_text('Copiados en total $1 archivos durante esta actualización', $total);
		}
		if ($datamodules['result'] != '') {
			$mensajes[] = $datamodules['result'];
		}
		if ($datamodules['ini-installed']) {
			$data_proyecto['modules']['changes'] = 0;
			$data_proyecto['ini_datetime'] = time(); // Recien actualizado
		}
	}

	if ($ordenar) {
		ksort($data_proyecto_new);
		ksort($data_proyecto_pre);
	}
}

$data_proyecto['mensajes'] = $mensajes;

$this->startView('projects/modules.php', $data_proyecto);