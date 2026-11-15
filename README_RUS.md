# parfeon/mcp-bitrix24

> **Альфа-версия** — API и формат конфигурации могут измениться без предупреждения. Не рекомендуется для использования в production.

[English version](README.md)

Composer-пакет, запускающий MCP-сервер (Model Context Protocol) поверх Bitrix24. Позволяет AI-клиентам (Claude Desktop, Cursor и др.) читать и управлять данными CRM напрямую через чат.

## Как работает

Запускается как долгоживущий PHP-процесс в режиме CLI. Общается с MCP-клиентом через **stdio** (JSON-RPC по stdin/stdout) — стандартный транспорт протокола MCP. Клиент отправляет вызов инструмента, сервер выполняет его через Bitrix API и возвращает результат.

## Требования

- PHP 8.3+
- Bitrix24, установленный и доступный из того же окружения
- Composer

## Установка

```bash
composer require parfeon/mcp-bitrix24
```

## Конфигурация

Добавьте секцию `extra.mcp-bitrix24` в корневой `composer.json` проекта:

```json
{
  "extra": {
    "mcp-bitrix24": {
      "bitrix_root": "/var/www/bitrix",
      "admin_user_id": 1,
      "modules": ["crm", "tasks", "socialnetwork"]
    }
  }
}
```

| Параметр | Тип | По умолчанию | Описание |
|---|---|---|---|
| `bitrix_root` | string | авто | Абсолютный путь к корню Bitrix. Если не задан, пакет поднимается вверх по директориям в поиске `bitrix/modules/main/include/prolog_before.php`. |
| `admin_user_id` | int | `1` | ID пользователя Bitrix для авторизации внутренних API-вызовов. |
| `modules` | array | `["crm", "tasks", "socialnetwork"]` | Модули Bitrix для загрузки при старте. Добавьте `"iblock"` если используете поля типа iblock_element. |

Если `bitrix_root` не задан и авто-обнаружение не нашло Bitrix, сервер завершится с ошибкой:

```
[MCP] Bitrix root not found. Set extra.mcp-bitrix24.bitrix_root in composer.json
```

## Настройка MCP-клиента

### Claude Desktop

**Windows (Docker + WSL):**

```json
{
  "mcpServers": {
    "bitrix24": {
      "command": "wsl",
      "args": ["docker", "exec", "-i", "bitrix_php",
               "php", "/var/www/bitrix/local/modules/bit.mcp/extension/cli/server.php"]
    }
  }
}
```

Файл конфигурации: `%APPDATA%\Claude\claude_desktop_config.json`

**Linux / macOS (нативный сервер):**

```json
{
  "mcpServers": {
    "bitrix24": {
      "command": "php",
      "args": ["/var/www/bitrix/local/modules/bit.mcp/extension/cli/server.php"]
    }
  }
}
```

**Linux / macOS (Docker):**

```json
{
  "mcpServers": {
    "bitrix24": {
      "command": "docker",
      "args": ["exec", "-i", "bitrix_php",
               "php", "/var/www/bitrix/local/modules/bit.mcp/extension/cli/server.php"]
    }
  }
}
```

**Удалённый сервер через SSH:**

```json
{
  "mcpServers": {
    "bitrix24": {
      "command": "ssh",
      "args": ["user@your-server",
               "php", "/var/www/bitrix/local/modules/bit.mcp/extension/cli/server.php"]
    }
  }
}
```

Любой MCP-клиент с поддержкой stdio-транспорта работает по той же схеме.

## Доступные инструменты

| Инструмент | Описание |
|---|---|
| `crm_get_deals` | Список сделок с фильтрами по стадии и ответственному |
| `crm_get_deal` | Сделка по ID, включая поля UF_* |
| `crm_get_contacts` | Список контактов с поиском по имени |
| `crm_get_contact` | Контакт по ID |
| `crm_get_item` | Элемент любой CRM-сущности по `entityTypeId` и `id` |
| `crm_create_item` | Создать элемент CRM-сущности |
| `crm_update_item` | Обновить поля элемента CRM-сущности |
| `crm_delete_item` | Удалить элемент CRM-сущности (необратимо) |
| `crm_get_entity_fields` | Все поля сущности: ORM, UF_* и родительские связи |
| `crm_get_smart_processes` | Список всех смарт-процессов (пользовательских типов CRM) |
| `crm_get_categories` | Список воронок/категорий для сделки или смарт-процесса |
| `crm_get_stages` | Список стадий воронки для сделки или смарт-процесса |
| `crm_create_stage` | Создать стадию в воронке для сделки или смарт-процесса |
| `crm_rename_stage` | Переименовать стадию по STATUS_ID |
| `crm_delete_stage` | Удалить пользовательскую стадию по STATUS_ID (необратимо) |
| `crm_move_items_between_stages` | Массово переместить элементы (до 100) между стадиями |
| `crm_add_user_field` | Добавить пользовательское поле (UF_*) к CRM-сущности |
| `crm_update_user_field` | Обновить название / обязательность / сортировку поля |
| `crm_delete_user_field` | Удалить пользовательское поле (необратимо) |
| `iblock_get_elements` | Элементы инфоблока (для расшифровки значений iblock_element-полей) |
| `tasks_get_list` | Список задач с фильтрами |
| `tasks_create` | Создать задачу |
| `tasks_update` | Изменить задачу |
| `tasks_delete` | Удалить задачу (необратимо) |
| `groups_get_list` | Список групп/проектов с фильтрами |
| `groups_create` | Создать группу или проект |
| `groups_update` | Изменить группу или проект |
| `groups_delete` | Удалить группу или проект (необратимо) |

## Окружения запуска

Сервер не привязан к Docker. Работает в любом окружении, где PHP 8.3+ имеет доступ к файлам Bitrix:

| Окружение | Команда запуска |
|---|---|
| Нативный Linux / macOS | `php /path/to/cli/server.php` |
| Docker (Linux / macOS) | `docker exec -i bitrix_php php /path/to/cli/server.php` |
| Docker + WSL (Windows) | `wsl docker exec -i bitrix_php php /path/to/cli/server.php` |
| Удалённый сервер через SSH | `ssh user@host php /path/to/cli/server.php` |

> PHP CGI / FPM **не поддерживается** — сервер требует CLI-процесс со stdio.

## Ручной запуск

```bash
php /var/www/bitrix/local/modules/bit.mcp/extension/cli/server.php
```

Логи сервера пишутся в stderr и доступны в лог-файле MCP-клиента
(`%APPDATA%\Claude\logs\mcp-server-bitrix24.log` для Claude Desktop на Windows).

## Лицензия

Copyright (c) 2025 [Vadim Palgov](https://parfeon.dev)

Распространяется под лицензией [Elastic License 2.0](LICENSE). Запрещается перераспространять, перепродавать или переименовывать данное программное обеспечение и выдавать его за собственную разработку. Полные условия — в файлах [NOTICE](NOTICE) и [LICENSE](LICENSE).
