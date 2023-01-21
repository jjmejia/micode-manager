<?php
/**
 * Librería para manejo de datos recibidos por Web (_POST, _GET, _REQUEST).
 *
 * Escrito por John Mejia (C) Abril 2022.
 */

namespace miFrame\Interface;

class Request {

	// $method = $_SERVER['REQUEST_METHOD']; // GET, POST

	// public function isPost(string $name) {}

	// public function isGet(string $name) {}

	public function exists(string $name) {

		return array_key_exists($name, $_REQUEST);
	}

	private function getData(string $name, mixed $default) {

		if ($this->exists($name)) {
			$default = $_REQUEST[$name];
		}

		return $default;
	}

	public function getBoolean(string $name, bool $default = false) {

		$valor = $this->getData($name, $default);
		if (!is_bool($valor)) {
			$valor = ($valor > 0);
		}

		return $valor;
	}

	private function getOptionParam(string $name, mixed $default, array $options = null) {

		if (is_array($options) && isset($options[$name])) {
			$default = $options[$name];
		}

		return $default;
	}

	/**
	 * Opciones:
	 * - conector: A usar cuando el valor recuperado es un arreglo.
	 */
	public function getString(string $name, string $default = '', array $options = null) {
		// return $this->get($name, 'bool', false);

		$valor = $this->getData($name, $default);
		if (!is_array($valor)) {
			$valor = trim($valor);
		}
		else {
			$conector = $this->getOptionParam('conector', ',', $options);
			$valor = implode($conector, $valor);
		}

		// Validar en opciones que no acepte HTML (PENDIENTE)
		// Previene tags en entradas que puedan ir a pantalla.
		$valor = str_replace('<', '&lt;', $valor);

		// if ($valor === '') { $valor = $default; } <-- En blanco puede ser un valor valido
		// $valor = filter_var($_REQUEST[MIFRAME_IDFRM], FILTER_SANITIZE_STRING);
		return $valor;
	}

	public function getArray(string $name, array $default = array(), $options = '') {

		$valor = $this->getData($name, $default);
		if (!is_array($valor)) {
			if ($valor != '') { $valor = array($valor); }
			else { $valor = array(); }
		}

		return $valor;
	}

	// PENDIENTE: Para mapear entradas por cli

}