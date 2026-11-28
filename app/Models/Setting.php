<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Réglages de l'application modifiables dans Administration > Paramètres.
 * Ils remplacent, au démarrage, les valeurs de config/sigaci.php (elles-mêmes lues dans .env) : sans réglage
 * enregistré, c'est la valeur du fichier .env (ou la valeur par défaut) qui s'applique.
 */
class Setting extends Model
{
    /** Réglage => clé de configuration qu'il remplace. */
    public const CONFIG = [
        'sigle' => 'sigaci.sigle',
        'nom' => 'sigaci.nom',
    ];

    private const CACHE = 'sigaci.settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /** @return array<string, string> */
    public static function values(): array
    {
        try {
            return Cache::rememberForever(self::CACHE, fn () => Schema::hasTable('settings')
                ? static::query()->whereNotNull('value')->pluck('value', 'key')->all()
                : []);
        } catch (Throwable) {
            return []; // base pas encore prête (installation, migrations) : valeurs du fichier .env
        }
    }

    /** Enregistre les réglages ; une valeur vide revient à celle du fichier .env. */
    public static function store(array $values): void
    {
        foreach ($values as $key => $value) {
            $value = is_string($value) ? trim($value) : $value;
            static::updateOrCreate(['key' => $key], ['value' => $value === '' ? null : $value]);
        }
        Cache::forget(self::CACHE);
        static::apply();
    }

    /** Applique les réglages enregistrés à la configuration en cours. */
    public static function apply(): void
    {
        // Valeurs du fichier .env, gardées à la première application : un nouvel enregistrement repart d'elles.
        $defaults = config('sigaci.par_defaut') ?? collect(self::CONFIG)->map(fn ($c) => config($c))->all()
            + ['information' => (string) config('sigaci.information')];
        config(['sigaci.par_defaut' => $defaults]);
        $values = self::values();
        foreach (self::CONFIG as $key => $config) {
            config([$config => $values[$key] ?? $defaults[$key]]);
        }
        config(['sigaci.information' => str_replace(':sigle', config('sigaci.sigle'), $defaults['information'])]);
    }
}
