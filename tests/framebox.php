<?php
/**
 * Proyecto de ejemplo para demostrar uso de miFrame.
 *
 * @author John Mejía
 * @since Abril 2022
 */

include_once __DIR__ . '/lib/testfunctions.php';
include_once MIFRAME_LOCALMODULES_PATH . '/miframe/common/debug.php';
include_once MIFRAME_LOCALMODULES_PATH . '/miframe/common/helpers.php';

// Por defecto, deshabilita modo Debug
miframe_debug_enable(false);

$enlace = '';
$enlace_error = '';

// Valida si habilita modo debug
if (miframe_test_option(
	'debugon',
	'Habilitar modo debug',
	'Deshabilitar modo debug',
	$enlace
	)) {
	miframe_debug_enable(true);
}

if (miframe_test_option(
	'errorson',
	'Cargar librería "miframe/common/errors"',
	'No cargar librería "miframe/common/errors"',
	$enlace_error
	)) {
	include_once MIFRAME_LOCALMODULES_PATH . '/miframe/common/errors.php';
}

if (miframe_test_option(
	'vscodeon',
	'Abrir archivos con VSCode',
	'No abrir archivos con VSCode',
	$enlace_error
	)) {
	miframe_vscode_enable(true);
}

miframe_test_start('Test Framebox');

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

</p>
<?php

	echo miframe_box('Información',
		'La librería <b>miframe/common/errors</b> provee una interfaz alternativa para despliegue de errores y puede asociarse al editor VSCode.',
		'info',
		$enlace_error
		);

?>
<p>
	Ejemplos:
</p>

<?php

miframe_test_pre("trigger_error('Prueba de manejo de errores Warning', E_USER_WARNING);");
trigger_error('Prueba de manejo de errores Warning', E_USER_WARNING);

miframe_test_pre("trigger_error('Prueba de manejo de errores Notice', E_USER_NOTICE);");
trigger_error('Prueba de manejo de errores Notice', E_USER_NOTICE);

miframe_test_pre("trigger_error('Prueba de manejo de errores Deprecated (funciones obsoletas)', E_USER_DEPRECATED);");
trigger_error('Prueba de manejo de errores Deprecated (funciones obsoletas)', E_USER_DEPRECATED);

// miframe_test_pre("miframe_error('Prueba de manejo de errores en $1', date('Y/m/d H:i:s'), debug: 'Mensaje de debug');");
miframe_test_pre("trigger_error('Prueba de manejo de errores Fatales', E_USER_ERROR);");
trigger_error('Prueba de manejo de errores Fatales', E_USER_ERROR);
