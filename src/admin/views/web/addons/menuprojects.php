<?php
/**
 * Menú principal.
 *
 * @author John Mejía
 * @since Diciembre 2022
 */

$cfg_type = 'mirepo->type';
$cfg_name = 'config->project-name';
$cfg_nuevo = '';

$cfg_type = $this->view->param($cfg_type);
$cfg_name = $this->view->param($cfg_name);
$cfg_nuevo = $this->view->param($cfg_nuevo, false);
$data_repo = $this->view->param('mirepo', false);

$arreglo = array();

if (!$cfg_nuevo) {
	$arreglo = array(
		'projects/info/' . $cfg_name => miframe_text('Detalles'),
		'projects/edit/' . $cfg_name => miframe_text('Editar'),
	);

	if ($cfg_type != '' && $cfg_type != '?') {
		// $arreglo['projects/config/' . $cfg_name] = 'Configuración';
		$arreglo['projects/modules/' . $cfg_name] = miframe_text('Módulos');
		$arreglo['projects/explore/' . $cfg_name] = miframe_text('Explorar directorio');
		$arreglo['projects/packs/' . $cfg_name] = miframe_text('Paquetes');
		// $arreglo['projects/logs/' . $cfg_name] = 'Logs';
	}
}

$tituloppal = miframe_text('Proyecto') . ' ' . htmlspecialchars($cfg_name);
if ($cfg_nuevo) { $tituloppal = 'Nuevo proyecto'; }

// Enlaces
$enlaces = array();
$cmd = cmdPost($this->post);

foreach ($arreglo as $llave => $titulo) {
	$enlace = $this->router->getFormAction($llave, true);
	$enlaces[] = array('url' => $enlace, 'titulo' => $titulo, 'selecto' => ($cmd == $llave));
}

if (!$cfg_nuevo) {
	// Valida si existe el index.php (pendiente definir alterno)
	$enlace = micode_modules_enlace($cfg_name, $data_repo);
	if ($enlace != '') {
		$md5 = miframe_mask($cfg_name, 'w');
		$enlaces[] = array('url' => $enlace, 'titulo' => 'Abrir en ventana nueva', 'selecto' => false, 'target' => $md5);
	}
}

menuApps($this->view, $tituloppal, $enlaces);
