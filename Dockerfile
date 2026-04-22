# Dockerfile — Ambiente PHP para Atividade 4.1 (GCP)
FROM php:8.2-apache

# Instala dependências do sistema e do PHP
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instala o Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Habilita o mod_rewrite do Apache
RUN a2enmod rewrite

# Define o diretório de trabalho
WORKDIR /var/www/html

# Ajusta permissões
RUN chown -R www-data:www-data /var/www/html
