# Guía del Laboratorio y Remediaciones

Este directorio contiene los archivos necesarios para instruir a los alumnos sobre cómo solucionar las vulnerabilidades presentes en el entorno.

## 1. Vulnerabilidad: Inyección SQL (CWE-89)
**Problema en V1 (`web/consulta.php`):**
El código PHP toma el valor de `$_POST['documento']` y lo concatena directamente en la cadena de consulta SQL. Esto permite a un atacante introducir sintaxis SQL (ej. `' OR '1'='1`) alterando la lógica de la consulta original.

**Solución (`defensa/consulta_segura.php`):**
Se debe utilizar **Sentencias Preparadas (Prepared Statements)** mediante PDO o MySQLi. En la versión segura, la estructura de la consulta se define previamente con marcadores de posición (`?`). Los datos ingresados por el usuario se envían al motor de base de datos de manera separada, garantizando que nunca se interpreten como comandos ejecutables.

## 2. Vulnerabilidad: Denegación de Servicio (Slowloris)
**Problema en V2:**
El servidor Apache por defecto intenta mantener conexiones vivas esperando pacientemente a que los clientes lentos terminen de enviar sus datos. Herramientas como Slowloris explotan esto abriendo cientos de conexiones y enviando cabeceras HTTP a una velocidad de unos pocos bytes cada varios segundos, agotando el pool de conexiones (Workers) de Apache.

**Solución (`defensa/mitigacion_apache.conf`):**
La configuración a nivel de servidor web debe ser ajustada para desechar peticiones que tarden demasiado. En Apache, se utiliza el módulo `mod_reqtimeout` para establecer un tiempo límite estricto para recibir las cabeceras y el cuerpo de la petición HTTP.

## 3. Vulnerabilidad: Exposición de Servicios (Reconocimiento)
**Problema:**
El archivo `docker-compose.yml` está configurado intencionalmente de forma deficiente:
- Expone el puerto `3306` (MySQL) al host local.
- Mapea un puerto ficticio `2222` simulando un servicio SSH mal configurado en el contenedor web.

**Solución DevOps/Defensiva:**
En una arquitectura basada en contenedores, solo los servicios que interactúan directamente con el usuario (como el puerto `80`/`443` del proxy inverso o servidor web) deben publicarse (`ports` en docker-compose).
- La base de datos debe comunicarse únicamente a través de la red interna de Docker, por lo que su directiva `ports` debe ser eliminada por completo en un entorno de producción seguro.
- Los contenedores deben ser inmutables; no se debe correr SSH dentro de un contenedor web. El acceso administrativo se gestiona a través del demonio de Docker (`docker exec`).

## Limpieza del Entorno
Para detener y eliminar el entorno y su red de forma segura:
```bash
docker-compose down
```
