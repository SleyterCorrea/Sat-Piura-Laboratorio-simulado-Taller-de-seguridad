# ============================================================
# Dockerfile — Imagen personalizada: Apache 2.4 + PHP 8.1
# Laboratorio de Pentesting OWASP Top 10
# ============================================================
FROM php:8.1-apache

# Instalar extensión PDO MySQL (necesaria para scripts seguros)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar el módulo de reescritura de Apache (común en webs reales)
RUN a2enmod rewrite

# ────────────────────────────────────────────────────────────
# CONFIGURACIÓN VULNERABLE (para demostración de Slowloris)
# ────────────────────────────────────────────────────────────
# Se copia la configuración del laboratorio que:
# 1. Aumenta el Timeout a 600s (muy permisivo)
# 2. NO habilita mod_reqtimeout (sin protección anti-Slowloris)
COPY lamp/apache2_lab.conf /etc/apache2/conf-available/lab.conf
RUN a2enconf lab

# El directorio raíz de la web apunta a /var/www/html
# que es mapeado como volumen desde ./lamp/ en docker-compose.yml

EXPOSE 80

CMD ["apache2-foreground"]
