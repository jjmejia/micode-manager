<?php
/**
 * Script de soporte para presentación web (HTML).
 *
 * @author John Mejía
 * @since Diciembre 2022
 */

$dirbase = $this->params->get('dirbase:e');
if ($dirbase == '') {
	// Intenta recuperar para proyectos
	$dirbase = $this->params->get('mirepo->path:e');
}
echo '<div class="x-explorer"><p class="x-info"><b>' . miframe_text('Directorio base') . ':</b> ' . $dirbase . '</p></div>';
echo $this->params->get('html');