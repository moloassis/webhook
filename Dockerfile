FROM php:8.3-apache

# Habilita a extensão PDO MySQL para conexão com o banco
RUN docker-php-ext-install pdo pdo_mysql

# Habilita o mod_rewrite do Apache (caso precise de rotas amigáveis)
RUN a2enmod rewrite

# Copia os arquivos do seu projeto para o diretório padrão do Apache
COPY . /var/www/html/

# Dá permissão para o Apache ler e gravar na pasta
RUN chown -R www-data:www-data /var/www/html/
