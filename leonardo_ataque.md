# Informe de Laboratorio: Explotación y Mitigación de XSS Almacenado (Defacement)

## 1. Explicación del Ataque

El **Cross-Site Scripting (XSS)** es una vulnerabilidad de la Capa de Aplicación (Capa 7 OSI) que ocurre cuando una aplicación web incluye datos no confiables en una página web sin la debida validación o codificación. Esto permite a un atacante ejecutar scripts maliciosos en el navegador de la víctima.

En este laboratorio nos enfocaremos exclusivamente en el **XSS Almacenado**, la variante más peligrosa. El script malicioso se guarda permanentemente en la base de datos del servidor (en este caso, en la tabla del historial de búsquedas públicas). 

Cada vez que cualquier usuario normal visita la página, el servidor extrae el script de la base de datos y lo ejecuta. Aprovecharemos esta vulnerabilidad para realizar un **Defacement**, que consiste en alterar visualmente toda la interfaz de la página web de forma permanente para todos los visitantes, bloqueando su uso legítimo.

---

## 2. Pasos a realizar para el Ataque (Explotación)

### Preparación del Entorno
1. Abrir la terminal en la carpeta del proyecto.
2. Iniciar el laboratorio ejecutando: `docker compose up -d --build`.
3. Desde Kali Linux, abrir el navegador web (ej. Firefox).

### Ejecución del Defacement (XSS Almacenado)
1. Navegar a la dirección del buscador en el laboratorio: `http://192.168.0.2:8080/buscar.php`.
2. En la barra de búsqueda, inyectar el payload diseñado para reescribir todo el cuerpo del sitio web:
   ```html
   <script>document.body.innerHTML='<h1 style=color:red;text-align:center;font-size:50px;margin-top:20%%>Hackeado Permanente!</h1>'</script>
   ```
3. Presionar **Buscar**. El navegador ejecutará el script, poniendo toda la pantalla en blanco con el texto rojo gigante "Hackeado Permanente!".
4. **Comprobación de Persistencia (Bloqueo Total):** Cerrar la pestaña actual, abrir una nueva y volver a entrar directamente a la URL limpia `http://192.168.0.2:8080/buscar.php` (sin parámetros).
5. **Resultado esperado:** La pantalla de defacement volverá a aparecer automáticamente sin tener que buscar nada. Esto prueba que el XSS es **Almacenado** en el historial de la base de datos, bloqueando la página permanentemente para cualquiera que intente usar el buscador.

---

## 3. Pasos para la Mitigación

La regla de oro en el desarrollo seguro es: **Nunca confíes en los datos del usuario**. Toda salida hacia el navegador, incluso si proviene de nuestra propia base de datos, debe ser sanitizada y codificada.

En PHP, esto se logra envolviendo las variables que imprimen entrada del usuario con la función `htmlspecialchars()`. Esta función convierte caracteres especiales (como `<` y `>`) en entidades HTML (`&lt;` y `&gt;`), evitando que el navegador los interprete como código JavaScript.

### Corrección en el Código Fuente
Para solucionar esta vulnerabilidad que permite el defacement, debemos abrir el archivo `lamp/buscar.php` y aplicar la siguiente protección a la salida del historial:

*   Ir a la línea **~141** (donde se imprime el historial extraído de la base de datos).
*   **Código Vulnerable:**
    ```php
    echo $row['busqueda'];
    ```
*   **Código Mitigado (Protegido):**
    ```php
    echo htmlspecialchars($row['busqueda'], ENT_QUOTES, 'UTF-8');
    ```

### Comprobación de la Mitigación
1. Guardar los cambios en el archivo `buscar.php`.
2. Volver al navegador e intentar inyectar el payload de defacement nuevamente.
3. **Resultado final:** El navegador ya no ejecutará el script ni alterará el diseño de la página. En su lugar, el código inyectado (las etiquetas `<script>`) se imprimirá literalmente en la pantalla como texto plano inofensivo dentro del recuadro del historial. El ataque ha sido neutralizado con éxito.
