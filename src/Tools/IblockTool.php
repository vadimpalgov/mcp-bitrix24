<?php

namespace Parfeon\Bitrix\Mcp\Tools;

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;
use PhpMcp\Server\Attributes\McpTool;

class IblockTool
{
    #[McpTool(
        name: 'iblock_get_elements',
        description: 'Получить элементы инфоблока по его ID. Используется для расшифровки значений полей типа iblock_element / iblock_element_dp — чтобы понять, что стоит за числовым ID значения.'
    )]
    public function getElements(
        int    $iblockId,
        int    $limit = 50,
        string $search = '',
        int    $sectionId = 0
    ): array {
        Loader::includeModule('iblock');

        $filter = ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'];
        if ($search !== '') {
            $filter['%NAME'] = $search;
        }
        if ($sectionId > 0) {
            $filter['SECTION_ID'] = $sectionId;
        }

        $result = ElementTable::getList([
            'select' => ['ID', 'NAME', 'XML_ID', 'CODE', 'SORT'],
            'filter' => $filter,
            'order'  => ['SORT' => 'ASC', 'NAME' => 'ASC'],
            'limit'  => $limit,
        ]);

        return $result->fetchAll();
    }
}
