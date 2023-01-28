<?php
/**
 * Script para probar implementación de clase DocSimple.php
 *
 * @author John Mejía
 * @since Abril 2022
 */

include_once __DIR__ . '/lib/testfunctions.php';

$files = array(
	// Archivos locales (si existen)
	__DIR__ . '/../src/serialize.php',
	__DIR__ . '/../src/docsimple.php',
	__DIR__ . '/../src/functions.php',
	// Archivos del repositorio (si se invoca desde miCode-Manager)
	__DIR__ . '/../repository/miframe/file/serialize.php',
	__DIR__ . '/../repository/miframe/utils/docsimple.php',
	__DIR__ . '/../repository/miframe/common/shared/functions.php'
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
$navegable = (!isset($_REQUEST['nav']) || intval($_REQUEST['nav']) > 0);

$doc = new \miFrame\Utils\DocSimple();

//////////////////////////////////////
// TEMPORAL!
include_once 'C:\Desarrollo\www\vendor\parsedown-master\parsedown.php';
// Función para realizar Parser
if (class_exists('\Parsedown')) {
	$parser = new \Parsedown();
	// Escape HTML even in trusted input
	$parser->setMarkupEscaped(true);
	$doc->parserTextFunction = array($parser, 'text');
	// print_r($parser);
}
//////////////////////////////////////

$ejemplos = '';
foreach ($files as $k => $file) {
	if ($ejemplos != '') { $ejemplos .= ' | '; }
	if ($k == $selecto) {
		$doc->clickable = $navegable;
		$documento = $doc->getDocumentationHTML($file);
		$ejemplos .= "<b>" . basename($file) . "</b> ";
	}
	else {
		$ejemplos .= "<a href=\"?file=$k\">" . basename($file) . "</a>";
	}
}

miframe_test_start('Test DocSimple');

?>

<p>Uso:</p`>
<pre class="code">
	$doc = new \miFrame\Utils\DocSimple();
	$documento = $doc->getDocumentationHTML($file, true);
</pre>
<p>
	Explorar: <?= $ejemplos ?>
</p>

<?= $documento ?>

</body>
</html>