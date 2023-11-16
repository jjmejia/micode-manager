<?php
/**
 * Layout principal para el Administrador de miCode.
 * Usado para presentación web (HTML).
 *
 * @author John Mejía
 * @since Mayo 2022
 */

$backtrace = $this->params->get('footnote');
if ($backtrace == '') {
	$backtrace = miframe_debug_backtrace_info();
}

?>

<style>
body {
	font-family: "Segoe UI";
}
.trace {
	background: rgba(175,184,193,0.2);
	border: 1px solid #d0d7de;
	padding:16px;
	font-size:12px;
	margin-top:32px;
}
</style>

<h1><?= $this->params->get('title:e') ?></h1>

<p><?= nl2br($this->params->get('message')) ?></p>

<?= $this->params->iif('pre:!empty', '<p class="trace">{{ pre:e }}</p>') ?>

<?= $this->params->iif(($backtrace != ''), '<p class="trace">' . $backtrace . '</p>') ?>
