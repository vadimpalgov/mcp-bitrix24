<?php

namespace Parfeon\Bitrix\Mcp\Tools;

use Bitrix\Crm\Category\DealCategory;
use Bitrix\Crm\Integration\AI\Function\Category\Stage\Create as StageCreate;
use Bitrix\Crm\Integration\AI\Function\Category\Stage\Delete as StageDelete;
use Bitrix\Crm\Integration\AI\Function\Category\Stage\Rename as StageRename;
use Bitrix\Crm\Integration\AI\Function\Deal\MoveBetweenStage;
use CCrmOwnerType;
use PhpMcp\Server\Attributes\McpTool;

class CrmDealStageTool
{
    #[McpTool(
        name: 'crm_get_deal_categories',
        description: 'Получить список воронок (направлений) сделок CRM Bitrix24: ID, название, сортировка, признак воронки по умолчанию.'
    )]
    public function getDealCategories(): array
    {
        return DealCategory::getAll(true);
    }

    #[McpTool(
        name: 'crm_get_deal_stages',
        description: 'Получить список стадий сделки для указанной воронки (categoryId=0 — воронка по умолчанию). Возвращает STATUS_ID, NAME, SORT, SEMANTICS (P/S/F), COLOR.'
    )]
    public function getDealStages(int $categoryId = 0): array
    {
        return array_values(DealCategory::getStageInfos($categoryId));
    }

    #[McpTool(
        name: 'crm_create_deal_stage',
        description: 'Создать новую стадию сделки в указанной воронке (funnel/category). semantics: "P" — стадия в работе (по умолчанию), "F" — стадия провала. Стадии успеха (S) создавать нельзя — она у воронки одна и системная. sort задаёт порядок среди стадий той же семантики.'
    )]
    public function createDealStage(
        int $categoryId,
        string $name,
        string $semantics = 'P',
        string $color = '',
        int $sort = 0
    ): array {
        $operation = new StageCreate(currentUserId: $this->getCurrentUserId());
        $result = $operation->invoke(
            entityTypeId: CCrmOwnerType::Deal,
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

        return ['success' => true, 'id' => $result->getData()['id'] ?? null, 'categoryId' => $categoryId, 'name' => $name];
    }

    #[McpTool(
        name: 'crm_rename_deal_stage',
        description: 'Переименовать стадию сделки по её STATUS_ID (идентификатору стадии) в указанной воронке.'
    )]
    public function renameDealStage(int $categoryId, string $stageId, string $name): array
    {
        $operation = new StageRename(currentUserId: $this->getCurrentUserId());
        $result = $operation->invoke(
            entityTypeId: CCrmOwnerType::Deal,
            categoryId: $categoryId,
            names: [['stageId' => $stageId, 'name' => $name]],
        );

        if (!$result->isSuccess()) {
            return ['success' => false, 'error' => implode('; ', $result->getErrorMessages())];
        }

        return ['success' => true, 'categoryId' => $categoryId, 'stageId' => $stageId, 'name' => $name];
    }

    #[McpTool(
        name: 'crm_delete_deal_stage',
        description: 'Удалить пользовательскую стадию сделки по её STATUS_ID из указанной воронки. Системные стадии (начало и успех воронки) удалить нельзя. Действие необратимо.'
    )]
    public function deleteDealStage(int $categoryId, string $stageId): array
    {
        $operation = new StageDelete(currentUserId: $this->getCurrentUserId());
        $result = $operation->invoke(
            entityTypeId: CCrmOwnerType::Deal,
            categoryId: $categoryId,
            stageId: $stageId,
        );

        if (!$result->isSuccess()) {
            return ['success' => false, 'error' => implode('; ', $result->getErrorMessages())];
        }

        return ['success' => true, 'categoryId' => $categoryId, 'stageId' => $stageId, 'deleted' => true];
    }

    #[McpTool(
        name: 'crm_move_deals_between_stages',
        description: 'Массово переместить сделки (максимум 100 за раз) со стадии "from" на стадию "to" в пределах одной воронки. Используй для переноса всех сделок с одной стадии на другую, а не для перемещения одной конкретной сделки (для одной сделки используй crm_update_item со STAGE_ID).'
    )]
    public function moveDealsBetweenStages(int $categoryId, string $from, string $to): array
    {
        $operation = new MoveBetweenStage(currentUserId: $this->getCurrentUserId());
        $result = $operation->invoke(categoryId: $categoryId, from: $from, to: $to);

        if (!$result->isSuccess()) {
            return ['success' => false, 'error' => implode('; ', $result->getErrorMessages())];
        }

        return ['success' => true, 'categoryId' => $categoryId, 'from' => $from, 'to' => $to];
    }

    private function getCurrentUserId(): int
    {
        global $USER;

        return ($USER instanceof \CUser && $USER->IsAuthorized()) ? (int)$USER->GetID() : 1;
    }
}
