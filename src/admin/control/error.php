<?php
/**
 * Script de soporte para presentación web (HTML) a través de "interface/views".
 *
 * @author John Mejía
 * @since Abril 2022
 */

$data = array();

// Exporta parámetros de Router a $data
$this->router->exportParamsInto($data);

$this->startView('error.php', $data);
