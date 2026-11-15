<?php

namespace Parfeon\Bitrix\Mcp\Tools;

use Bitrix\Crm\Integration\AI\Function\Category\Stage\Create as StageCreate;
use Bitrix\Crm\Integration\AI\Function\Category\Stage\Delete as StageDelete;
use Bitrix\Crm\Integration\AI\Function\Category\Stage\Rename as StageRename;
use Bitrix\Crm\Item;
use Bitrix\Crm\PhaseSemantics;
use Bitrix\Crm\Service\Container;
use CCrmOwnerType;
use PhpMcp\Server\Attributes\McpTool;

class CrmStageTool
{
    private const MOVE_LIMIT = 100;

    #[McpTool(
        name: 'crm_get_categories',
        description: 'Получить список воронок (категорий/направлений) для указанного типа CRM-сущности: сделки (entityTypeId=2, по умолчанию) или смарт-процесса (entityTypeId = ENTITY_TYPE_ID из crm_get_smart_processes). Возвращает ID, NAME, SORT, IS_DEFAULT.'
    )]
    public function getCategories(int $entityTypeId = CCrmOwnerType::Deal): array
    {
        $factory = Container::getInstance()->getFactory($entityTypeId);
        if (!$factory) {
            return ['error' => "Фабрика не найдена для entityTypeId=$entityTypeId"];
        }

        return array_map(static fn($category) => $category->getData(), $factory->getCategories());
    }

    #[McpTool(
        name: 'crm_get_stages',
        description: 'Получить список стадий для указанного типа CRM-сущности (сделка или смарт-процесс) в указанной воронке (categoryId=0 — воронка по умолчанию). Возвращает STATUS_ID, NAME, SORT, SEMANTICS (P/S/F), COLOR.'
    )]
    public function getStages(int $entityTypeId = CCrmOwnerType::Deal, int $categoryId = 0): array
    {
        $factory = Container::getInstance()->getFactory($entityTypeId);
        if (!$factory) {
            return ['error' => "Фабрика не найдена для entityTypeId=$entityTypeId"];
        }

        return array_values(array_map(
            static fn($stage) => [
                'STATUS_ID' => $stage->getStatusId(),
                'NAME' => $stage->getName(),
                'SORT' => $stage->getSort(),
                'SEMANTICS' => $stage->getSemantics(),
                'COLOR' => $stage->getColor(),
            ],
            $factory->getStages($categoryId)->getAll(),
        ));
    }

    #[McpTool(
        name: 'crm_create_stage',
        description: 'Создать новую стадию в указанной воронке (categoryId) для сделки или смарт-процесса (entityTypeId). semantics: "P" — стадия в работе (по умолчанию), "F" — стадия провала. Стадии успеха (S) создавать нельзя — она у воронки одна и системная. sort задаёт порядок среди стадий той же семантики.'
    )]
    public function createStage(
        int $entityTypeId,
        int $categoryId,
        string $name,
        string $semantics = 'P',
        string $color = '',
        int $sort = 0
    ): array {
        $operation = new StageCreate(currentUserId: $this->getCurrentUserId());
        $result = $operation->invoke(
            entityTypeId: $entityTypeId,
            categoryId: $categoryId,
            fields: array_filter([
                'name' => $name,
                'semantics' => $semantics,
                'color' => $color ?: null,
                'sort' => $sort ?: null,
            ], static fn($v) => $v !== null),
        );

        if (!$result->isSuccess()) {
            return ['success' => false, 'error' => implode('; ', $result->getErrorMessages())];
        }

        return [
            'success' => true,
            'id' => $result->getData()['id'] ?? null,
            'entityTypeId' => $entityTypeId,
            'categoryId' => $categoryId,
            'name' => $name,
        ];
    }

    #[McpTool(
        name: 'crm_rename_stage',
        description: 'Переименовать стадию по её STATUS_ID в указанной воронке (сделка или смарт-процесс).'
    )]
    public function renameStage(int $entityTypeId, int $categoryId, string $stageId, string $name): array
    {
        $operation = new StageRename(currentUserId: $this->getCurrentUserId());
        $result = $operation->invoke(
            entityTypeId: $entityTypeId,
            categoryId: $categoryId,
            names: [['stageId' => $stageId, 'name' => $name]],
        );

        if (!$result->isSuccess()) {
            return ['success' => false, 'error' => implode('; ', $result->getErrorMessages())];
        }

        return ['success' => true, 'entityTypeId' => $entityTypeId, 'categoryId' => $categoryId, 'stageId' => $stageId, 'name' => $name];
    }

    #[McpTool(
        name: 'crm_delete_stage',
        description: 'Удалить пользовательскую стадию по её STATUS_ID из указанной воронки (сделка или смарт-процесс). Системные стадии (начало и успех воронки) удалить нельзя. Действие необратимо.'
    )]
    public function deleteStage(int $entityTypeId, int $categoryId, string $stageId): array
    {
        $operation = new StageDelete(currentUserId: $this->getCurrentUserId());
        $result = $operation->invoke(
            entityTypeId: $entityTypeId,
            categoryId: $categoryId,
            stageId: $stageId,
        );

        if (!$result->isSuccess()) {
            return ['success' => false, 'error' => implode('; ', $result->getErrorMessages())];
        }

        return ['success' => true, 'entityTypeId' => $entityTypeId, 'categoryId' => $categoryId, 'stageId' => $stageId, 'deleted' => true];
    }

    #[McpTool(
        name: 'crm_move_items_between_stages',
        description: 'Массово переместить элементы (максимум 100 за раз) со стадии "from" на стадию "to" в пределах одной воронки для указанного типа сущности (сделка или смарт-процесс). Используй для переноса группы элементов, а не для одного конкретного (для одного используй crm_update_item со STAGE_ID).'
    )]
    public function moveItemsBetweenStages(int $entityTypeId, int $categoryId, string $from, string $to): array
    {
        $factory = Container::getInstance()->getFactory($entityTypeId);
        if (!$factory) {
            return ['error' => "Фабрика не найдена для entityTypeId=$entityTypeId"];
        }

        $fromStage = $factory->getStageFromCategory($categoryId, $from);
        if ($fromStage === null) {
            return ['success' => false, 'error' => "Стадия \"$from\" не найдена в воронке $categoryId"];
        }

        $toStage = $factory->getStageFromCategory($categoryId, $to);
        if ($toStage === null) {
            return ['success' => false, 'error' => "Стадия \"$to\" не найдена в воронке $categoryId"];
        }

        $items = $factory->getItems([
            'select' => ['*'],
            'filter' => ['=' . Item::FIELD_NAME_STAGE_ID => $fromStage->getStatusId()],
            'limit' => self::MOVE_LIMIT,
            'order' => ['ID' => 'DESC'],
        ]);

        $errors = [];
        $movedCount = 0;
        foreach ($items as $item) {
            $item->set(Item::FIELD_NAME_STAGE_ID, $toStage->getStatusId());
            $item->set(Item::FIELD_NAME_STAGE_SEMANTIC_ID, $toStage->getSemantics() ?? PhaseSemantics::PROCESS);

            $result = $factory->getUpdateOperation($item)->launch();
            if ($result->isSuccess()) {
                $movedCount++;
            } else {
                $errors[] = implode('; ', $result->getErrorMessages());
            }
        }

        if ($errors) {
            return ['success' => false, 'error' => implode('; ', array_unique($errors)), 'moved' => $movedCount];
        }

        return [
            'success' => true,
            'entityTypeId' => $entityTypeId,
            'categoryId' => $categoryId,
            'from' => $from,
            'to' => $to,
            'moved' => $movedCount,
        ];
    }

    private function getCurrentUserId(): int
    {
        global $USER;

        return ($USER instanceof \CUser && $USER->IsAuthorized()) ? (int)$USER->GetID() : 1;
    }
}
