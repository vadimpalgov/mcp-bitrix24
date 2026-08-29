<?php

namespace Parfeon\Bitrix\Mcp\Tools;

use PhpMcp\Server\Attributes\McpTool;

class TaskTool
{
    #[McpTool(
        name: 'tasks_get_list',
        description: 'Получить список задач Bitrix24. Можно фильтровать по ответственному, статусу и группе.'
    )]
    public function getList(
        int $responsibleId = 0,
        int $groupId = 0,
        int $status = 0,
        int $limit = 20
    ): array {
        $filter = [];
        if ($responsibleId > 0) {
            $filter['RESPONSIBLE_ID'] = $responsibleId;
        }
        if ($groupId > 0) {
            $filter['GROUP_ID'] = $groupId;
        }
        if ($status > 0) {
            $filter['STATUS'] = $status;
        }

        $res = \CTasks::GetList(
            ['DEADLINE' => 'ASC'],
            $filter,
            ['ID', 'TITLE', 'STATUS', 'RESPONSIBLE_ID', 'DEADLINE', 'CREATED_DATE', 'GROUP_ID'],
            ['nPageSize' => $limit]
        );

        $tasks = [];
        while ($task = $res->GetNext()) {
            $tasks[] = $task;
        }

        return $tasks;
    }

    #[McpTool(
        name: 'tasks_create',
        description: 'Создать задачу Bitrix24.'
    )]
    public function create(
        string $title,
        int $responsibleId,
        string $description = '',
        int $createdBy = 0,
        int $groupId = 0,
        string $deadline = ''
    ): array {
        $fields = [
            'TITLE'           => $title,
            'RESPONSIBLE_ID'  => $responsibleId,
        ];
        if ($description !== '') {
            $fields['DESCRIPTION'] = $description;
        }
        if ($groupId > 0) {
            $fields['GROUP_ID'] = $groupId;
        }
        if ($deadline !== '') {
            $fields['DEADLINE'] = $deadline;
        }

        $params = [];
        if ($createdBy > 0) {
            $params['USER_ID'] = $createdBy;
        }

        $task = new \CTasks();
        $id = $task->Add($fields, $params);

        if (!$id) {
            return ['error' => $this->formatErrors($task->GetErrors()) ?: 'Не удалось создать задачу'];
        }

        return ['success' => true, 'id' => (int)$id];
    }

    #[McpTool(
        name: 'tasks_update',
        description: 'Изменить задачу Bitrix24 по ID. Передавать только те поля, которые нужно поменять.'
    )]
    public function update(
        int $taskId,
        string $title = '',
        string $description = '',
        int $responsibleId = 0,
        int $status = 0,
        string $deadline = ''
    ): array {
        $fields = [];
        if ($title !== '') {
            $fields['TITLE'] = $title;
        }
        if ($description !== '') {
            $fields['DESCRIPTION'] = $description;
        }
        if ($responsibleId > 0) {
            $fields['RESPONSIBLE_ID'] = $responsibleId;
        }
        if ($status > 0) {
            $fields['STATUS'] = $status;
        }
        if ($deadline !== '') {
            $fields['DEADLINE'] = $deadline;
        }

        if (empty($fields)) {
            return ['error' => 'Не переданы поля для изменения'];
        }

        $task = new \CTasks();
        $ok = $task->Update($taskId, $fields);

        if (!$ok) {
            return ['error' => $this->formatErrors($task->GetErrors()) ?: "Не удалось изменить задачу #$taskId"];
        }

        return ['success' => true, 'id' => $taskId];
    }

    #[McpTool(
        name: 'tasks_delete',
        description: 'Удалить задачу Bitrix24 по ID. Действие необратимо.'
    )]
    public function delete(int $taskId): array
    {
        $ok = \CTasks::Delete($taskId);

        if (!$ok) {
            return ['error' => "Не удалось удалить задачу #$taskId"];
        }

        return ['success' => true, 'id' => $taskId, 'deleted' => true];
    }

    private function formatErrors(array $errors): string
    {
        return implode('; ', array_column($errors, 'text'));
    }
}
