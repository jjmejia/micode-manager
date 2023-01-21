<?php
/**
 * Script de soporte para la definición de subrutinas a usar en las vistas.
 * Este archivo se carga desde el objeto Views, de forma que $this->view hace referencia a ese objeto.
 * Para información sobre el uso de variables dentro de funciones anonimas, ver:
 * https://www.php.net/manual/en/functions.anonymous.php
 *
 * Funciones creadas:
 *
 * - menuMain
 * - menuProjects
 * - menuByType
 * - checkModules
 *
 * Adicionalmente, fija un diseño alterno para uso de miframe_box().
 *
 * @author John Mejía
 * @since Abril 2022
 */

if ($this->view->param('page-nomenu', false) === false) {
	// Menú principal general
	$include_nav = 'addons/menumain.php';
	if ($this->view->param('config->project-name', false) !== false) {
		// Está consultando una aplicación
		$include_nav = 'addons/menuprojects.php';
	}
	elseif ($this->view->param('module', false) !== false) {
		// Menús para detalle de módulos
		$include_nav = 'addons/menumodules.php';
	}
	elseif ($this->view->param('reponame', false) !== false) {
		// Está consultando una aplicación
		$include_nav = 'addons/menurepos.php';
	}
	elseif ($this->router->requestStartWith('modules') || $this->router->requestStartWith('localtests')) {
		// Menus por tipos de proyecto
		$include_nav = 'addons/menubytype.php';
	}
	$this->view->capture($include_nav, array(), 'menu');
}

/**
 * Alternativa a usar al invocar la función miframe_box().
 *
 * @return string HTML con la presentación alternativa.
 */

function cmdPost($post, string $param_name = '') {

	if ($param_name == '') { $param_name = 'app'; }
	$cmd = strtolower($post->getString('cmd'));
	$app = strtolower($post->getString($param_name));
	if ($app != '') { $cmd .= '/' . $app; }

	return $cmd;
}

function menuApps(&$view, string $title, array $enlaces, string $return = '') {

	$return_enlace = '';
	if ($title != '') {
		$return_enlace = $view->documentRoot();
	}

	/*$view->setParams([
		'page-subtitle' => $title,
		'page-menu' => $enlaces,
		'page-return' => $return_enlace
		]);*/

	// Menú de cada página
	// if ($this->view->param('page-menu:count') > 0) {
	if (count($enlaces) > 0 || $title != '') {

		$nav_add = '';
		$subtitulo = '';
		$enlace_secundario = '';
		if ($return != '') {
			$enlace_secundario = "| $return";
		}

		// Valida subtitulo para complementar la clase asociada al "nav"
		// if ($this->view->param('page-subtitle') != '') {
		if ($title != '') {
			$nav_add = '-app';
			$subtitulo = "
			<h2>
				$title
				<div class=\"return_link\">
					<a href=\"{$return_enlace}\">Regresar a Página principal</a> $enlace_secundario
				</div>
			</h2>";
		}

	?>

	<!-- <nav class="nav-main<?= $nav_add ?>"> -->

		<?= $subtitulo ?>

		<ul class="app-list">

			<?php

			// $this->view->param('page-menu:foreach', '', function ($k, $info) {
			foreach ($enlaces as $k => $info) {
					$selecto = '';
					if ($info['selecto']) { $selecto .= ' class="selected"'; }
					if (isset($info['target'])) { $selecto .= " target=\"{$info['target']}\""; }
			?>

			<li>
				<a href="<?= $info['url'] ?>"<?= $selecto ?>>
					<?= htmlentities($info['titulo']) ?>
				</a>
			</li>

			<?php

			}
			// return true; });

			?>

		</ul>
	<!-- </nav> -->

	<?php

	}

}