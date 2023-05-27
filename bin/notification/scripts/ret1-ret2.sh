#!/bin/sh
mlbStackPath='/var/www/html/MaarchCourrier/bin/notification/stack_letterbox_alerts.php'
eventStackPath='/var/www/html/MaarchCourrier/bin/notification/process_event_stack.php'
php $mlbStackPath   -c /var/www/html/MaarchCourrier/config/config.json
php $eventStackPath -c /var/www/html/MaarchCourrier/config/config.json -n RET1
php $eventStackPath -c /var/www/html/MaarchCourrier/config/config.json -n RET2
