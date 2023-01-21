<?php
/**
 * Adición de nuevos proyectos.
 *
 * Los proyectos se crean así:
 * - El nombre del proyecto (no confundir con el titulo) es el de un directorio a crear en
 *   user/projects pero este no será el contenedor del código, tan solo de los archivos de
 *   configuración donde se indique el path, tipo, etc.
 *
 * Escrito por John Mejía. Abril 2022.
 */

$data_proyecto = micode_modules_project_new();

$data_proyecto['nuevo'] = true;

include __DIR__ . '/edit.php';