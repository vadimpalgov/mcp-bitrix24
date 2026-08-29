<?php

namespace Parfeon\Bitrix\Mcp\Tools;

use PhpMcp\Server\Attributes\McpTool;

class GroupTool
{
    #[McpTool(
        name: 'groups_get_list',
        description: 'Получить список групп/проектов Bitrix24 (модуль "Рабочие группы"). Проекты — это группы с PROJECT=Y.'
    )]
    public function getList(
        int $ownerId = 0,
        string $project = '',
        int $limit = 20
    ): array {
        \Bitrix\Main\Loader::includeModule('socialnetwork');

        $filter = [];
        if ($ownerId > 0) {
            $filter['OWNER_ID'] = $ownerId;
        }
        if ($project === 'Y' || $project === 'N') {
            $filter['PROJECT'] = $project;
        }

        $res = \CSocNetGroup::GetList(
            ['ID' => 'DESC'],
            $filter,
            false,
            ['nTopCount' => $limit],
            ['ID', 'NAME', 'DESCRIPTION', 'OWNER_ID', 'PROJECT', 'VISIBLE', 'OPENED', 'CLOSED', 'SUBJECT_ID', 'DATE_CREATE']
        );

        $groups = [];
        while ($group = $res->Fetch()) {
            $groups[] = $group;
        }

        return $groups;
    }

    #[McpTool(
        name: 'groups_create',
        description: 'Создать группу или проект Bitrix24 (модуль "Рабочие группы"). Для проекта передать project=Y. subjectId — тематика группы (b_sonet_group_subject), если не передать — берётся первая доступная.'
    )]
    public function create(
        string $name,
        int $ownerId,
        string $description = '',
        string $project = 'N',
        int $subjectId = 0,
        string $siteId = 's1',
        string $visible = 'Y',
        string $opened = 'N',
        string $closed = 'Y'
    ): array {
        \Bitrix\Main\Loader::includeModule('socialnetwork');
        \Bitrix\Main\Loader::includeModule('tasks');

        if ($subjectId <= 0) {
            $subjectId = $this->getDefaultSubjectId();
        }
        if ($subjectId <= 0) {
            return ['error' => 'Не удалось определить тематику группы (subjectId), в системе не настроено ни одной тематики'];
        }

        $now = new \Bitrix\Main\Type\DateTime();
        $fields = [
            'NAME'            => $name,
            'DESCRIPTION'     => $description,
            'OWNER_ID'        => $ownerId,
            'SITE_ID'         => $siteId,
            'SUBJECT_ID'      => $subjectId,
            'PROJECT'         => $project,
            'VISIBLE'         => $visible,
            'OPENED'          => $opened,
            'CLOSED'          => $closed,
            // без прав на приглашение/отправку сообщений Bitrix отказывает в создании группы
            'INITIATE_PERMS'  => SONET_ROLES_MODERATOR,
            'SPAM_PERMS'      => SONET_ROLES_MODERATOR,
            // NOT NULL-колонки без значения по умолчанию — иначе фоновые обработчики
            // модуля tasks падают на пустой ACTIVITY_DATE
            'DATE_CREATE'     => $now,
            'DATE_UPDATE'     => $now,
            'DATE_ACTIVITY'   => $now,
        ];

        $id = \CSocNetGroup::Add($fields);

        if (!$id) {
            return ['error' => $this->getLastApplicationError() ?: 'Не удалось создать группу'];
        }

        return ['success' => true, 'id' => (int)$id];
    }

    #[McpTool(
        name: 'groups_update',
        description: 'Изменить группу или проект Bitrix24 по ID. Передавать только те поля, которые нужно поменять.'
    )]
    public function update(
        int $groupId,
        string $name = '',
        string $description = '',
        int $ownerId = 0,
        string $visible = '',
        string $opened = '',
        string $closed = ''
    ): array {
        \Bitrix\Main\Loader::includeModule('socialnetwork');

        $fields = [];
        if ($name !== '') {
            $fields['NAME'] = $name;
        }
        if ($description !== '') {
            $fields['DESCRIPTION'] = $description;
        }
        if ($ownerId > 0) {
            $fields['OWNER_ID'] = $ownerId;
        }
        if ($visible === 'Y' || $visible === 'N') {
            $fields['VISIBLE'] = $visible;
        }
        if ($opened === 'Y' || $opened === 'N') {
            $fields['OPENED'] = $opened;
        }
        if ($closed === 'Y' || $closed === 'N') {
            $fields['CLOSED'] = $closed;
        }

        if (empty($fields)) {
            return ['error' => 'Не переданы поля для изменения'];
        }

        $ok = \CSocNetGroup::Update($groupId, $fields);

        if (!$ok) {
            return ['error' => $this->getLastApplicationError() ?: "Не удалось изменить группу #$groupId"];
        }

        return ['success' => true, 'id' => $groupId];
    }

    #[McpTool(
        name: 'groups_delete',
        description: 'Удалить группу или проект Bitrix24 по ID. Действие необратимо: удаляются все связанные задачи, файлы и обсуждения группы.'
    )]
    public function delete(int $groupId): array
    {
        \Bitrix\Main\Loader::includeModule('socialnetwork');

        $ok = \CSocNetGroup::Delete($groupId);

        if (!$ok) {
            return ['error' => $this->getLastApplicationError() ?: "Не удалось удалить группу #$groupId"];
        }

        return ['success' => true, 'id' => $groupId, 'deleted' => true];
    }

    private function getDefaultSubjectId(): int
    {
        $res = \CSocNetGroupSubject::GetList(['ID' => 'ASC'], [], false, ['nTopCount' => 1]);
        $subject = $res->Fetch();

        return $subject ? (int)$subject['ID'] : 0;
    }

    private function getLastApplicationError(): string
    {
        global $APPLICATION;

        $messages = [];
        while ($e = $APPLICATION->GetException()) {
            $messages[] = $e->GetString();
        }

        return implode('; ', $messages);
    }
}
