FROM php:8.2.12-apache-bookworm
RUN apt update && apt install -y git
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === 'e21205b207c3ff031906575712edab6f13eb0b361f2085f1f1237b7126d785e826a450292b6cfd1d64d92e6563bbde02') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    mv composer.phar /usr/local/bin/composer
RUN a2enmod rewrite
RUN pecl install pcov && docker-php-ext-enable pcov
RUN mkdir -p /opt/podsumer/
RUN mkdir -p /opt/podsumer/conf
COPY ./apache.conf /etc/apache2/sites-available/000-default.conf
COPY ./apache.conf /etc/apache2/sites-enabled/000-default.conf
COPY ./conf/podsumer.conf /opt/podsumer/conf/podsumer.conf
COPY ./sql /opt/podsumer/sql
COPY ./src /opt/podsumer/src
COPY ./templates /opt/podsumer/templates
COPY ./www /opt/podsumer/www
COPY ./composer.json /opt/podsumer/composer.json
COPY ./composer.lock /opt/podsumer/composer.lock
WORKDIR /opt/podsumer
RUN chown -R www-data:www-data /opt/podsumer
RUN chmod -R 755 /opt/podsumer
RUN chown -R www-data:www-data /etc/apache2/sites-available/000-default.conf
RUN chown -R www-data:www-data /etc/apache2/sites-enabled/000-default.conf
RUN composer dump-autoload
EXPOSE 3094

