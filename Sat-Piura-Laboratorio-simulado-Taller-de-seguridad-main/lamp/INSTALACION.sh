# ============================================================
# GUÍA DE INSTALACIÓN — LABORATORIO NATIVO EN UBUNTU LAMP
# ============================================================
# Proyecto: Laboratorio Educativo de Ciberseguridad
# Entorno:  Ubuntu Desktop 20.04 / 22.04 + LAMP
# ============================================================


# ============================================================
# PASO 1 — INSTALAR EL ENTORNO LAMP
# ============================================================

# Actualizar repositorios
sudo apt update && sudo apt upgrade -y

# Instalar Apache, MySQL y PHP con extensiones necesarias
sudo apt install -y apache2 mysql-server php libapache2-mod-php php-mysql

# Verificar que Apache y MySQL estén corriendo
sudo systemctl status apache2
sudo systemctl status mysql


# ============================================================
# PASO 2 — PREPARAR LOS ARCHIVOS DEL LABORATORIO
# ============================================================

# La raíz web de Apache en Ubuntu es /var/www/html/
# Copiar todos los archivos del laboratorio allí:

sudo cp index.html        /var/www/html/
sudo cp login.php         /var/www/html/
sudo cp login_seguro.php  /var/www/html/
sudo cp logo.png          /var/www/html/
sudo cp fondo.png         /var/www/html/

# Dar permisos correctos
sudo chown -R www-data:www-data /var/www/html/
sudo chmod -R 755 /var/www/html/


# ============================================================
# PASO 3 — CONFIGURAR MYSQL Y CARGAR LA BASE DE DATOS
# ============================================================

# Configurar MySQL (establecer contraseña para root)
sudo mysql_secure_installation

# Cargar el script SQL del laboratorio
sudo mysql -u root -p < /var/www/html/database.sql

# O de forma interactiva:
# sudo mysql -u root -p
# mysql> source /var/www/html/database.sql;

# Ajustar la contraseña en login.php y login_seguro.php
# según la que haya definido durante mysql_secure_installation
# Variable a modificar: $db_pass = 'TU_CONTRASEÑA_AQUI';


# ============================================================
# PASO 4 — CONFIGURAR APACHE (MODO VULNERABLE - Slowloris)
# ============================================================

# Editar la configuración principal de Apache
sudo nano /etc/apache2/apache2.conf

# Añadir al final del archivo (copiar del archivo apache2_lab.conf):
#   Timeout 600
#
# Deshabilitar el módulo de protección reqtimeout:
sudo a2dismod reqtimeout
sudo systemctl restart apache2


# ============================================================
# PASO 5 — APLICAR HARDENING (MODO SEGURO)
# ============================================================

# Editar apache2.conf y añadir/modificar:
#   ServerTokens Prod
#   ServerSignature Off
#
# Habilitar reqtimeout:
sudo a2enmod reqtimeout
# Añadir en apache2.conf dentro de <IfModule reqtimeout_module>:
#   RequestReadTimeout header=20-40,MinRate=500 body=20,MinRate=500
#
sudo systemctl restart apache2

# Verificar que el banner fue ocultado:
curl -I http://localhost
# Debería mostrar: Server: Apache (sin versión)


# ============================================================
# PASO 6 — VERIFICAR EL LABORATORIO
# ============================================================

# Abrir en el navegador del Ubuntu:
#   http://localhost

# Prueba de SQL Injection (V1 vulnerable):
#   Usuario:    ' OR '1'='1
#   Contraseña: cualquiercosa
#   → Debería ingresar sin credenciales válidas

# Prueba de mitigación (V2 seguro):
#   Cambiar en index.html: action="login.php" → action="login_seguro.php"
#   Repetir el mismo payload → Debería denegar el acceso


# ============================================================
# ESTRUCTURA FINAL DE ARCHIVOS EN /var/www/html/
# ============================================================
#
# /var/www/html/
# ├── index.html          ← Formulario de login (frontend)
# ├── login.php           ← Backend VULNERABLE (V1 - SQLi)
# ├── login_seguro.php    ← Backend SEGURO (Remediación PDO)
# ├── database.sql        ← Script de base de datos
# ├── logo.png            ← Logo institucional del lab
# └── fondo.png           ← Imagen de fondo tecnológica
