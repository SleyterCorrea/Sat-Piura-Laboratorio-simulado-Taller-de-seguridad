# Informe de Laboratorio: Explotación y Mitigación de XSS Almacenado (Defacement)

## 1. Explicación del Ataque

El **Cross-Site Scripting (XSS)** es una vulnerabilidad de la Capa de Aplicación (Capa 7 OSI) que ocurre cuando una aplicación web incluye datos no confiables en una página web sin la debida validación o codificación. Esto permite a un atacante ejecutar scripts maliciosos en el navegador de la víctima.

En este laboratorio nos enfocaremos exclusivamente en el **XSS Almacenado**, la variante más peligrosa. El script malicioso se guarda permanentemente en la base de datos del servidor (en este caso, en la tabla del historial de búsquedas públicas). 

Cada vez que cualquier usuario normal visita la página, el servidor extrae el script de la base de datos y lo ejecuta. Aprovecharemos esta vulnerabilidad para realizar un **Defacement**, que consiste en alterar visualmente toda la interfaz de la página web de forma permanente para todos los visitantes, bloqueando su uso legítimo.

---

## 2. Análisis Automatizado (Escaneo con OWASP ZAP)

Antes de realizar el ataque manual, un auditor de seguridad utiliza herramientas automatizadas para identificar posibles vulnerabilidades en la aplicación web.

1. **Preparación del Entorno:** Iniciar el laboratorio con `docker compose up -d` y abrir Kali Linux.
2. **Ejecutar OWASP ZAP:** Abrir la herramienta en Kali y seleccionar **Automated Scan**.
3. **Objetivo:** Ingresar la URL del buscador: `http://192.168.0.2:8080/buscar.php` y hacer clic en **Attack**.
4. **Resultados del Escaneo:** Al finalizar, ZAP arrojará una bandera roja (High) indicando la presencia de **Cross-Site Scripting (XSS)**. El escáner detecta que los datos ingresados en la barra de búsqueda se reflejan y se almacenan en el código fuente sin validación.

---

## 3. Prueba de Concepto (Explotación Manual - Defacement)

El escáner automatizado nos indicó que existe la vulnerabilidad. Ahora lo demostraremos manualmente con un ataque destructivo que afectará a todos los visitantes.

1. Navegar a la dirección del buscador en el navegador: `http://192.168.0.2:8080/buscar.php`.
2. En la barra de búsqueda, inyectar el payload diseñado para reescribir todo el cuerpo del sitio web:
   ```html
   <script>document.body.innerHTML='<h1 style=color:red;text-align:center;font-size:50px;margin-top:20%%>Hackeado Permanente!</h1>'</script>
   ```
3. Presionar **Buscar**. El navegador ejecutará el script, poniendo toda la pantalla en azul con el texto rojo gigante "Hackeado Permanente!".
4. **Comprobación de Persistencia:** Cerrar la pestaña, abrir una nueva y entrar directamente a la URL limpia `http://192.168.0.2:8080/buscar.php` (sin parámetros). La pantalla de defacement volverá a aparecer automáticamente. Esto prueba que el XSS es **Almacenado** en la base de datos, bloqueando la página permanentemente para cualquiera.

---

## 4. Pasos para la Mitigación

La regla de oro en el desarrollo seguro es: **Nunca confíes en los datos del usuario**. Toda salida hacia el navegador debe ser codificada.

En PHP, esto se logra envolviendo las variables con la función `htmlspecialchars()`. Esta función convierte caracteres especiales (como `<` y `>`) en entidades HTML (`&lt;` y `&gt;`), neutralizando el código malicioso.

### Corrección en el Código Fuente
Debemos abrir el archivo `lamp/buscar.php` y aplicar la protección en los **3 puntos vulnerables** descubiertos:

1. **El valor de la barra de búsqueda (XSS Reflejado):**
   ```php
   // Cambiar: echo $_GET['q'];
   echo htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8');
   ```

2. **El resultado de la búsqueda actual (XSS Reflejado):**
   ```php
   // Cambiar: echo $_GET['q'];
   echo htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8');
   ```

3. **El Historial Público (XSS Almacenado):**
   ```php
   // Cambiar: echo $row['busqueda'];
   echo htmlspecialchars($row['busqueda'], ENT_QUOTES, 'UTF-8');
   ```

### Comprobación Final
1. **Prueba manual:** Al intentar inyectar el payload nuevamente, el navegador ya no ejecutará el script. El código malicioso se imprimirá como texto plano inofensivo.
2. **Re-escaneo con ZAP:** Si se vuelve a correr el escáner (limpiando la sesión anterior), ZAP ya no podrá explotar el XSS. Si ZAP llegara a arrojar un *"XSS (DOM Based)"*, esto se clasifica como un **Falso Positivo**, ya que la validación manual demuestra que el código ha sido correctamente neutralizado por PHP en el servidor antes de llegar al DOM.
