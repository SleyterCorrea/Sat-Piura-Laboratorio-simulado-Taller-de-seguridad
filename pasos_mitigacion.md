# Pasos para la Mitigación del XSS (Reflejado y Almacenado)

La regla de oro en ciberseguridad es: **Nunca confíes en los datos del usuario**. Si vas a imprimir algo en pantalla que escribió un usuario (ya sea directamente desde la URL o desde la base de datos), debes "codificarlo" para que el navegador lo vea como texto inofensivo y no como código JavaScript ejecutable.

En PHP, usamos la función `htmlspecialchars()`.

## Tu Misión en el Laboratorio:
Abre tu archivo `lamp/buscar.php` en tu editor de código y localiza las siguientes tres líneas. Tu objetivo es envolver la variable vulnerable dentro de la función de protección:

### 1. Primera vulnerabilidad (XSS Reflejado en la barra de búsqueda):
* Ve a la **línea 89** aproximadamente.
* Verás el siguiente código vulnerable:
  ```php
  if (isset($_GET['q'])) { echo $_GET['q']; }
  ```
* **Cámbialo por:**
  ```php
  if (isset($_GET['q'])) { echo htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8'); }
  ```

### 2. Segunda vulnerabilidad (XSS Reflejado en el resultado):
* Ve a la **línea 114** aproximadamente.
* Verás el siguiente código vulnerable:
  ```php
  echo $_GET['q'];
  ```
* **Cámbialo por:**
  ```php
  echo htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8');
  ```

### 3. Tercera vulnerabilidad (XSS Almacenado en el Historial):
* Ve a la **línea 141** aproximadamente.
* Verás el siguiente código vulnerable:
  ```php
  echo $row['busqueda'];
  ```
* **Cámbialo por:**
  ```php
  echo htmlspecialchars($row['busqueda'], ENT_QUOTES, 'UTF-8');
  ```

## Comprobación
Una vez que hagas esos 3 cambios y guardes el archivo (`Ctrl + S`), ve a tu entorno controlado en Kali Linux y trata de inyectar de nuevo tu código malicioso (por ejemplo: `<script>alert('Ataque Exitoso')</script>`).

Verás que ya no se ejecuta ninguna alerta ni se modifica la página, sino que el texto se imprime de forma segura en la pantalla. ¡La vulnerabilidad ha sido mitigada exitosamente!
