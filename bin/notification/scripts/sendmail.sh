#!/bin/sh
emailStackPath='/var/www/html/MaarchCourrier/bin/notification/process_email_stack.php'
php $emailStackPath -c /var/www/html/MaarchCourrier/config/config.json

