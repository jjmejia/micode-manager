<?php
/**
 * Script de soporte para presentación web (HTML) a través de "interface/views".
 *
 * @author John Mejía
 * @since Abril 2022
 */

$data = array();

// Exporta parámetros de Router a $data
miframe_app()->router->exportParamsInto($data);

miframe_app()->startView('error.php', $data);
