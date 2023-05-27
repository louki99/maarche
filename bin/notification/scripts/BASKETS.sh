#!/bin/sh
eventStackPath='/var/www/html/MaarchCourrier/bin/notification/basket_event_stack.php'
php $eventStackPath -c /var/www/html/MaarchCourrier/config/config.json -n BASKETS
