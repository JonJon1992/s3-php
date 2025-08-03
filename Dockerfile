FROM php:8.3-cli

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensões PHP
RUN docker-php-ext-install \
    zip \
    json \
    openssl

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Definir diretório de trabalho
WORKDIR /var/www/html

# Copiar arquivos do projeto
COPY . .

# Instalar dependências
RUN composer install --no-interaction --optimize-autoloader

# Criar diretório para logs
RUN mkdir -p /var/www/html/logs

# Expor porta (se necessário)
EXPOSE 8000

# Comando padrão
CMD ["php", "-S", "0.0.0.0:8000"] 