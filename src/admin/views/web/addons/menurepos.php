<?php
/**
 * Menú principal.
 *
 * @author John Mejía
 * @since Diciembre 2022
 */

$cfg_module = $this->params->get('reponame');
$tituloppal = '';
if ($this->params->exists('nuevo')) {
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
$tiposvalidos = $this->params->get('tiposvalidos');
foreach ($validos as $tipo => $titulo) {
	if (is_array($tiposvalidos) && isset($tiposvalidos[$tipo])) {
		$titulo .= ' (' . count($tiposvalidos[$tipo]) . ')';
		$enlace = $this->router->getFormAction('repositories/detail/' . $cfg_module . '/' . $tipo, true);
		$enlaces[] = array('url' => $enlace, 'titulo' => $titulo, 'selecto' => ($this->params->get('type') == $tipo));
	}
}


$enlace = $this->router->getFormAction('repositories/list', true);

menuApps($this->router, $tituloppal, $enlaces, "<a href=\"{$enlace}\">" . miframe_text('Listado de Repositorios') . "</a>");
