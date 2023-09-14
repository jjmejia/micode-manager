# micode-manager

Organizador de código personal para la gestión de librerías propias y de terceros en los proyectos de desarrollo.

Los objetivos principales de este Organizador son:

* Reusar los archivos de código para prevenir la proliferación de múltiples copias de un mismo archivo,
  lo que a su vez permite que las mejoras en ese mismo archivo beneficien a todos los proyectos que lo usan.
* Generar paquetes de distribución que contengan copias de los archivos necesarios.
* Facilitar la generación de documentación, mediante la documentación en código con el modelo Javadoc.
* Aunque está originalmente ideado para manejo de proyectos PHP, se estructura de forma tal que pueda adecuarse
  para la organización de proyectos en otros lenguajes de programación.

## ¿Cómo comenzar?

Para usar **miCode-Manager** primero debes descargar una copia del código publicado en *Github* y copiarlo en un directorio del servidor web, de forma que puedas consultar desde tu navegador web de preferencia el archivo `src\index.php`. Este script se encargará de solicitar la información y realizar las acciones que requiere el sistema para su primer uso. Esas acciones son:

* Solicitud del Path asociado al proyecto "micode-admin", que es el nombre de proyecto asignado a la interfaz de
  administración de **miCode-Manager**.
* Configuración de los módulos externos requeridos. Con esta primera versión se solicitará el  módulo *Parsedown*,
  que puede descargarse de Github en https://github.com/erusev/parsedown. Dicha librería interpreta el formato Markdown para una visualización adecuada en el navegador web.
* Creación de los archivos de soporte asociados a los módulos requeridos por la interfaz de administración (proyecto
  registrado con el nombre "micode-admin").
* Finalmente, tu nombre y correo electrónico, datos que serán usados para identificación del sistema (esta información
  quedará en tu equipo local de usuario y se usará en algunos casos para firmar scripts creados para tus proyectos).

Una vez terminados los procesos de configuración inicial, podrás usar **miCode-Manager**. El primer proyecto registrado
(para referencia) es precisamente la interfaz de administración.

**Importante:** Se recomienda (aunque no es mandatorio) configurar el servidor web usado para permitir direccionamiento dinámico en el directorio donde se instale **miCode-Manager** y apuntar toda consulta sobre dicho directorio al script `index.php`.

Más información sobre esta primera versión puedes encontrarla en [el Blog](https://micode-manager.blogspot.com/2023/01/micode-manager-version-10.html).

## Estructura básica de directorios

Se diferencian los siguientes directorios para uso de **miCode-Manager**:

* `src`: Contiene scripts básicos para uso del sistema.
* `data`: Archivos de datos y configuración del sistema.
* `public`: Scripts del sistema.
* `tests`: Scripts de pruebas a librerías del sistema.
* `cmd`: Contiene scripts de soporte para uso del servidor web nativo de PHP.

Por precaución y seguridad, estos son los permisos recomendados para cada uno de estos directorios, sean de acceso directo desde el navegador (A), lectura (L), escritura (E) y/o ejecución (X):

    +------------+----------+
    | Directorio | Permisos |
    +------------+----------+
    | src        | L/X      |
    +------------+----------+
    | data       | L/E      |
    +------------+----------+
    | public     | A/L/X    |
    +------------+----------+
    | tests      | A/L/X    |
    +------------+----------+

## Soporte para servidor web nativo de PHP

Para sistemas operativos Windows, en el directorio `cmd` se incluye el archivo por lotes `micode-server.bat` que al ejecutarse inicia una sesión de servidor web usando el servidor nativo de PHP, que puede usarse para consultar esta aplicación sin requerir de un servidor externo. Para que funcione, es necesario declarar en el entorno del sistema, los siguientes valores:

- `PHPPATH`: Path de PHP.EXE.
- `PHPSERVERPORT`: Puerto a usar para el servidor web (por ejemplo, "8080").

Esto puede hacerse por cualquiera de estos métodos:

- Modificando agregando estos valores a las variables de entorno de Windows.
- Fijando las variables de entorno usando el comando `SET` y luego invocando `micode-server.bat`. Por ejemplo:
````
set PHPPATH=C:\php\php.exe
set PHPSERVERPORT=8080
micode-server.bat
````
- Haciendo una copia de `micode-server.bat` e incluir en el nuevo archivo la declaración de valores usando el comando `SET`.

Al ejecutar, abrirá la ventana del navegador web por defecto en la URL `http://localhost:8080`, donde se visualizará la interfaz de **miCode-Manager**.

## Documentación

La siguiente documentación puede consultarse en el Blog asociado:

* [Presentación](https://micode-manager.blogspot.com/2022/05/presentacion.html)

* [Caso de uso práctico](https://micode-manager.blogspot.com/2022/12/micodemanager-caso-de-uso.html)

* [Versión 1.0](https://micode-manager.blogspot.com/2023/01/micode-manager-version-10.html)

* [Reportar bugs y/o sugerencias de mejora](https://github.com/jjmejia/micode-manager/issues) (Requiere tener una cuenta
  activa en Github).

## Descargo

Esta propuesta se realiza considerando que pueda ser de utilidad a los desarrolladores (particularmente aquellos _freelance_), de modo que les ayude a administrar los diferentes scripts y librerías, propias o externas, que usen
en su trabajo.
