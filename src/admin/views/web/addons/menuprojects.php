<?php
/**
 * Menú principal.
 *
 * @author John Mejía
 * @since Diciembre 2022
 */

$cfg_type = miframe_app()->params->get('mirepo->type');
$cfg_name = miframe_app()->params->get('config->project-name');
$cfg_nuevo = miframe_app()->params->get('nuevo:bool');
$data_repo = miframe_app()->params->get('mirepo', false);

$arreglo = array();

if (!$cfg_nuevo) {
	$arreglo = array(
		'projects-info' => miframe_text('Detalles'),
		'projects-edit' => miframe_text('Editar'),
	);

	if ($cfg_type != '' && $cfg_type != '?') {
		// $arreglo['projects/config/' . $cfg_name] = 'Configuración';
		$arreglo['projects-modules'] = miframe_text('Módulos');
		$arreglo['projects-explore'] = miframe_text('Explorar directorio');
		$arreglo['projects-packs'] = miframe_text('Paquetes');
		// $arreglo['projects/logs/' . $cfg_name] = 'Logs';
	}
}

$tituloppal = miframe_text('Proyecto $1', htmlspecialchars($cfg_name));
if ($cfg_nuevo) { $tituloppal = 'Nuevo proyecto'; }

// Enlaces
$enlaces = array();

foreach ($arreglo as $alias => $titulo) {
	$enlace = miframe_app()->router->createRouteURL($alias, [ 'app' => $cfg_name ]);
	$enlaces[] = array('url' => $enlace, 'titulo' => $titulo, 'selecto' => (miframe_app()->router->selectedRoute() == $alias));
}

if (!$cfg_nuevo) {
	// Valida si existe el index.php (pendiente definir alterno)
	$enlace = micode_modules_enlace($cfg_name, $data_repo);
	if ($enlace != '') {
		$md5 = miframe_mask($cfg_name, 'w');
		$enlaces[] = array('url' => $enlace, 'titulo' => 'Abrir en ventana nueva', 'selecto' => false, 'target' => $md5);
	}
}

menuApps($tituloppal, $enlaces);
