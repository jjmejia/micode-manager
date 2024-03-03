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

if (!$this->params->exists('page-nomenu')) {
	// Menú principal general
	$include_nav = 'addons/menumain.php';
	if ($this->params->exists('config->project-name')) {
		// Está consultando una aplicación
		$include_nav = 'addons/menuprojects.php';
	}
	elseif ($this->params->exists('module')) {
		// Menús para detalle de módulos
		$include_nav = 'addons/menumodules.php';
	}
	elseif ($this->params->exists('reponame')) {
		// Está consultando una aplicación
		$include_nav = 'addons/menurepos.php';
	}
	elseif ($this->router->requestStartWith('modules') || $this->router->requestStartWith('localtests')) {
		// Menus por tipos de proyecto
		$include_nav = 'addons/menubytype.php';
	}

	$this->view->capture($include_nav, 'menu');
}

/**
 * Alternativa a usar al invocar la función miframe_box().
 *
 * @return string HTML con la presentación alternativa.
 */

function menuApps(&$router, string $title, array $enlaces, string $return = '') {

	$return_enlace = '';
	if ($title != '') {
		$return_enlace = $router->documentRoot();
	}

	// Menú de cada página
	if (count($enlaces) > 0 || $title != '') {
		$subtitulo = '';
		$enlace_secundario = '';
		if ($return != '') {
			$enlace_secundario = "| $return";
		}

		// Valida subtitulo para complementar la clase asociada al "nav"
		if ($title != '') {
			$subtitulo = "
			<h2>
				$title
				<div class=\"return_link\">
					<a href=\"{$return_enlace}\">Regresar a Página principal</a> $enlace_secundario
				</div>
			</h2>";
		}

	?>

		<?= $subtitulo ?>

		<ul class="app-list">

			<?php

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

			?>

		</ul>

	<?php

	}

}
