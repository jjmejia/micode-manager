<?php
/**
 * miCode - Organizador de código para proyectos en PHP y otros lenguajes.
 *
 * Este script se incluye para prevenir consultas directas a este directorio desde el navegador.
 *
 * @author John Mejia
 * @since Abril 2022
 */

// Directorio principal
define('MIFRAME_ROOT', dirname(__DIR__));

// Consulta script principal
include_once MIFRAME_ROOT . '/src/admin/index.php';
