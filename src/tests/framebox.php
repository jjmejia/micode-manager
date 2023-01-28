<?php
/**
 * Proyecto de ejemplo para demostrar uso de miFrame.
 *
 * @author John Mejía
 * @since Abril 2022
 */

include_once __DIR__ . '/../repository/miframe/common/debug.php';
include_once __DIR__ . '/../repository/miframe/common/functions.php';
include_once __DIR__ . '/lib/testfunctions.php';

// Valida carga de librería para formato de errores
if (array_key_exists('errorson', $_REQUEST)) {
	include_once dirname(__DIR__) . '/repository/miframe/common/errors.php';
}

miframe_test_start('Test Framebox');

// Por defecto, deshabilita modo Debug
miframe_debug_enable(false);

// Valida si habilita modo debug
$enlace_base = miframe_server_get('REQUEST_URI');
$enlace = $enlace_base;
if (strpos($enlace, '?') !== false) { $enlace .= '&'; }
else { $enlace .= '?'; }
$enlace .= 'debugon';
$enlace = '<a href="' . $enlace . '">Habilitar modo debug</a>';
if (array_key_exists('debugon', $_REQUEST)) {
	miframe_debug_enable(true);
	$enlace = '<a href="' . str_replace('debugon', '', $enlace_base) . '">Deshabilitar modo debug</a>';
	$enlace = str_replace('?&', '?', $enlace);
}

$enlace_error = $enlace_base;
if (strpos($enlace_error, '?') !== false) { $enlace_error .= '&'; }
else { $enlace_error .= '?'; }
$enlace_error .= 'errorson';
$enlace_error = '<a href="' . $enlace_error . '">Cargar librería "miframe/common/errors"</a>';
if (array_key_exists('errorson', $_REQUEST)) {
	$enlace_error = '<a href="' . str_replace('errorson', '', $enlace_base) . '">No cargar librería "miframe/common/errors"</a>';
	$enlace_error = str_replace('?&', '?', $enlace_error);
}

?>

Ejemplos de utilidades incluidas con <b>miFrame</b>.

<h2>Mensajes de depuración</h2>

<p>
	Estos mensajes solamente son visibles cuando <code>MIFRAME_DEBUG_ON</code> es TRUE.
	Por ejemplo:
</p>
<pre class="code">
	miframe_debug_box($_SERVER, 'SERVER');
</pre>

<?php

// Cuando no está habilitado, no se genera salida a pantalla para miframe_debug_box()
miframe_debug_box($_SERVER, 'SERVER');

if (!miframe_is_debug_on()) {
	echo miframe_box('Aviso', 'No es posible visualizar mensajes de depuración porque no está habilitado el modo DEBUG.', '', $enlace);
}
else {
	echo '<p>' . $enlace . '</p>';
}

?>

<h2>Mensajes de Error</h2>

<p>
	La función <code>miframe_error</code> puede usarse como alternativa para generar Excepciones en PHP.
</p>

<?= miframe_test_pre('miframe_error($message, $message_debug, $endscript);') ?>

<p>
	Adicionalmente, la librería <b>miframe/common/errors</b> provee una interfaz alternativa para despliegue de errores.<br />
	<?= $enlace_error ?>
</p>
<p>
	Ejemplos:
</p>

<?php

// throw new Exception("Value must be 1 or below");
miframe_test_pre("trigger_error('Prueba de manejo de errores Warning', E_USER_WARNING);");
trigger_error('Prueba de manejo de errores Warning', E_USER_WARNING);

miframe_test_pre("trigger_error('Prueba de manejo de errores Notice', E_USER_NOTICE);");
trigger_error('Prueba de manejo de errores Notice', E_USER_NOTICE);

miframe_test_pre("trigger_error('Prueba de manejo de errores Deprecated (funciones obsoletas)', E_USER_DEPRECATED);");
trigger_error('Prueba de manejo de errores Deprecated (funciones obsoletas)', E_USER_DEPRECATED);

miframe_test_pre("miframe_error('Prueba de manejo de errores en $1', date('Y/m/d H:i:s'), debug: 'Mensaje de debug');");
miframe_error('Prueba de manejo de errores en $1', date('Y/m/d H:i:s'), debug: 'Mensaje de debug');
