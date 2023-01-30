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

**Importante:** Se recomienda (aunque no es mandatorio) configurar el servidor web usado para permitir direccionamiento dinámico en el directorio donde se instale **miCode-Manager** y apuntar toda consulta sobre dicho directorio al script ´index.php´.

Más información sobre esta primera versión puedes encontrarla en [el Blog](https://micode-manager.blogspot.com/2023/01/micode-manager-version-10.html).

## Documentación

La siguiente documentación puede consultarse en el Blog asociado:

* [Presentación](https://micode-manager.blogspot.com/2022/05/presentacion.html)

* [Caso de uso práctico](https://micode-manager.blogspot.com/2022/12/micodemanager-caso-de-uso.html)

* [Versión 1.0](https://micode-manager.blogspot.com/2023/01/micode-manager-version-10.html)

## Descargo

Esta propuesta se realiza considerando que pueda ser de utilidad a los desarrolladores (particularmente aquellos _freelance_), de modo que les ayude a administrar los diferentes scripts y librerías, propias o externas, que usen
en su trabajo.
