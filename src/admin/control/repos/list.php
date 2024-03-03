<?php
/**
 * Administrador de miFrame - Listar repositorios.
 *
 * @author John Mejia
 * @since Enero 2023
 */

$m = new \miFrame\Local\AdminModules(true);

// Captura todos los modulos
$m->getAllModules();

$listado = $m->getAllRepos();

foreach ($listado as $clase => $data) {
	$listado[$clase]['url'] = $this->router->createRouteURL('repositories-detail', [ 'name' => $clase, 'type' => '' ]);
	$listado[$clase]['dirbase'] = miframe_path($_SERVER['DOCUMENT_ROOT'], $data['path']);
}

$data_proyecto = array( 'listado' => $listado );
// Valida valores fijados por ej. al crear proyecto
// if (isset($mensajes)) { $data_proyecto['mensajes'] = $mensajes; }
$data = $this->router->getDataReloaded(true);
if ($data !== false && is_array($data) && isset($data['msg'])) {
	$data_proyecto['mensajes'] = $data['msg'];
}

$this->startView('repos/list.php', $data_proyecto);