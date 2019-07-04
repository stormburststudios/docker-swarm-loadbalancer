#!/usr/bin/env bash
sed -i "s/cgi.fix_pathinfo.*/cgi.fix_pathinfo=0/g" /etc/php/{{PHPVERSION}}/fpm/php.ini 
sed -i "s/upload_max_filesize.*/upload_max_filesize = 1024M/g" /etc/php/{{PHPVERSION}}/fpm/php.ini 
sed -i "s/post_max_size.*/post_max_size = 1024M/g" /etc/php/{{PHPVERSION}}/fpm/php.ini 
sed -i "s/max_execution_time.*/max_execution_time = 0/g" /etc/php/{{PHPVERSION}}/fpm/php.ini 
sed -i "s/variables_order.*/variables_order = \"EGPCS\"/g" /etc/php/{{PHPVERSION}}/fpm/php.ini 
sed -i "s/error_reporting.*/error_reporting = E_ALL \& \~E_DEPRECATED \& \~E_STRICT \& \~E_CORE_WARNING/g" /etc/php/{{PHPVERSION}}/fpm/php.ini 
cp /etc/php/{{PHPVERSION}}/fpm/php.ini /etc/php/{{PHPVERSION}}/cli/php.ini 
if [[ "{{PHPVERSION}}" = "5.6" ]] ; then
    # Skip setting clear_env
    echo "Skipping clear_env";
else
    echo "clear_env=no" >> /etc/php/{{PHPVERSION}}/fpm/php-fpm.conf
    echo "clear_env=no" >> /etc/php/{{PHPVERSION}}/fpm/pool.d/www.ini
fi
mkdir /run/php
