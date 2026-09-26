#!/usr/bin/env bash
#
# Скачивание бэкапа с Яндекс.Диска. По умолчанию — только скачать и расшифровать.
# Заливка в базу выполняется ТОЛЬКО с флагом --apply.
#
set -Eeuo pipefail

CONF="${ZOVCORD_BACKUP_CONF:-/etc/zovcord-backup.conf}"
[ -r "$CONF" ] || { echo "Нет конфига: $CONF" >&2; exit 1; }
# shellcheck disable=SC1090
. "$CONF"

API="https://cloud-api.yandex.net/v1/disk"
YADISK_DIR="${YADISK_DIR:-app:/zovcord}"
OUT_DIR="${OUT_DIR:-/var/backups/zovcord/restore}"
APPLY=0
WHICH=""

while [ $# -gt 0 ]; do
  case "$1" in
    --apply) APPLY=1 ;;
    --file)  WHICH="$2"; shift ;;
    --list)  LIST_ONLY=1 ;;
    -h|--help)
      echo "Использование: $0 [--list] [--file db-2026-09-10-0330.sql.gz.gpg] [--apply]"
      exit 0 ;;
    *) echo "Неизвестный аргумент: $1" >&2; exit 1 ;;
  esac
  shift
done

urlenc() {
  local s="$1" out='' c i
  for ((i = 0; i < ${#s}; i++)); do
    c="${s:i:1}"
    case "$c" in
      [a-zA-Z0-9.~_-]) out+="$c" ;;
      *) out+="$(printf '%%%02X' "'$c")" ;;
    esac
  done
  printf '%s' "$out"
}

if [ -n "${YADISK_REFRESH_TOKEN:-}" ] && [ -n "${YADISK_CLIENT_ID:-}" ]; then
  YADISK_TOKEN="$(curl -fsS https://oauth.yandex.ru/token \
    -d grant_type=refresh_token -d refresh_token="$YADISK_REFRESH_TOKEN" \
    -d client_id="$YADISK_CLIENT_ID" -d client_secret="${YADISK_CLIENT_SECRET:-}" \
    | jq -r '.access_token')"
fi
yd() { curl -fsS -H "Authorization: OAuth $YADISK_TOKEN" "$@"; }

LIST="$(yd "$API/resources?path=$(urlenc "$YADISK_DIR")&limit=1000&sort=created&fields=_embedded.items.name,_embedded.items.size,_embedded.items.created,_embedded.items.type")"

if [ "${LIST_ONLY:-0}" = 1 ]; then
  printf '%s' "$LIST" | jq -r '._embedded.items[]? | select(.type=="file")
    | "\(.created)  \(.size/1048576*10|floor/10) МБ  \(.name)"'
  exit 0
fi

if [ -z "$WHICH" ]; then
  WHICH="$(printf '%s' "$LIST" | jq -r '[._embedded.items[]?
    | select(.type=="file") | select(.name | startswith("db-"))] | last | .name')"
  [ -n "$WHICH" ] && [ "$WHICH" != "null" ] || { echo "На Диске нет файлов db-*" >&2; exit 1; }
  echo "Последний бэкап базы: $WHICH"
fi

mkdir -p "$OUT_DIR"
HREF="$(yd "$API/resources/download?path=$(urlenc "$YADISK_DIR/$WHICH")" | jq -r '.href')"
curl -fsSL -o "$OUT_DIR/$WHICH" "$HREF"
echo "Скачано: $OUT_DIR/$WHICH"

FILE="$OUT_DIR/$WHICH"
if [[ "$FILE" == *.gpg ]]; then
  [ -n "${GPG_PASSPHRASE:-}" ] || { echo "Файл зашифрован, а GPG_PASSPHRASE пуст" >&2; exit 1; }
  gpg --batch --yes --quiet --passphrase "$GPG_PASSPHRASE" \
    --output "${FILE%.gpg}" --decrypt "$FILE"
  rm -f "$FILE"
  FILE="${FILE%.gpg}"
  echo "Расшифровано: $FILE"
fi

gzip -t "$FILE"
gzip -dc "$FILE" | tail -c 200 | grep -q 'Dump completed' \
  || { echo "ВНИМАНИЕ: в дампе нет маркера завершения — файл повреждён" >&2; exit 1; }
echo "Дамп целый: $(gzip -dc "$FILE" | grep -c '^CREATE TABLE') таблиц"

if [ "$APPLY" != 1 ]; then
  echo
  echo "Заливка не выполнялась. Чтобы залить в базу — перезапустите с --apply,"
  echo "или вручную:  gzip -dc \"$FILE\" | mysql -h HOST -P PORT -u USER -p БАЗА"
  exit 0
fi

ENV_FILE="$APP_DIR/.env"
env_get() { grep -E "^${1}=" "$ENV_FILE" | tail -1 | cut -d= -f2- \
  | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"; }
DB_NAME="$(env_get DB_DATABASE)"
DB_HOST_V="$(env_get DB_HOST)"

echo
echo "ЭТО ПЕРЕЗАПИШЕТ БАЗУ '$DB_NAME' НА ${DB_HOST_V:-127.0.0.1}. Данные будут потеряны."
read -rp "Введите имя базы для подтверждения: " CONFIRM
[ "$CONFIRM" = "$DB_NAME" ] || { echo "Не совпало, выходим." >&2; exit 1; }

CNF="$(mktemp)"; chmod 600 "$CNF"
cat > "$CNF" <<EOC
[client]
host=$(env_get DB_HOST)
port=$(env_get DB_PORT)
user=$(env_get DB_USERNAME)
password=$(env_get DB_PASSWORD)
EOC
gzip -dc "$FILE" | mysql --defaults-extra-file="$CNF" "$DB_NAME"
rm -f "$CNF"
echo "База восстановлена. Дальше: php artisan config:clear && php artisan migrate"
