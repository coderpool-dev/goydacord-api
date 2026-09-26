#!/usr/bin/env bash
#
# Разовое получение refresh_token Яндекс.Диска. Запускать на машине с браузером.
# Результат вставить в /etc/zovcord-backup.conf на сервере.
#
set -Eeuo pipefail

CLIENT_ID="${1:-}"
CLIENT_SECRET="${2:-}"
if [ -z "$CLIENT_ID" ] || [ -z "$CLIENT_SECRET" ]; then
  echo "Использование: $0 <client_id> <client_secret>" >&2
  exit 1
fi

command -v jq >/dev/null || { echo "Нужен jq" >&2; exit 1; }

AUTH_URL="https://oauth.yandex.ru/authorize?response_type=code&client_id=${CLIENT_ID}"

cat <<EOT

Перед началом убедитесь, что в настройках приложения на oauth.yandex.ru:
  • выдано право  cloud_api:disk.app_folder  (доступ только к своей папке)
  • в Redirect URI добавлен  https://oauth.yandex.ru/verification_code

1) Откройте ссылку и подтвердите доступ:

   ${AUTH_URL}

2) Яндекс покажет код подтверждения — введите его сюда.

EOT

read -rp "Код подтверждения: " CODE
[ -n "$CODE" ] || { echo "Пустой код" >&2; exit 1; }

RESP="$(curl -fsS https://oauth.yandex.ru/token \
  -d grant_type=authorization_code \
  -d code="$CODE" \
  -d client_id="$CLIENT_ID" \
  -d client_secret="$CLIENT_SECRET")"

ACCESS="$(printf '%s' "$RESP" | jq -r '.access_token // empty')"
REFRESH="$(printf '%s' "$RESP" | jq -r '.refresh_token // empty')"
[ -n "$ACCESS" ] || { echo "Не получилось: $RESP" >&2; exit 1; }

echo
echo "Готово. Вставьте в /etc/zovcord-backup.conf:"
echo
echo "YADISK_CLIENT_ID=\"$CLIENT_ID\""
echo "YADISK_CLIENT_SECRET=\"$CLIENT_SECRET\""
echo "YADISK_REFRESH_TOKEN=\"$REFRESH\""
echo
echo "Проверка доступа к Диску:"
curl -fsS -H "Authorization: OAuth $ACCESS" https://cloud-api.yandex.net/v1/disk/ \
  | jq '{used_space, total_space, user: .user.login}'
