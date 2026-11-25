#!/bin/sh
set -eu

for password in "$ESCALA_DEV_PASSWORD" "$ESCALA_TEST_PASSWORD"; do
    case "$password" in
        ''|*[!0-9a-f]*) echo 'As credenciais dos bancos devem conter apenas caracteres hexadecimais.' >&2; exit 1 ;;
    esac
    if [ "${#password}" -lt 32 ]; then
        echo 'As credenciais dos bancos devem ter pelo menos 32 caracteres.' >&2
        exit 1
    fi
done

export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"
mysql --protocol=socket -uroot <<SQL
CREATE DATABASE escalasimples CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE escalasimples_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER 'escala_dev'@'%' IDENTIFIED BY '${ESCALA_DEV_PASSWORD}';
CREATE USER 'escala_test'@'%' IDENTIFIED BY '${ESCALA_TEST_PASSWORD}';
GRANT ALL PRIVILEGES ON escalasimples.* TO 'escala_dev'@'%';
GRANT ALL PRIVILEGES ON escalasimples_test.* TO 'escala_test'@'%';
SQL
