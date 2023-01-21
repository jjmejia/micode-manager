Al evaluar el template, genera automáticamente el $defaults

###
$call('menu', 'menuProjects', {{ config->type  }}, {{ config->project-name }})
###

<link rel="stylesheet" href="{{ $createURL('/admin/public/css/forms.css') }}">

<div class="btn-container">
	<!--a href="?cmd=addmodule" class="btn btn-ppal">Nuevo módulo</a-->
</div>

### mensajes:implode
	<div class="info"><ul><li>{{ </li><li> }}</li></ul></div>
###

<form action="{{ form-action }}" method="POST">

	<h3>Módulos instalados ({{ modules->pre:count }})</h3>

	### ini_datetime:!empty
	<p class="separator">Última modificación realizada en {{ ini_datetime:date }}</p>
	###

	### modules->changes:!empty
	$box('Aviso importante', 'Hay {{ modules->changes:count }} modulos instalados que han sido cambiados desde la última actualización del proyecto. Verifique.', 'warning')
	###

	{{ $checkModules('pre', 'checked') }}

	<h3>Módulos adicionales sugeridos ({{ modules->add:count }})</h3>

	### modules->add:!empty
	<p class="separator">Los módulos instalados pueden requerir de algunos de los siguientes módulos:</p>
	###

	{{ $checkModules('add') }}

	<h3>Módulos removidos del repositorio principal ({{ modules->del:count }})</h3>

	### modules->del:!empty
	<p class="separator">
		Estos módulos fueron instalados pero ya no se encuentran disponibles en el repositorio.
		Deseleccione para removerlos del proyecto.
	</p>
	###

	<?= $this->view->checkModules('del') ?>

	<h3>Módulos disponibles ({{ modules->new:count }}</h3>

	<?= $this->view->checkModules('new') ?>

	### modules->pre:!empty
	<p><label>
		<input type="checkbox" name="rebuildall" value="1"> Reconstruir todos los módulos instalados
	</label></p>
	###

	<div style="margin-top:30px">
		<input type="submit" name="modulok" value="Guardar cambios" class="btn btn-ppal">
		<!--input type="button" value="Cancelar"  class="btn" onclick="javascript:document.load='index.php"-->
	</div>
</form>
