<?php
/**
 * Redirige a la página de index.php del directorio principal.
 *
 * @author John Mejia
 * @since Septiembre 2023
 */

 $location = $_SERVER['REQUEST_URI'] . '/../../index.php';
 $mensaje = "<script>window.location='{$location}';</script>" .
	 "Esta página ha sido consultada de forma incorrecta (E1044). " .
	 "<a href=\"{$location}\">Favor consultar desde esta página</a>.";
 if (!headers_sent()) {
	 // header("HTTP/1.1 301 Moved Permanently");
	 header("Location: {$location}");
 }
 exit($mensaje);