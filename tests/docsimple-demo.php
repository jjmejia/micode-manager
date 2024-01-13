<?php
/**
 * Script para probar implementación de clase DocSimple.php
 *
 * @author John Mejía
 * @since Abril 2022
 */

include_once __DIR__ . '/lib/testfunctions.php';

$files = array(
	'/miframe/common/shared/functions.php',
	'/miframe/file/serialize.php',
	'/miframe/utils/ui/HTMLSupport.php',
	'/miframe/utils/docsimple/docsimple.php',
	'/miframe/utils/docsimple/docsimplehtml.php',
);

// Carga archivos requeridos
miframe_test_include($files);

// Adiciona este archivo para ejemplo de documentación
$files[] = __FILE__;

$llaves = array_keys($files);
$selecto = $llaves[0];
if (isset($_REQUEST['file']) && isset($files[$_REQUEST['file']])) {
	$selecto = $_REQUEST['file'];
}
if (isset($_REQUEST['doc'])) {
	// Indica documento a visualizar. Debe estar incluido en el DOCUMENT_ROOT.
	$documento = $_SERVER['DOCUMENT_ROOT'] . '/' . str_replace('..', '.', $_REQUEST['doc']);
	if (file_exists($documento)) {
		$files[] = $documento;
		$selecto = count($files) - 1;
	}
}

$doc = new \miFrame\Utils\DocSimple\DocSimpleHTML();

// Opcional: echo $doc->getStylesCSS();

// Opciones de visualización
$doc->clickable = false;
$doc->showErrors = false;
$doc->showAllFunctions = false;

$enlaces = '';

// Habilita navegacion en linea
if (miframe_test_option(
	'online',
	'Habilitar navegación en línea',
	'Mostrar todo en pantalla',
	$enlaces)) {
	$doc->clickable = true;
}

// Mostrar errores
if (miframe_test_option(
	'errorson',
	'Mostrar errores encontrados',
	'Ocultar errores encontrados',
	$enlaces
	)) {
	$doc->showErrors = true;
}

// Mostrar todas las funciones
if (miframe_test_option(
	'privateon',
	'Mostrar funciones/métodos privados',
	'Ocultar funciones/métodos privados',
	$enlaces
	)) {
	$doc->showAllFunctions = true;
}

// Valida si existe librería Parsedown en ruta pre-establecida
$parsedown_include = __DIR__ . '\..\..\..\vendor\parsedown-master\parsedown.php';
if (file_exists($parsedown_include)) {
	if (miframe_test_option(
		'usepdon',
		'Usar librería Parsedown',
		'Remover librería Parsedown',
		$enlaces
		)) {
		include_once $parsedown_include;
		// Función para realizar Parser
		$parser = new \Parsedown();
		// Escape HTML even in trusted input
		$parser->setMarkupEscaped(true);
		$doc->parserTextFunction = array($parser, 'text');
	}
}


// Adiciona listas de ejemplos
$ejemplos = '';
$data = $_REQUEST;
foreach ($files as $k => $file) {
	if ($ejemplos != '') { $ejemplos .= ' | '; }
	if ($k == $selecto) {
		$documento = $doc->render($file);
		$ejemplos .= "<b>" . basename($file) . "</b> ";
	}
	else {
		$data['file'] = $k;
		$ejemplos .= miframe_test_datalink(basename($file), $data);
	}
}

miframe_test_start('Test DocSimple');

?>

Uso:

<pre class="code">
	$doc = new \miFrame\Utils\DocSimple\DocSimpleHTML();
	$documento = $doc->getDocumentationHTML($file);
</pre>
<p>
	Opciones: <?= $enlaces ?>
</p>
<p>
	Explorar: <?= $ejemplos ?>
</p>

<?= $documento ?>

</body>
</html>