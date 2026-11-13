<?php
declare(strict_types=1);

namespace BriteScheduler\Installation;

use DomainException;

/** Split migration SQL while respecting strings, identifiers and SQL comments. */
final class SqlStatements
{
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $state = 'normal';
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $ch = $sql[$i];
            $next = $sql[$i + 1] ?? '';
            if ($state === 'line') {
                if ($ch === "\n") { $state = 'normal'; $buffer .= "\n"; }
                continue;
            }
            if ($state === 'block') {
                if ($ch === '*' && $next === '/') { $state = 'normal'; $i++; $buffer .= ' '; }
                continue;
            }
            if ($state !== 'normal') {
                $buffer .= $ch;
                if ($ch === '\\' && $i + 1 < $length) { $buffer .= $sql[++$i]; continue; }
                if ($ch === $state) {
                    if ($next === $state) { $buffer .= $sql[++$i]; }
                    else $state = 'normal';
                }
                continue;
            }
            if ($ch === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) {
                $state = 'line'; $i++; $buffer .= ' '; continue;
            }
            if ($ch === '#') { $state = 'line'; $buffer .= ' '; continue; }
            if ($ch === '/' && $next === '*') {
                // Executable MySQL comments cannot safely be discarded.
                if (($sql[$i + 2] ?? '') === '!' || ($sql[$i + 2] ?? '') === '+') {
                    throw new DomainException('Executable or optimizer SQL comments require manual review');
                }
                $state = 'block'; $i++; $buffer .= ' '; continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') { $state = $ch; $buffer .= $ch; continue; }
            if ($ch === ';') {
                if (trim($buffer) !== '') $statements[] = trim($buffer);
                $buffer = ''; continue;
            }
            $buffer .= $ch;
        }
        if ($state !== 'normal' && $state !== 'line') throw new DomainException('Unterminated SQL literal or comment');
        if (trim($buffer) !== '') $statements[] = trim($buffer);
        if (!$statements) throw new DomainException('Migration has no SQL statements');
        return $statements;
    }
}
