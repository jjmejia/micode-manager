<?php
/**
 * Administrador de Tests.
 *
 * @author John Mejia
 * @since Mayo 2022
 */

include_once MIFRAME_BASEDIR . '/lib/modules/tests.php';

$type = $this->router->param('type', 'php');
if (!micode_modules_eval_type($type)) {
	$this->router->abort(
			miframe_text('Parámetros incorrectos'),
			miframe_text('Tipo de módulos a recuperar no es valido ($1).', $type)
			);
}

$listado = micode_modules_tests($type);

$this->startView('tests/list.php', [ 'listado' => $listado, 'type' => $type ]);
