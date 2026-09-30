#!/usr/bin/env bash
# Throwaway WordPress in Docker: activate the plugin, import a tagged PDF and a
# scan-style PDF, and check what the PDF check records for each. Tears down after.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"; PORT="${PORT:-8091}"
cleanup(){ docker rm -f lfwpdf-db lfwpdf-wp >/dev/null 2>&1 || true; docker network rm lfwpdf >/dev/null 2>&1 || true; }
cleanup; trap cleanup EXIT
docker network create lfwpdf >/dev/null
docker run -d --name lfwpdf-db --network lfwpdf -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wp -e MARIADB_USER=wp -e MARIADB_PASSWORD=wp mariadb:11 >/dev/null
docker run -d --name lfwpdf-wp --network lfwpdf -p $PORT:80 -e WORDPRESS_DB_HOST=lfwpdf-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp -v "$HERE:/var/www/html/wp-content/plugins/lfw-pdf-check:ro" wordpress:6.8-php8.2-apache >/dev/null
for i in $(seq 1 60); do docker exec lfwpdf-db mariadb -uwp -pwp -e 'select 1' wp >/dev/null 2>&1 && curl -s -o /dev/null "http://localhost:$PORT/" && break; sleep 2; done
WP="docker run --rm --network lfwpdf --volumes-from lfwpdf-wp --user 33:33 -e WORDPRESS_DB_HOST=lfwpdf-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp wordpress:cli-php8.2"
docker exec lfwpdf-wp bash -c 'mkdir -p /var/www/html/wp-content/uploads /var/www/html/pdftest && chown www-data:www-data /var/www/html/wp-content /var/www/html/wp-content/uploads /var/www/html/pdftest'
$WP wp core install --url="http://localhost:$PORT" --title=Test --admin_user=admin --admin_password=admin --admin_email=a@b.co --skip-email >/dev/null
$WP wp plugin activate lfw-pdf-check >/dev/null
docker cp "$HERE/testpdfs/tagged.pdf" lfwpdf-wp:/var/www/html/pdftest/tagged.pdf
docker cp "$HERE/testpdfs/scan.pdf" lfwpdf-wp:/var/www/html/pdftest/scan.pdf
docker exec lfwpdf-wp chmod 644 /var/www/html/pdftest/tagged.pdf /var/www/html/pdftest/scan.pdf
T=$($WP wp media import /var/www/html/pdftest/tagged.pdf --porcelain)
S=$($WP wp media import /var/www/html/pdftest/scan.pdf --porcelain)
echo "tagged: $($WP wp post meta get "$T" _lfw_pdf_check --format=json)"
echo "scan:   $($WP wp post meta get "$S" _lfw_pdf_check --format=json)"
$WP wp lfw-pdf-check
