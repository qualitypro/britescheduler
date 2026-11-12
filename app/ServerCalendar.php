<?php
declare(strict_types=1);

/**
 * Tenant-local calendar date boundaries. Values are wall-clock DATETIME strings.
 * Range end is exclusive, matching FullCalendar event feeds.
 */
final class ServerCalendar
{
    public static function range(string $date, string $view, int $weekStart = 0): array
    {
        if (!in_array($view, ['day','week','month'], true) || !in_array($weekStart,[0,1],true)) {
            throw new InvalidArgumentException('Invalid calendar view');
        }
        $day=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('UTC'));
        $errors=DateTimeImmutable::getLastErrors();
        if (!$day || $day->format('Y-m-d')!==$date ||
            ($errors!==false && ($errors['warning_count'] || $errors['error_count']))) {
            throw new InvalidArgumentException('Invalid calendar date');
        }
        if ($view==='day') {
            $start=$day;
            $end=$start->modify('+1 day');
        } elseif ($view==='week') {
            $weekday=(int)$day->format('w');
            $start=$day->modify('-'.(($weekday-$weekStart+7)%7).' days');
            $end=$start->modify('+7 days');
        } else {
            $first=$day->modify('first day of this month');
            $weekday=(int)$first->format('w');
            $start=$first->modify('-'.(($weekday-$weekStart+7)%7).' days');
            $last=$first->modify('last day of this month');
            $lastWeekday=(int)$last->format('w');
            $end=$last->modify('+'.((6-($lastWeekday-$weekStart+7)%7)+1).' days');
        }
        return ['start'=>$start->format('Y-m-d H:i:s'),'end'=>$end->format('Y-m-d H:i:s'),'view'=>$view,'date'=>$date];
    }
}
