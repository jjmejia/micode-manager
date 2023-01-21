<?php
/**
 * Menú principal.
 *
 * @author John Mejía
 * @since Diciembre 2022
 */

$cfg_module = $this->view->param('reponame');
$tituloppal = '';
if ($this->view->param('nuevo', false) !== false) {
	$tituloppal = miframe_text('Nuevo Repositorio');
}
else {
	$tituloppal = miframe_text('Repositorio $1', htmlspecialchars($cfg_module));
}

// Enlaces
$enlaces = array();
$cmd = cmdPost($this->post, 'name');

// Recupera tipos validos
$validos = micode_modules_types();
$tiposvalidos = $this->view->param('tiposvalidos');
foreach ($validos as $tipo => $titulo) {
	if (is_array($tiposvalidos) && isset($tiposvalidos[$tipo])) {
		$titulo .= ' (' . count($tiposvalidos[$tipo]) . ')';
		$enlace = $this->router->getFormAction('repositories/detail/' . $cfg_module . '/' . $tipo, true);
		$enlaces[] = array('url' => $enlace, 'titulo' => $titulo, 'selecto' => ($this->view->param('type') == $tipo));
	}
}

/*
foreach ($arreglo as $llave => $titulo) {
	$enlace = $this->router->getFormAction($llave, true);
	// $comparar = explode('?', $llave);
	$enlaces[] = array('url' => $enlace, 'titulo' => $titulo, 'selecto' => ($cmd == $llave));
	// echo "$cmd -- {$llave}<hr>";
}
*/

$enlace = $this->router->getFormAction('repositories/list', true);

menuApps($this->view, $tituloppal, $enlaces, "<a href=\"{$enlace}\">" . miframe_text('Listado de Repositorios') . "</a>");
