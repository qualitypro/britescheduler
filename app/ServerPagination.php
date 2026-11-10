<?php
declare(strict_types=1);

/** Validated, bounded page/limit parsing for tenant-scoped list APIs. */
final class ServerPagination
{
    public static function fromQuery(array $query, int $defaultSize = 25): array
    {
        $parse = static function (string $key, int $default, int $min, int $max) use ($query): int {
            $value = $query[$key] ?? (string)$default;
            if (!is_scalar($value) || !preg_match('/^[0-9]{1,8}$/D', (string)$value)) {
                throw new InvalidArgumentException('Invalid '.$key);
            }
            $number = (int)$value;
            if ($number < $min || $number > $max) {
                throw new InvalidArgumentException('Invalid '.$key);
            }
            return $number;
        };
        $page = $parse('page', 1, 1, 100000);
        $size = $parse('per_page', $defaultSize, 1, 100);
        return ['page'=>$page, 'per_page'=>$size, 'offset'=>($page-1)*$size];
    }

    public static function metadata(array $paging, int $total): array
    {
        return [
            'page'=>$paging['page'],
            'per_page'=>$paging['per_page'],
            'total'=>$total,
            'total_pages'=>max(1,(int)ceil($total/$paging['per_page'])),
            'has_next'=>$paging['offset']+$paging['per_page']<$total,
            'has_previous'=>$paging['page']>1
        ];
    }
}
