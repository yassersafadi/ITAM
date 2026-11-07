<?php
declare(strict_types=1);

namespace ITAM\Services;

/**
 * Organisation structure kept in the departments table: the executive office at the top (the sectors branch from
 * it), then sectors, departments and sections. A unit may sit under any unit of a higher level, or under none — some
 * organisations have sections only, others departments without sectors. Employees and assets point at any unit;
 * pickers show its full path (without the executive office, which every path would start with), and counts of a
 * unit include the units below it.
 */
final class OrgUnits
{
    /** From the top down. */
    public const LEVELS = ['executive', 'sector', 'department', 'section'];

    private static ?array $all = null;

    public static function flush(): void
    {
        self::$all = null;
    }

    /** @return array<int, array> every unit by id */
    public static function all(): array
    {
        if (self::$all === null) {
            self::$all = [];
            foreach (db()->all('SELECT id, name, code, level, parent_id FROM departments ORDER BY name') as $u) {
                $u['level'] = in_array($u['level'], self::LEVELS, true) ? $u['level'] : 'department';
                self::$all[(int) $u['id']] = $u;
            }
            // Units with a code come first in code order (S2 before S10), so a numbered structure reads as it was
            // written; the rest follow by name.
            uasort(self::$all, function ($a, $b) {
                $ca = trim((string) $a['code']);
                $cb = trim((string) $b['code']);
                if (($ca === '') !== ($cb === '')) {
                    return $ca === '' ? 1 : -1;
                }
                return ($ca !== '' ? strnatcasecmp($ca, $cb) : 0) ?: strcasecmp((string) $a['name'], (string) $b['name']);
            });
        }
        return self::$all;
    }

    public static function rank(string $level): int
    {
        $i = array_search($level, self::LEVELS, true);
        return $i === false ? 1 : $i;
    }

    /** A parent must be a unit of a higher level (so the structure can never loop). Null = no parent, always fine. */
    public static function validParent(string $level, ?int $parentId): bool
    {
        if ($parentId === null) {
            return true;
        }
        $p = self::all()[$parentId] ?? null;
        return $p !== null && self::rank($p['level']) < self::rank($level);
    }

    /** Parent of a unit as stored, or null when it is missing or not of a higher level. */
    private static function parentOf(array $u): ?int
    {
        $pid = $u['parent_id'] !== null ? (int) $u['parent_id'] : null;
        return $pid !== null && self::validParent($u['level'], $pid) ? $pid : null;
    }

    /**
     * Every unit in tree order (each followed by the units under it, in code order then by name), with its depth.
     * @return list<array>
     */
    public static function tree(): array
    {
        $children = [];
        foreach (self::all() as $id => $u) {
            $children[self::parentOf($u) ?? 0][] = $id;
        }
        $out = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$out, $children) {
            foreach ($children[$parent] ?? [] as $id) {
                $out[] = self::all()[$id] + ['depth' => $depth];
                $walk($id, $depth + 1);
            }
        };
        $walk(0, 0);
        return $out;
    }

    /** "Sector › Department › Section" for a unit, '' when unknown (the executive office only names itself). */
    public static function path(?int $id): string
    {
        $names = [];
        while ($id !== null && isset(self::all()[$id]) && count($names) < count(self::LEVELS)) {
            $u = self::all()[$id];
            if ($u['level'] === 'executive' && $names) {
                break;
            }
            array_unshift($names, $u['name']);
            $id = self::parentOf($u);
        }
        return implode(' › ', $names);
    }

    /** Choices for a picker, in tree order with full paths: [['id' => ..., 'name' => path], ...]. */
    public static function options(): array
    {
        return array_map(fn ($u) => ['id' => $u['id'], 'name' => self::path((int) $u['id'])], self::tree());
    }

    /** A unit and every unit under it. @return int[] */
    public static function descendants(int $id): array
    {
        $out = [$id];
        foreach (self::all() as $uid => $u) {
            if ($uid !== $id && in_array(self::parentOf($u), $out, true)) {
                $out[] = $uid;
            }
        }
        // A second pass catches grandchildren listed before their parent.
        foreach (self::all() as $uid => $u) {
            if (!in_array($uid, $out, true) && in_array(self::parentOf($u), $out, true)) {
                $out[] = $uid;
            }
        }
        return $out;
    }

    /** A unit and the units above it, nearest first. @return int[] */
    public static function lineage(int $id): array
    {
        $out = [];
        for ($u = self::all()[$id] ?? null; $u !== null && count($out) < count(self::LEVELS); $u = ($p = self::parentOf($u)) !== null ? (self::all()[$p] ?? null) : null) {
            $out[] = (int) $u['id'];
        }
        return $out;
    }

    /** Units directly under a unit. @return int[] */
    public static function children(int $id): array
    {
        return array_keys(array_filter(self::all(), fn ($u) => self::parentOf($u) === $id));
    }
}
