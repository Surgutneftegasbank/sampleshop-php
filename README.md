Тестовый магазин СНГБ Электронной коммерции (Slim + Twig + Redbean)
======================================================

[![Latest Stable Version]([![Latest Stable Version](https://poser.pugx.org/leaphly/cart-bundle/version.png)](https://packagist.org/packages/leaphly/cart-bundle)](https://packagist.org/packages/sngb/sngb)

Это пример Интернет магазина, который производит платежи с помощью пластиковых карт VISA и MASTERCARD. 
Платежи проводятся с помощью сервиса электронной коммерции СНГБ.

Используемые модули php:
* **Controller/Routing**: Slim ([codeguy/Slim](https://github.com/codeguy/Slim))
* **Model/Persistence/ORM**: RedBean ([gabordemooij/redbean](https://github.com/gabordemooij/redbean))
* **View/Template**: Twig ([fabpot/Twig](https://github.com/fabpot/Twig))
* **UI Toolkit**: Twitter Bootstrap ([twitter/bootstrap](https://github.com/twitter/bootstrap))

## Installation

###Self Managed Server

The instructions below assume you are running a **LAMP** stack in Ubuntu or any other **apt**-based distributions. To allow Slim to route with clean path syntax, you need to enable the url rewrite module.   

    sudo a2enmod rewrite
    sudo service apache2 restart

Optionally, if you want to run this demo with the default SQLite database, you need the driver

    sudo apt-get install php5-sqlite
    sqlite3 /var/www/simpleshop-php/storage/shopdb.sqlite
    >>.read .sqlite_createdb_sql
    >>.table payment
    >>> payment

Suppose your document root is in /var/www, clone the repository as follows:

    cd /var/www
    unzip simpleshop-php.zip

The required vendor libraries can be installed/updated using [Composer](http://getcomposer.org/). Go to the project root (where you see the file *composer.json*) and run the following command:

    cd ./simpleshop-php
    composer install

There are some directories should be made writeable to your web server process. 

    chmod -R 777 ./simpleshop-php/storage

Then, update your apache config file to set your document root to the **web** subdirectory. This helps to secure your scripts which should normally be put inside the **app/** folder.

    <VirtualHost *:80>
        DocumentRoot /var/www/simpleshop-php/
        ServerName example.com
    </VirtualHost>

##Structure

* index.php - intrance point/configuration, route.php - url mappings, database.php - db config
* **vendor/** contains the libraries for your application, and you can update them with composer.
* **templates/** is for your assets: js/css/img files. It should be the only folder publically available so your domain should point to this folder. `web/index.php` bootstraps the rest of the application.

##Writable Directory

* **storage/db/** contains SQLite database file.
* **templates/cache/twig/** contains the twig template cache.
