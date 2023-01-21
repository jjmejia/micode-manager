<?php
/**
 * Prepara salidas a pantalla para rutas que empiezan con "api", asociadas a Web Services.
 *
 * @author John Mejia
 * @since Diciembre 2022
 */

// $this->view_base = 'api';

$this->router->force_json = true;

// Registra ventanas modales personalizadas
miframe_data_fun('miframe-box-web', array($this, 'apiBox'));

// Informa que la salida es en JSON
header('Content-Type: application/json');

// print_r($this); exit;