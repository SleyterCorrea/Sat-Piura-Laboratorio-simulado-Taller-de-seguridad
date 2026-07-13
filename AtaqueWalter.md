# Forced Browsing (Navegación Forzada)
## OWASP A01:2021 - Broken Access Control (Control de Acceso Roto)


## Documento Técnico: Laboratorio de Ciberseguridad y Auditoría Web (SATP)

## 1. Introducción y Contexto del Laboratorio

El presente documento detalla la ejecución de un laboratorio de ciberseguridad (Red Team / Blue Team) enfocado en evaluar y mitigar vulnerabilidades críticas basadas en el OWASP Top 10. Para garantizar un entorno ético y seguro ("Caja Blanca/Gris"), no se realizaron ataques contra infraestructuras gubernamentales en producción. En su lugar, se desplegó un entorno controlado e intensamente realista en una red aislada (Docker), replicando la superficie de ataque del portal web del Servicio de Administración Tributaria de Piura (SATP).

Este vector de ataque específico se centra en la vulnerabilidad **OWASP A01:2021 (Control de Acceso Roto)**, complementando la simulación integral del equipo donde también se evaluaron inyecciones SQL, ataques de Denegación de Servicio (DoS) y XSS Reflejado.

---

## 2. Fase de Preparación y Clonado de la Infraestructura

Para dotar de máximo realismo a la simulación, se procedió a construir una réplica exacta del objetivo:

1. **Clonado Frontend:** Se utilizó la herramienta **HTTrack Website Copier** para descargar y duplicar la estructura estática del portal principal del SAT Piura (HTML, CSS, imágenes y scripts del cliente).
2. **Desarrollo del Backend Simulado:** Una vez obtenido el cascarón estático, se programó la funcionalidad lógica mediante PHP y MySQL dentro de un contenedor Docker (`192.168.0.2`). Se construyó una "Oficina Virtual" completa que permitía visualizar deudas, reportes de contribuyentes y un panel de administración restringido. Esta arquitectura permitió evaluar las vulnerabilidades a nivel de código de aplicación sin comprometer sistemas reales.

---

## 3. Fase de Reconocimiento (Footprinting & Scanning)

Para fundamentar los vectores de ataque en el laboratorio local, primero se realizó una fase de Inteligencia de Fuentes Abiertas (OSINT) y escaneo pasivo sobre el dominio principal en producción.

Se ejecutó un escaneo de puertos específicos utilizando Nmap desde una distribución Kali Linux:

```bash
sudo nmap -p 80,443,3306,1433 web.satp.gob.pe

```

**Análisis de Resultados (Superficie de Ataque):**

* **80/tcp (HTTP) & 443/tcp (HTTPS) - OPEN:** Confirman la presencia del servidor web expuesto, habilitando los ataques de capa de aplicación (Capa 7).
* **3306/tcp (MySQL) - OPEN:** Representa una desconfiguración crítica de seguridad (OWASP A05), revelando que el motor de la base de datos es directamente accesible desde el exterior.
* **1433/tcp (MS-SQL) - FILTERED:** Puerto bloqueado o protegido por firewall.

Este descubrimiento justificó el diseño de un ataque enfocado en vulnerar las rutas y accesos del servidor web, asumiendo que un atacante buscaría aprovechar las desconfiguraciones del entorno.

---

## 4. Fase de Ataque y Explotación (Red Team)

Bajo un enfoque de "Caja Negra" (sin credenciales), el objetivo fue vulnerar la Oficina Virtual eludiendo la pantalla de autenticación principal.

**Vulnerabilidad Objetivo:** OWASP A01 - Control de Acceso Roto (Navegación Forzada / Fuzzing de Directorios).
**Herramienta:** `dirb` (Kali Linux - `192.168.0.10`).

El atacante lanzó una ráfaga automatizada contra el servidor local buscando directorios ocultos o mal configurados utilizando diccionarios de palabras comunes:

```bash
dirb http://192.168.0.2:8080/

```

**Resultado de la Explotación:**
En cuestión de segundos, la herramienta identificó una ruta expuesta con un código HTTP 200 (OK): `==> DIRECTORY: [http://192.168.0.2:8080/administrator/](http://192.168.0.2:8080/administrator/)`.

Al ingresar esta URL descubierta directamente en el navegador, el atacante logró un **Bypass total de Autenticación**. Accedió al panel interno de los trabajadores del SAT (Dashboard de Administración) con privilegios máximos, logrando visualizar DNIs, modificar multas y controlar el sistema sin haber ingresado ninguna credencial.

---

## 5. Fase de Detección (Blue Team)

Simultáneamente a la explotación, el tráfico de red fue monitoreado utilizando el Sistema de Detección de Intrusos (IDS) de **Security Onion**. El sistema demostró que la infraestructura defensiva no estaba ciega ante el ataque.

* **SGUIL (Consola de Eventos en Tiempo Real):** Capturó las firmas exactas de la herramienta. Los analistas pudieron observar alertas críticas rojas identificando la IP origen (`192.168.0.10`), clasificando el ataque bajo las firmas `ET SCAN DirBuster/Dirb User-Agent detected` y múltiples alertas de `403 Forbidden`. El IDS agrupó inteligentemente los miles de intentos en contadores (CNT), confirmando que era un script automatizado y no navegación humana.
* **Squert (Visualización de Datos):** El dashboard mostró un pico vertical masivo de eventos concentrados en un solo minuto. Esta anomalía visual fue catalogada como **Reconocimiento**, lo que en un entorno de producción permitiría a un firewall (IPS) bloquear la IP del atacante instantáneamente.

---

## 6. Fase de Mitigación y Remediación

Para parchar la vulnerabilidad descubierta y asegurar la Oficina Virtual, se implementó una estrategia defensiva a nivel de código de aplicación (Zero Trust).

El problema radicaba en que el panel de administración confiaba ciegamente en cualquier petición que conociera la URL, sin validar la identidad del solicitante. Para mitigarlo, se insertó un bloque de validación estricta de sesión en la línea 1 del archivo `administrator/index.php`:

```php
<?php
/*
// 1. Iniciar el motor de sesiones de PHP
session_start();

// 2. Control de Acceso: Validar si la sesión NO existe o si el rol NO es el autorizado
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    
    // 3. Destruir cualquier rastro de sesión inválida
    session_unset();
    session_destroy();

    // 4. Redirección forzada hacia el portal público
    header("Location: ../index.html");
    
    // 5. Detener la ejecución para evitar renderizar datos sensibles de fondo
    exit(); 
}
*/
?>

```

**Conclusión de la Remediación:**
Con este parche implementado, si una herramienta como `dirb` (o un atacante manual) vuelve a descubrir y solicitar la ruta `/administrator/`, el servidor evalúa la petición en milisegundos. Al no encontrar un token criptográfico de sesión (`PHPSESSID`) emitido por un inicio de sesión legítimo, expulsa inmediatamente al visitante al `index.html`. El ataque queda totalmente neutralizado, garantizando la confidencialidad e integridad del portal tributario.