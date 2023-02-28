<?php
/**
 * Configuración del sistema.
 *
 * @author John Mejía
 * @since Diciembre 2022.
 */

$this->startEditConfig();

// Datos del sistema actuales
$inifile = miframe_path(MIFRAME_LOCALCONFIG_PATH, 'sistema.ini');
$desde_inicio = !file_exists($inifile); // TRUE si no existe "sistema.ini"
$this->config->loadData('sistema', $inifile);

// Configuración de los campos de sistema.ini
$inifile = micode_modules_dataconfig_path('sistema-cfg.ini');
$this->config->addConfigFile('sistema', $inifile);

// Opciones asociadas a los modulos
// POST (configok) se usa como control
if ($this->config->checkformRequest('configok')) {

	// miframe_debug_request_box(); exit;
	$mensaje = '';
	if ($this->config->unsaved('sistema')) {
		$guardado = $this->config->putData('sistema');
		if ($guardado) {
			$mensaje = miframe_text('Configuración actualizada con éxito.');
			if ($desde_inicio) {
				// Adiciona mensaje para recargar página
				$this->config->setMessage($mensaje);
				$enlace = $this->router->getFormAction('index.php', true);
				$mensaje = miframe_text('Recargue la página o <a href="$1">haga click aquí para continuar</a>.', $enlace);
			}
		}
		else {
			$mensaje = miframe_text('No pudo actualizar Configuración.');
		}
	}
	else {
		$mensaje = miframe_text('Nada que actualizar');
	}
	$this->config->setMessage($mensaje);
}

$data_proyecto = array('desde-inicio' => $desde_inicio);

$this->startView('projects/edit.php', $data_proyecto);