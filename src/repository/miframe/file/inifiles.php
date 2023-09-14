<?php
/**
 * miFrame - Librería para manejo de archivos de configuración (*.ini).
 *
 * @author John Mejia
 * @since Abril 2022
 */

/**
 * Recupera información de archivos ".ini".
 * Convierte todas las llaves (sea a nivel de grupo o de elemento) a minúsculas.
 *
 * @param string $filename El nombre del fichero ini que va a ser analizado.
 * @param bool $process_sections TRUE obtiene un arreglo multidimesional, con los nombres de las secciones y las
 * 				configuraciones incluidas. En FALSE retorna un arreglo unidimensional e ignora los nombres de las secciones.
 * @param int $scanner_mode Puede ser o INI_SCANNER_NORMAL, INI_SCANNER_TYPED (por defecto) o INI_SCANNER_RAW.
 * @return array Arreglo conteniendo los datos recuperados.
 */
function miframe_inifiles_get_data(string $filename, bool $process_sections = true, int $scanner_mode = INI_SCANNER_TYPED) {

	$data = array();
	if (file_exists($filename)) {
		$data = parse_ini_file($filename, $process_sections, $scanner_mode);
		if (is_array($data) && count($data) > 0) {
			$data = array_change_key_case($data, CASE_LOWER);
			if ($process_sections) {
				foreach ($data as $k => $v) {
					if (is_array($v)) {
						$data[$k] = array_change_key_case($v, CASE_LOWER);
					}
				}
			}
		}
		else {
			// Garantiza que retorne arreglo
			$data = array();
		}
	}

	return $data;
}

/**
 * Genera archivo con formaro "ini".
 * Algunos valores se guardan entre comillas para prevenir problemas en la lectura. Este proceso se
 * realiza con asistencia de la función miframe_inifiles_format().
 *
 * @param string $filename El nombre del fichero ini que va a ser analizado.
 * @param mixed $data Arreglo con los valores a guardar.
 * @param bool $process_sections TRUE asume el primer nivel de $data corresponde a un nombre de seccion (si se recibe
 * 				un arreglo unidimensional no tendrá efecto este parámetro). El nombre se registra con el uso de corchetes "[...]".
 *             	En FALSE evalua $data como si se tratase de un arreglo unidimensional.
 * @return bool TRUE si el archivo fue creado con éxito.
 */
function miframe_inifiles_format_data(mixed $data, bool $process_sections = true) {

	$contenido = '';
	$comentarios = array();
	$adicional = '';

	// Busca comentarios, estos van en llaves del tipo ";(llave a comentar)"
	foreach ($data as $clave => $valor) {
		$llave = strtolower(trim($clave));
		if ($llave != '' && $llave[0] == ';') {
			$llave = trim(substr($llave, 1));
			if (is_array($valor)) {
				$valor = implode("\n", array_filter($valor));
			}
			$valor = miframe_inifiles_comment_format($valor);
			if ($valor != '') {
				if ($llave == '') {
					// Comentario general, lo adiciona de inmediato
					$contenido .= '; ' . $valor . PHP_EOL;
				}
				else {
					// Comentario asociado a una llave del archivo
					$comentarios[$llave] = $valor;
				}
			}
			unset($data[$clave]);
		}
	}

	// Deja espacio al iniciar. Separa posteriormente el nombre del archivo del resto
	// pero no deja espacio si se incluye un comentario general.
	$contenido .= PHP_EOL;

	// Registra valores
	foreach ($data as $clave => $valor) {
		$llave = strtolower(trim($clave));
		if (isset($comentarios[$llave])) {
			$contenido .= '; ' . $comentarios[$llave] . PHP_EOL;
		}
		if (is_array($valor)) {
			// En este caso, $llave indica el grupo
			if ($contenido != '') {
				$contenido .= PHP_EOL;
			}
			if ($process_sections) {
				$contenido .= '[' . $llave . ']' . PHP_EOL . PHP_EOL;
			}
			foreach ($valor as $gllave => $gvalor) {
				$contenido .= miframe_inifiles_format($gllave, $gvalor);
			}
		}
		else {
			$contenido .= miframe_inifiles_format($llave, $valor);
		}

		$contenido .= PHP_EOL;
	}

	return $contenido;
}

function miframe_inifiles_comment_format(string $comment) {

	$valor = '';
	if ($comment != '') {
		$comment = str_replace("\r", '', trim($comment));
		$lineas = explode("\n", $comment);
		array_walk($lineas, function(&$v) { $v = wordwrap(trim($v), 110, PHP_EOL . "; "); });
		$valor = implode(PHP_EOL . "; ", $lineas);
	}

	return $valor;
}

function miframe_inifiles_save_data(string $filename, mixed $data, bool $process_sections = true) {

	$contenido = miframe_inifiles_format_data($data, $process_sections);
	return miframe_inifiles_save_data_raw($filename, $contenido);
}

function miframe_inifiles_save_data_raw(string $filename, string $contenido) {

	if ($contenido != '') {
		$contenido = '; ' . strtoupper(basename($filename)) . PHP_EOL .
			$contenido .
			// Adiciona comentarios de control
			'; Creado en ' . date('Y/m/d H:i:s') . PHP_EOL;

		return file_put_contents($filename, $contenido);
	}

	return false;
}

/**
 * Da formato a la asociación llave/valor que será almacenada en archivo ini.
 * De https://www.php.net/manual/es/function.parse-ini-string.php:
 * > (modo) INI_SCANNER_TYPED... los tipos boolean, null e integer se preservan siempre que sea posible.
 * > Los valores de string "true", "on" y "yes" son convertidos a true. "false", "off", "no" y "none" se consideran como false.
 * > "null" se convierte a null en el modo tipificado.
 * > También, todos los string numéricos son convertidos al tipo integer si fuera posible.
 * Adicionalmente, si se recibe un valor arreglo, intentará generar la respectiva llave con formato del tipo "nombre[llave]=valor".
 *
 * @param string $key Nombre de la llave.
 * @param mixed $value Valor a ser asociado a la llave.
 * @return string Línea compatible con formato de lectura.
 */
function miframe_inifiles_format(string $key, mixed $value) {

	$contenido = '';
	if (is_array($value)) {
		// Procesa datos como arreglo
		foreach ($value as $k => $v) {
			$llave = '';
			if (!is_numeric($k)) {
				$llave = $key . '[' . trim($k) . ']';
			}
			else {
				$llave = $key . '[]';
			}
			$contenido .= miframe_inifiles_format($llave, $v);
		}
	}
	else {
		$valor_ini = trim($value);
		$llave = strtolower($key);
		if ($valor_ini === '') {
			// No hay dato valido, guarda como comentario <-- Guarda siempre
			// $llave = '; ' . $key;
		}
		else {
			// Encapsula entre comillas si contiene fin de linea, tabs, comillas o las palabras "no/yes"
			// Confirma que se interprete correctamente
			// $eval = @parse_ini_string('v='.$valor_ini, false, INI_SCANNER_TYPED);
			// if (!isset($eval['v']) || trim($eval['v']) !== $valor_ini) {
				// echo "$llave = "; print_r($eval); echo " // $valor_ini<hr>";
				$valor_ini = '"' . addslashes($valor_ini) . '"';
			// }
		}

		$contenido = $llave . '=' . $valor_ini . PHP_EOL;
	}

	return $contenido;
}