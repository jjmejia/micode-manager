<?php
/**
 * miFrame - Librería para almacenado en archivo de datos serializados.
 * No usar para guardar Objetos y cualquier otro tipo que no funcione correctamente o soporte
 * la ejecución de la función PHP "serialize()" ya que puede tener resultados no deseados.
 *
 * @author John Mejia
 * @since Abril 2022
 */

function miframe_serialize_id() {
	return 'MIFRAME-CACHE-' . date('ymdhis', filemtime(__FILE__)) . '/';
}

/**
 * Guarda información de caché en disco.
 * Para evitar conflictos, no guarda si $data es un booleano (true/false).
 *
 * @param string $filename Nombre del script del que se va a recuperar la documentación.
 * @return bool TRUE si pudo crear el caché en disco. FALSE en otro caso.
 */
function miframe_serialize(string $filename, mixed $data) {
	// Guarda en disco
	if ($filename != '' && is_dir(dirname($filename)) && !is_bool($data)) {
		$contenido = base64_encode(serialize($data));
		$crc = md5($contenido);
		return file_put_contents($filename, wordwrap(miframe_serialize_id() . $crc . $contenido, 120, "\n", true));
	}

	return false;
}

/**
 * Recupera información de caché en disco.
 *
 * @param string $filename Nombre del script del que se va a recuperar la documentación.
 * @return bool/mixed FALSE si no existe caché u ocurre algún error, en otro caso retorna el dato en cache.
 */
function miframe_unserialize(string $filename) {

	if ($filename != '' && file_exists($filename)) {
		$contenido = str_replace("\n", '', file_get_contents($filename));
		$identificador_base = miframe_serialize_id();
		$len = strlen($identificador_base);
		$identificador = substr($contenido, 0, $len);
		$crc = substr($contenido, $len, 32);
		$contenido = substr($contenido, $len + 32);
		if ($identificador == $identificador_base
			&& $crc == md5($contenido)
			) {
			return @unserialize(base64_decode($contenido));
		}
	}

	return false;
}
