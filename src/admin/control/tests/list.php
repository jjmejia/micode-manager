<?php
/**
 * Administrador de Tests.
 *
 * @author John Mejia
 * @since Mayo 2022
 */

include_once MIFRAME_BASEDIR . '/lib/modules/tests.php';

$type = $this->router->param('type', 'php');
micode_modules_eval_type($type);

$listado = micode_modules_tests($type);

$this->startView('tests/list.php', [ 'listado' => $listado, 'type' => $type ]);
