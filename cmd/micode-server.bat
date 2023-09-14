@echo off

rem Archivo de ejecución por lotes para ejecutar servidor web nativo PHP.

rem Defina en una variable de entorno PHPPATH el path de PHP.EXE o modifique la
rem siguiente linea con el path correcto según sea su caso.
rem set PHPPATH=C:\xxx

rem Inicia servidor en el puerto indicado por la variable de entorno PHPSERVERPORT.
rem Modifique el puerto segun conveniencia. Ejemplo:
rem set PHPSERVERPORT=8080

rem Ejecuta en paralelo
rem (Basado en una sugerencia tomada de https://stackoverflow.com/a/45021609)
start "miCode-Launcher" /b cmd /c "timeout /nobreak 3 >nul & start ""miCode-Browser"" explorer ""http://localhost:%PHPSERVERPORT%"""

rem Ejecuta el server built-in usando router.php para filtrar las consultas
%PHPPATH% -S localhost:%PHPSERVERPORT% -t "%cd%\.." "%cd%\router.php"
