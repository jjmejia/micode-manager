<?php
/**
 * Administrador de Tests.
 *
 * @author John Mejia
 * @since Mayo 2022
 */

include_once MIFRAME_BASEDIR . '/lib/modules/tests.php';

$type = miframe_app()->router->param('type', 'php');
if (!micode_modules_eval_type($type)) {
	miframe_app()->router->abort(
			miframe_text('Parámetros incorrectos'),
			miframe_text('Tipo de módulos a recuperar no es valido ($1).', $type)
			);
}

$listado = micode_modules_tests($type);

miframe_app()->startView('tests/list.php', [ 'listado' => $listado, 'type' => $type ]);
