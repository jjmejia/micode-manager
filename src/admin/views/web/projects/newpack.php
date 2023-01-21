<?php
/**
 * Script de soporte para presentación web (HTML).
 *
 * @author John Mejía
 * @since Abril 2022
 */

$app_name = $this->view->param('config->project-name');

?>

<h1>Nuevo paquete</h1>

<?php

$estado = $this->view->param('pack-status');
$enlace = $this->router->getFormAction('projects/packs/' . $app_name, true);
$regresar = $this->router->getFormAction('projects/packs/' . $app_name, true);
$enlace_regresar = '<p><a href="' . $regresar . '">Regresar a listado de paquetes</a></p>';
$filepack = $this->view->param('pack-file');

if ($estado == 'CAMBIOS_PENDIENTES') {

	$enlace = $this->router->getFormAction('projects/modules/' . $app_name, true);

?>
	<p><b>Existen cambios no revisados</b></p>
	<p>
		Uno o varios de los modulos instalados reportan diferencias respecto a la copia en el Repositorio.<br />
		Revise los módulos instalados, actualice las copias locales del Proyecto y verifique que el Proyecto funciona correctamente después de eso.<br />
		Regrese una vez actualizado para generar el Paquete de distribución.
	</p>
	<p><a href="<?= $enlace ?>">Revisar módulos instalados</a></p>

<?php

}
elseif ($estado == 'NOFILES') {

?>
	<p><b>No se encontraron archivos</b></p>
	<p>
		No se encontraron archivos al explorar el directorio del proyecto.
		Revise el directorio <i><?= $this->view->param('config->path:e') ?></i> y confirme que existen archivos.
		Revise también la configuración de archivos a ser ignorados (sección <b>Paquetes de distribución</b>
		del menú de edición de proyecto) no los esté excluyendo todos.
	</p>

<?php

}
elseif ($estado == 'ZIP_NOCREADO') {

?>
	<p><b>No pudo crear paquete de distribución</b></p>
	<p>
		No fue posible crear el archivo ZIP con los archivos requeridos para el Proyecto.<br />
		El error se produjo al intentar crear el archivo en la siguiente ubicación:<br />
		<div class="codepre"><?= $filepack ?></div>
	</p>

<?php

}
elseif ($estado == 'ZIP_VALIDO') {

?>
	<p><b>Paquete de distribución ya creado</b></p>
	<p>
		El paquete de distribución previamente creado es valido, ninguno de los archivos
		del proyecto se ha modificado desde su creación.
		<div class="codepre"><?= $filepack ?></div>
	</p>

<?php

}
elseif ($estado == 'ZIP_FALLIDO') {

	$errorfile = $this->view->param('pack-errorfile');

?>
	<p><b>No pudo crear paquete de distribución</b></p>
	<p>
		No fue posible crear el archivo ZIP con los archivos requeridos para el Proyecto.<br />
		El error se produjo al intentar incluir el siguiente archivo:<br />
		<div class="codepre"><?= $errorfile['path'] ?></div>
		Con origen en:<br />
		<div class="codepre"><?= $errorfile['src'] ?></div>
	</p>

<?php

}
elseif ($estado == 'ZIP_OK') {

?>
	<p><b>Paquete de distribución creado con éxito</b></p>
	<p>
		Se ha creado el siguiente archivo ZIP con los archivos requeridos para el Proyecto:<br />
		<div class="codepre"><?= $filepack ?></div>
	</p>

<?php

}
else {
	// No identificado
	?>
	<p><b>Existen cambios no revisados</b></p>
	<p>
		Uno o varios de los modulos instalados reportan diferencias respecto a la copia en el Repositorio.<br />
		Revise los módulos instalados, actualice las copias locales del Proyecto y verifique que el Proyecto funciona correctamente después de eso.<br />
		Regrese una vez actualizado para generar el Paquete de distribución.
	</p>
	<p><a href="<?= $enlace ?>">Revisar módulos instalados</a></p>

<?php
}

echo $enlace_regresar;
