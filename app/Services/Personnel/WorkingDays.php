<?php

namespace App\Services\Personnel;

use App\Models\PublicHoliday;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/** Décompte des jours ouvrés : samedis, dimanches et jours fériés exclus. */
class WorkingDays
{
    public static function count(CarbonInterface $debut, CarbonInterface $fin): int
    {
        $holidays = PublicHoliday::whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->pluck('date')->map(fn ($d) => $d->toDateString())->all();

        return collect(CarbonPeriod::create($debut, $fin))
            ->filter(fn (Carbon $d) => ! $d->isWeekend() && ! in_array($d->toDateString(), $holidays, true))
            ->count();
    }

    /**
     * Jours fériés de Côte d'Ivoire dont la date se calcule : fêtes civiles et chrétiennes.
     * Les fêtes musulmanes (Ramadan, Tabaski, Maouloud, Nuit du destin) dépendent de l'observation
     * de la lune : elles se saisissent chaque année dans le paramétrage.
     *
     * @return array<string, string> date => libellé
     */
    public static function computed(int $year): array
    {
        $easter = self::easter($year);

        return collect([
            "{$year}-01-01" => 'Jour de l’an',
            $easter->copy()->addDay()->toDateString() => 'Lundi de Pâques',
            "{$year}-05-01" => 'Fête du Travail',
            $easter->copy()->addDays(39)->toDateString() => 'Ascension',
            $easter->copy()->addDays(50)->toDateString() => 'Lundi de Pentecôte',
            "{$year}-08-07" => 'Fête de l’Indépendance',
            "{$year}-08-15" => 'Assomption',
            "{$year}-11-01" => 'Toussaint',
            "{$year}-11-15" => 'Journée nationale de la Paix',
            "{$year}-12-25" => 'Noël',
        ])->sortKeys()->all();
    }

    /** Dimanche de Pâques (algorithme de Meeus/Jones/Butcher, calendrier grégorien). */
    public static function easter(int $y): Carbon
    {
        $a = $y % 19;
        $b = intdiv($y, 100);
        $c = $y % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($y, $month, $day)->startOfDay();
    }

    public static function seed(int $year): int
    {
        $n = 0;
        foreach (self::computed($year) as $date => $libelle) {
            $n += PublicHoliday::firstOrCreate(['date' => $date], ['libelle' => $libelle])->wasRecentlyCreated ? 1 : 0;
        }

        return $n;
    }
}
