#!/bin/sh
eventStackPath='/var/www/html/MaarchCourrier/bin/notification/process_event_stack.php'
php $eventStackPath -c /var/www/html/MaarchCourrier/config/config.json -n NCC
php $eventStackPath -c /var/www/html/MaarchCourrier/config/config.json -n ANC
php $eventStackPath -c /var/www/html/MaarchCourrier/config/config.json -n AND
php $eventStackPath -c /var/www/html/MaarchCourrier/config/config.json -n RED
