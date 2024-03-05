<?php
/**
 * Administrador de miFrame - Listar modulos.
 *
 * @author John Mejia
 * @since Abril 2022
 */

$type = miframe_app()->router->param('type', 'php');
if (!micode_modules_eval_type($type)) {
	miframe_app()->router->abort(
			miframe_text('Parámetros incorrectos'),
			miframe_text('Tipo de módulos a recuperar no es valido ($1).', $type)
			);
}

$m = new \miFrame\Local\AdminModules(true);

$listado = $m->getAllModules();
$tiposvalidos = array();

foreach ($listado as $modulo => $data) {
	$tiposvalidos[$data['type']][] = $modulo;
	if ($data['type'] == $type) {
		$listado[$modulo]['url'] = miframe_app()->router->createRouteURL('modules-detail', [ 'module' => $modulo ]);
		$listado[$modulo]['dirbase'] = $m->getDirBase($modulo);
	}
	else {
		unset($listado[$modulo]);
	}
}

miframe_app()->startView('modules/list.php', [ 'listado' => $listado, 'type' => $type, 'tiposvalidos' => $tiposvalidos ]);
