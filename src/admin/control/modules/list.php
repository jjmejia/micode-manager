<?php
/**
 * Administrador de miFrame - Listar modulos.
 *
 * @author John Mejia
 * @since Abril 2022
 */

$type = $this->router->param('type', 'php');
if (!micode_modules_eval_type($type)) {
	$this->router->abort(
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
		$basename = urlencode($modulo);
		// $tipo_encode = urlencode($data['type']);
		$urlbase = "modules/detail?module={$basename}";
		$listado[$modulo]['url'] = $this->router->getFormAction($urlbase, true);
		$listado[$modulo]['dirbase'] = $m->getDirBase($modulo);
		// $listado[$modulo]['dirdest'] = $m->getDirRemote($modulo);
	}
	else {
		unset($listado[$modulo]);
	}
}

$this->startView('modules/list.php', [ 'listado' => $listado, 'type' => $type, 'tiposvalidos' => $tiposvalidos ]);
