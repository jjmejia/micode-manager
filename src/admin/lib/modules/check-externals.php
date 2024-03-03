<?php
/**
 * Revisión de librerías externas requeridas por miCode.
 *
 * @author John Mejia
 * @since Noviembre 2022.
 */

$e = function() {
	if (count($_REQUEST) <= 0 || isset($_REQUEST['check-externals-update'])) {
		include_once __DIR__ . '/../class/EvalMiCode.php';
		try {
			$check = new miFrame\Check\EvalMiCode();
			$check->checkExternals();
			$check->checkMiCode();
		}
		catch (\Throwable | \Exception $e) {
			exit($e->getMessage());
		}
	}
};

// Ejecuta función temporal
$e();

// Remueve para no afectar el resto del sistema
unset($e);
