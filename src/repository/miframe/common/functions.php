<?php
/**
 * Librería de funciones requeridas para las aplicaciones nativas de miFrame.
 *
 * @micode-uses miframe/common/shared
 * @author John Mejia
 * @since Abril 2022
 */

include_once __DIR__ . '/shared/functions.php';

/**
 * Retorna el valor numerico de un valor byte con formato.
 * Basado en ejemplo tomado de https://www.php.net/manual/es/function.ini-get.php
 *
 * Cumple con las directivas PHP para manejo de valoers byte. A saber:
 *
 * > The available options are K (for Kilobytes), M (for Megabytes) and G (for Gigabytes), and are all case-insensitive.
 * > Anything else assumes bytes. 1M equals one Megabyte or 1048576 bytes. 1K equals one Kilobyte or 1024 bytes.
 * > Note that the numeric value is cast to int; for instance, 0.5M is interpreted as 0.
 *
 * Tomado de: https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
 *
 * @param string $val Valor con formato.
 * @return int Valor numerico equivalente.
 */
function miframe_text2bytes(string $val) {

    $val = trim(str_replace('b', '', strtolower($val)));
    $last = substr($val, -1, 1);
	$valor = intval($val);

    switch($last) {
        case 'g':
            $valor *= 1024;
        case 'm':
            $valor *= 1024;
        case 'k':
            $valor *= 1024;
		}

    return $valor;
}

/**
 * Valida que un texto no tenga carácteres prohibidos para nombres de archivo o directorio.
 * Basado en https://www.php.net/manual/es/function.strcspn.php#60118
 *
 * @param string $basename Nombre a validar
 * @return bool TRUE si es un nombre valido. FALSE en otro caso.
*/
function miframe_valid_path(string $basename) {

	$forbidden="\"\\?*:/@|<>/";
	return (strlen($basename) != strcspn($basename, $forbidden));
}

/**
 * Retorna máximo tamaño de memoria asignado a PHP.
 *
 * @return int Tamaño en bytes.
 */
function miframe_memory_limit() {

   return miframe_text2bytes(ini_get('memory_limit'));
}

/**
 * Retorna máximo tamaño de memoria libre disponible para PHP.
 *
 * @param int $reserved Tamaño en bytes mínimo a garantizar.
 * @return int Tamaño en bytes. Valor negativo significa que se tiene menos que el mínimo deseado.
 */
function miframe_memory_free(int $reserved = 0) {

	return (miframe_memory_limit() - memory_get_usage(true) - $reserved);
}

/**
 * Recupera directorios existentes en el path indicado.
 *
 * @param string $path Directorio base.
 * @param string $pattern Patrón de busqueda (Ej. "base*").
 * @param bool $ignore_empty_dirs TRUE para no incluir subdirectorios que no contengan archivos.
 * @return array Listado de directorios.
 */
function miframe_tree_directory(string $path, string $pattern = '', bool $ignore_empty_dirs = true) {

	$i = 0;
	if ($pattern == '') {
		$pattern = '*';
	}

	$dirs = glob($path, GLOB_ONLYDIR | GLOB_NOSORT);
	while (is_array($dirs) && $i < count($dirs)) {
		$fileList = glob(miframe_path($dirs[$i], $pattern), GLOB_ONLYDIR | GLOB_NOSORT);
		if (is_array($fileList) && count($fileList) > 0) {
			$dirs = array_merge($dirs, $fileList);
		}
		$i ++;
	}

	if (is_array($dirs)) {
		if ($ignore_empty_dirs) {
			$total_dirs = count($dirs);
			for ($i = 0; $i < $total_dirs; $i++) {
				if ($dh = opendir($dirs[$i])) {
					$hay_archivos = false;
					while (($file = readdir($dh)) !== false) {
						if ($file != '.' && $file != '..' && !is_dir($dirs[$i] . '/' . $file)) {
							// Encontró un archivo valido
							$hay_archivos = true;
							break; // Sale del while
						}
					}
					closedir($dh);
					if (!$hay_archivos) {
						unset($dirs[$i]);
					}
				}
			}
		}
		sort($dirs);
	}
	else {
		$dirs = array(); // Se asegura de retornar un arreglo. glob() retorna false si hay algún error
	}

	// echo "<pre>"; print_r($dirs); exit;

	return $dirs;
}

/*

https://stackoverflow.com/questions/11452938/how-to-use-http-x-forwarded-for-properly
public function getClientIP(){
     if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)){
            return  $_SERVER["HTTP_X_FORWARDED_FOR"];
     }else if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
            return $_SERVER["REMOTE_ADDR"];
     }else if (array_key_exists('HTTP_CLIENT_IP', $_SERVER)) {
            return $_SERVER["HTTP_CLIENT_IP"];
     }

     return '';
}

Note that it appears that using the last value in the list is still probably using a proxy's IP. According to the link below, the originating client is the FIRST IP. en.wikipedia.org/wiki/X-Forwarded-For –
Matthew Kolb
Dec 31, 2014 at 18:45
2
Also note that the same source says that this is easy to forge, so the last one is more reliable. So each use case may make different choices. If the use case for getting the IP is combating fraud or spam, the first IP may be meaningless and the most reliable address - the last one - is most useful. If the use case for getting the IP is less nefarious activities, the first one would be most useful. –
Rob Brandt
May 9, 2015 at 21:22

    // Header can contain multiple IP-s of proxies that are passed through.
    // Only the IP added by the last proxy (last IP in the list) can be trusted.
    $proxy_list = explode (",", $_SERVER["HTTP_X_FORWARDED_FOR"]);
    $client_ip = trim(end($proxy_list));

---
*/
