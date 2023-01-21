<?php
/**
 * Script para probar implementación de clase Pack.php
 *
 * PENDIENTE:
 * - No genera error en modo binario al leer bloque que no existe
 *   (Recuperar datos de archivo prueba-manual.miframe-pack)
 * - Método para detectar MODO de un archivo PACK? (se hace con "$data = $pack->get($destino, 1);"
 *   pero debe haber una forma de inicializarlo)
 * - Método para adicionar archivo ->addFile($filename, $name = '') si no indica name usa basename($filename)
 * - Incluir en demo método para recuperar el indice (si es que existe dicho método, sino crearlo)
 * - Buscar crear un método "recuperar" que sea tan sencillo que pueda copiarse a un archivo para auto-extraccion
 *   (para manejo de los packs)
 *
 * @author John Mejía
 * @since Mayo 2022
 */

include_once __DIR__ . '/lib/testfunctions.php';

$files = array(
	__DIR__ . '/../src/pack.php',
	__DIR__ . '/../src/functions.php',
	__DIR__ . '/../repository/miframe/utils/pack.php',
	__DIR__ . '/../repository/miframe/common/shared/functions.php'
);

// Carga archivos requeridos
miframe_test_include($files);

$pack = new \miFrame\Utils\Pack();

// Ejemplo de generación de un valor numerico en bytes
// $n = 10228995;
// $bytes = $pack->getBytes($n);
// $v = $pack->getValue($bytes);
// echo "$n = $v / " . urlencode($bytes). "<hr>";

$cmd = '';
$file = '';
if (isset($_REQUEST['cmd'])) { $cmd = strtolower(trim($_REQUEST['cmd'])); }
if (isset($_REQUEST['file'])) { $file = 'pack-demo-files/packs/' . strtolower(trim($_REQUEST['file'])); }

if ($cmd == 'export' && $file != '' && file_exists($file)) {
	// Comprime archivo y lo envia luego al navegador
	$pack->exportFile($file);
	exit;
}

miframe_test_start('Test Pack');

$t = microtime(true);

$s = 'Un elefante se balanceaba sobre la tela de una araña, como veia que resistia fueron a llamar otro elefante.';
$destino = 'pack-demo-files/packs/prueba-manual.miframe-pack';

echo '<p><a href="?cmd=create">Crear archivo <b>' . basename($destino) . '</b></a></p>';

if ($cmd == 'create') {
	// Crea paquete de datos
	$pack->put($destino, '(LINEA 1) ' . $s, true);
	$pack->put($destino, '(LINEA 2) ' . $s . ' ' . date('YmdHis'));
	$pack->put($destino, '(LINEA 3) Hola mundo');

	echo "<ul><li>Archivo creado (" . ffilesize($destino) . " bytes)</li></ul>";
	echo '<pre style="padding-left:40px">' . wordwrap(str_replace(array('%2F', '%'), array('/', '.'),
		urlencode(file_get_contents($destino))), 120, "\n", true) . "</pre>";
}

// $destino = 'pack-demo-files/packs/prueba-txt.miframe-pack';
echo '<p><a href="?cmd=text">Crear archivo <b>' . basename($destino) . '</b> en modo texto</a></p>';

if ($cmd == 'text') {
	// Crea paquete de datos
	$pack->text($destino, $s, true, true);
	$pack->text($destino, $s.'/'.date('YmdHis'));
	$pack->text($destino, 'Hola mundo');

	echo "<ul><li>Archivo creado (" . ffilesize($destino) . " bytes)</li></ul>";
	echo '<pre style="padding-left:40px">' . file_get_contents($destino) . "</pre>";
}

if (file_exists($destino)) {
	echo '<p><a href="?cmd=recover">Recuperar datos de archivo <b>' . basename($destino) . '</b></a></p>';
}

if ($cmd == 'recover') {
	// Recupera registros
	$data = $pack->get($destino, 1);
	echo '<p><b>Modo:</b> ' . $pack->getMode() . '</p>';
	echo '<ul>';
	$terminar = false;
	for ($i = 1; $i < 10; $i++) {
		$data = $pack->get($destino, $i);
		if ($data === false) {
			$data = "(" . $pack->getLastError() . ")";
			$terminar = true;
		}
		echo "<li><b>Bloque #{$i}:</b> $data</li>";
		if ($terminar) { break; }
	}
	echo '</ul>';
}

$origen = 'pack-demo-files/prueba.txt';
if (!file_exists($origen)) {
	// Crea archivo de 1.5M para pruebas
	$data = date('Y-m-d H:i:s') . PHP_EOL;
	for ($i = 0; $i < 1500; $i++) {
		$data .= sprintf('%04d: ', ($i + 1)) . str_repeat('1234567890 ', 100) . PHP_EOL;
	}
	file_put_contents($origen, $data);
	unset($data); // Libera memoria
}

$destino = 'pack-demo-files/packs/prueba.miframe-pack';
$destino2 = 'pack-demo-files/prueba-recuperado.txt';

echo '<p><a href="?cmd=readimg">Crear archivo <b>' . basename($destino) . '</b> leyendo datos de <b>' . basename($origen) . '</b></a></p>';

if ($cmd == 'readimg') {
	// Comprime archivo directamente (se revienta con archivos grandes)
	$s = file_get_contents($origen);
	$pack->put($destino, $s, true);
	echo "<ul><li>Archivo creado (pack = " . ffilesize($destino) . " / original = " .
		ffilesize($origen) . " bytes)</li></ul>";
}

if (file_exists($destino)) {
	echo '<p><a href="?cmd=expand2">Descompactar archivo <b>' . basename($destino) . '</b> a <b>' . basename($destino2) . '</b></a></p>';
}

if ($cmd == 'expand2') {
	// Comprime archivo completo
	$pack->uncompressFile($destino, $destino2, true);
	echo "<ul><li>Archivo recuperado (" . ffilesize($destino2) . ")</li></ul>";
}

$destino = 'pack-demo-files/packs/prueba-directo.miframe-pack';

echo '<p><a href="?cmd=compact">Compactar directamente archivo <b>' . basename($origen) . '</b> a <b>' . basename($destino) . '</b></a></p>';

if ($cmd == 'compact') {
	// Comprime archivo completo
	$bloques = $pack->compressFile($origen, $destino, false, true);
	echo "<ul><li>Archivo creado con $bloques bloques (pack = " . ffilesize($destino) . " / original = " .
		ffilesize($origen) . " bytes)</li></ul>";
}

if (file_exists($destino)) {
	echo '<p><a href="?cmd=expand">Descompactar archivo <b>' . basename($destino) . '</b> a <b>' . basename($destino2) . '</b></a></p>';
}

if ($cmd == 'expand') {
	// Comprime archivo completo
	$pack->uncompressFile($destino, $destino2, true);
	echo "<ul><li>Archivo recuperado (" . ffilesize($destino2) . ")</li></ul>";
}

if (file_exists($destino)) {
	echo '<p><a href="?cmd=export&file=' . basename($destino) . '" target="_blank">Exportar archivo <b>' . basename($destino) . '</b></a></p>';
}

if ($cmd != '') {
	echo '<p><a href="' . basename(__FILE__) . '">Empezar de nuevo...</a></p>';
	echo "<p>DURACION: " . (microtime(true) - $t);
}

miframe_test_end();

//*******************************************************

function ffilesize(string $filename) {

	return number_format(filesize($filename), 0, '', '.');
}
