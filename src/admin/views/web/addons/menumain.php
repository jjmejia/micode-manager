<?php
/**
 * Menú principal.
 *
 * @author John Mejía
 * @since Diciembre 2022
 */

$arreglo = array(
	'' => miframe_text('Proyectos'),
	'repositories-list' => miframe_text('Repositorios'),
	'modules-list' => miframe_text('Módulos'),
	'localtests-list' => miframe_text('Tests'),
	'settings' => miframe_text('Configuración')
);

// Se asegura que haya configurado "sistema.ini"
if (miframe_app()->userEmail() == '') {
	$arreglo = array('settings' => 'Configuración');
}

$enlaces = array();

foreach ($arreglo as $alias => $titulo) {
	$enlace = miframe_app()->router->createRouteURL($alias);
	$enlaces[] = array('url' => $enlace, 'titulo' => $titulo, 'selecto' => (miframe_app()->router->selectedRoute() == $alias));
}

menuApps('', $enlaces);
