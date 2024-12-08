FROM php:8.2.12-apache-bookworm
RUN apt update && apt install -y git
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    mv composer.phar /usr/local/bin/composer
RUN apt-get update && apt-get install -y \
		libzip-dev && \
    docker-php-ext-configure zip && \
	docker-php-ext-install -j$(nproc) zip
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

