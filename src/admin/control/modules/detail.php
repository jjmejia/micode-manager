<?php
/**
 * Detalle de modulos.
 *
 * @author John Mejia
 * @since Abril 2022
 */

// Modulo selecto
$modulo = $this->post->getString('module');
$file = $this->post->getString('file');

// Tipo selecto
$type = $this->router->param('type', 'php');

$filename = '';
$listado = array();
$requeridos = array();
$url_back = false;

if ($modulo != '') {
	$m = new \miFrame\Local\AdminModules(true);
	$listado = $m->getAllModules('', $modulo);
	if (isset($listado[$modulo])) {
		$requeridos = $m->getRequiredFiles($modulo, true);
		$total_requeridos = count($requeridos);
		// Adiciona enlaces de consulta
		$modulo_padre = urlencode($modulo);
		foreach ($requeridos as $modulo_req => $info_req) {
			$enlace = false;
			// if ($total_requeridos == 1) { $file = $modulo_req; }
			if (isset($info_req['type'])) {
				if ($modulo_req != $file) {
					$urlbase = "modules/detail?file={$modulo_req}&module={$modulo_padre}";
					$enlace = $this->router->getFormAction($urlbase, true);
				}
			}
			// Adiciona enlace
			$requeridos[$modulo_req]['url'] = $enlace;
		}
		if ($file != '') {
			// Enlace de regreso para detallado de "requeridos"
			$urlbase = 'modules/detail?module=' . $modulo_padre;
			$url_back = $this->router->getFormAction($urlbase, true);
		}
		if (isset($listado[$modulo]['uses'])
			&& is_array($listado[$modulo]['uses'])
			&& count($listado[$modulo]['uses']) > 0
			) {
			$nuevos = $listado[$modulo]['uses'];
			$listado[$modulo]['uses'] = array();
			foreach ($nuevos as $modulo_req) {
				$urlbase = "modules/detail?module={$modulo_req}";
				$enlace = $this->router->getFormAction($urlbase, true);
				$listado[$modulo]['uses'][$modulo_req] = $enlace;
			}
		}
		// Actualiza tipo
		$type = $listado[$modulo]['type'];
	}
}

if ($modulo == '' || !isset($listado[$modulo])) {
	$this->router->abort(
		miframe_text('Parámetros incompletos'),
		miframe_text('No se ha definido nombre de módulo válido ($1)', $modulo)
		);
}

if ($file != '' && isset($requeridos[$file])) {
	// Información de un documento de la lista de requeridos
	$filename = $requeridos[$file]['path'];
}

$documento = '';
if ($filename != '' && file_exists($filename)) {
	$clase_manejador = micode_modules_class($type);
	$clase_manejador->ignoreLocalStyles = true;
	$documento = $clase_manejador->getDocumentationHTML($filename, true, true);
}
elseif ($filename != '') {
	$documento = miframe_text('**Error:** No pudo encontrar archivo "$2" asociado al módulo $1', $modulo, $filename);
}

// miframe_debug_box($listado);
$type_titulo = micode_modules_eval_type($type);

$data_proyecto = array(
	'module' => $modulo,
	'file' => $file,
	// 'url-back' => $this->router->getFormAction('modules/list/' . $tipo_encode, true),
	'url-back-file' => $url_back,
	'type' => $type,
	'title' => $type_titulo,
	'documento' => $documento,
	'require-files' => $requeridos,
	'info' => $listado[$modulo],
	'dirbase' => $m->getDirBase($modulo)
	);

// if (isset($mensajes)) { $data_proyecto['mensajes'] = $mensajes; }
$data = $this->router->getDataReloaded(true);
if ($data !== false && is_array($data) && isset($data['msg'])) {
	$data_proyecto['mensajes'] = $data['msg'];
}

$this->startView('modules/detail.php', $data_proyecto);