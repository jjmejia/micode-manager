<?php
/**
 * Script de soporte para presentación web (HTML).
 *
 * @author John Mejía
 * @since Diciembre 2022
 */

$dirbase = $this->view->param('dirbase:e');
if ($dirbase == '') {
	// Intenta recuperar para proyectos
	$dirbase = $this->view->param('mirepo->path:e');
}
echo '<p>' . $dirbase . '</p>';
echo $this->view->param('html');