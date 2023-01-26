# micode-manager

Organizador de código personal para la gestión de librerías propias y de terceros en los proyectos de desarrollo.

Los objetivos principales de este Organizador son:

* Reusar los archivos de código para prevenir la proliferación de múltiples copias de un mismo archivo,
  lo que a su vez permite que las mejoras en ese mismo archivo beneficien a todos los proyectos que lo usan.
* Generar paquetes de distribución que contengan copias de los archivos necesarios.
* Facilitar la generación de documentación, mediante la documentación en código con el modelo Javadoc.
* Aunque está originalmente ideado para manejo de proyectos PHP, se estructura de forma tal que pueda adecuarse
  para la organización de proyectos en otros lenguajes de programación.

## Documentación

La siguiente documentación puede consultarse en el Blog asociado:

* [Presentación](https://micode-manager.blogspot.com/2022/05/presentacion.html)

* [Caso de uso práctico](https://micode-manager.blogspot.com/2022/12/micodemanager-caso-de-uso.html)

## ¿Cómo comenzar?

Para comenzar a usar **miCode-Manager** descarga el repositorio y consulta desde un navegador el archivo `src\index.php`.
El script solicitará la información que requiere para configurar el sistema para su primer uso. Estos valores son:

* Configuración del path asociado al proyecto "micode-admin", usado para la interfaz de administración
  de **miCode-Manager**.
* Módulos externos requeridos (inicialmente el módulo *Parsedown*, que puede descargarse de Github en
  https://github.com/erusev/parsedown).
* Creación de los archivos de soporte asociados a los módulos requeridos por el proyecto "micode-admin".
* Nombre y correo del desarrollador.

Una vez realizada la configuración indicada, ya podrás usar **miCode-Manager**. El primer proyecto registrado (para
referencia) es precisamente la interfaz de administración (micode-admin).

## Descargo

Esta propuesta se realiza considerando que pueda ser de utilidad a los desarrolladores (particularmente aquellos _freelance_), de modo que les ayude a administrar los diferentes scripts y librerías, propias o externas, que usen
en su trabajo.
