<?php
/**
 * Menú principal.
 *
 * @author John Mejía
 * @since Diciembre 2022
 */

$cfg_type = $this->params->get('type');
$cfg_module = $this->params->get('module');
$paths = explode('/', $cfg_module);

$arreglo = array(
	'modules/detail?module=' . $cfg_module => miframe_text('Información'),
	'modules/edit?module=' . $cfg_module => miframe_text('Editar'),
	'modules/explore?module=' . $cfg_module => miframe_text('Explorar directorio')
	);

$tituloppal = '';
$enlaces = array();
$enlace_modulos = '';

if (!$this->params->get('nuevo:bool')) {
	$tituloppal = miframe_text('Módulo $1', htmlspecialchars($cfg_module));
	// Enlaces
	$enlaces = array();
	$cmd = cmdPost($this->post);

	foreach ($arreglo as $llave => $titulo) {
		$enlace = $this->router->getFormAction($llave, true);
		$comparar = explode('?', $llave);
		$enlaces[] = array('url' => $enlace, 'titulo' => $titulo, 'selecto' => ($cmd == $comparar[0]));
		// echo "$cmd -- {$comparar[0]}<hr>";
	}

	// Enlace a página de módulos
	$enlace_modulos = 'modules/list';
	if ($cfg_type != '') {
		$enlace_modulos .= '/' . $cfg_type;
	}
	$enlace = $this->router->getFormAction($enlace_modulos, true);
	$enlace_modulos = "<a href=\"{$enlace}\">" . miframe_text('Listado de Módulos') . "</a> | ";
}
else {
	$repo_name = $this->router->param('name');
	// $paths[0] contiene el nombre del repositorio, lo toma del "Router"
	// (en este caso se invoca desde "modules/create/[repositorio]")
	$paths = array($repo_name);
	$tituloppal = miframe_text('Nuevo Módulo para repositorio $1', $repo_name);
}

$enlace_repo = $this->router->getFormAction('repositories/detail/' . urlencode($paths[0]) . '/' . $cfg_type, true);

menuApps($this->router, $tituloppal, $enlaces,
	$enlace_modulos .
	"<a href=\"{$enlace_repo}\">" . miframe_text('Repositorio $1', $paths[0]) . "</a>"
);
