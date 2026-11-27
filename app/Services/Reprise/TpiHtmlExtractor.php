<?php

namespace App\Services\Reprise;

use App\Services\Personnel\StaffImport;
use Carbon\Carbon;

/**
 * Extrait les données intégrées dans les fichiers HTML autonomes de l'application d'origine
 * « Gestion du personnel – TPI de Bouaké » et les met en correspondance avec le schéma SIGACI.
 *
 * Données lues dans le HTML :
 *  - initialPeople : liste du personnel ;
 *  - TPI_SERVICE_UPDATE_NOV2026(_B) : service et n° de porte de chaque agent (mise à jour de novembre 2026) ;
 *  - SEED du « Pack informatique » (bloc base64 #pcSrc) : bénéficiaires, unités centrales et écrans,
 *    imprimantes, scanners.
 *
 * Le résultat (format « sigaci-reprise ») est un tableau prêt pour la commande sigaci:reprise-origine,
 * qui peut aussi être enregistré en JSON pour relecture.
 */
class TpiHtmlExtractor
{
    public const FORMAT = 'sigaci-reprise';

    /** Corrections de libellés de service manifestement fautifs dans l'inventaire. */
    private const SERVICE_FIXES = ['INDORMATIQUE' => 'INFORMATIQUE'];

    private const MARQUES = ['HP', 'CANON', 'EPSON', 'DELL', 'LENOVO', 'SAMSUNG', 'BROTHER', 'ACER', 'ASUS', 'LG', 'PHILIPS', 'XEROX', 'KYOCERA'];

    /** @var list<array{source: string, ref: string, motif: string}> */
    private array $nonRepris = [];

    /** @var list<string> */
    private array $alertes = [];

    /**
     * @param  list<string>  $paths
     */
    public function extract(array $paths): array
    {
        $raw = ['initialPeople' => null, 'TPI_SERVICE_UPDATE_NOV2026' => null, 'TPI_SERVICE_UPDATE_NOV2026_B' => null, 'SEED' => null];
        $sources = [];
        foreach ($paths as $path) {
            $html = (string) file_get_contents($path);
            $found = [];
            foreach (array_keys($raw) as $name) {
                $value = $name === 'SEED' ? $this->packSeed($html) : self::jsLiteral($html, $name);
                // Plusieurs versions du fichier : on garde la plus complète de chaque liste.
                if (is_array($value) && strlen(json_encode($value)) > strlen(json_encode($raw[$name]))) {
                    $raw[$name] = $value;
                }
                if (is_array($value)) {
                    $found[] = $name;
                }
            }
            $sources[] = ['fichier' => basename($path), 'sha1' => sha1($html), 'listes' => $found];
        }

        return $this->map($raw, $sources);
    }

    /** Valeur d'une constante JavaScript (tableau ou objet au format JSON) déclarée dans le source. */
    public static function jsLiteral(string $src, string $name): ?array
    {
        if (! preg_match('/(?:const|let|var)\s+'.preg_quote($name, '/').'\s*=\s*(?=[\[{])/', $src, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $start = $m[0][1] + strlen($m[0][0]);
        $open = $src[$start];
        $close = $open === '[' ? ']' : '}';
        $depth = 0;
        $quote = null;
        for ($i = $start, $n = strlen($src); $i < $n; $i++) {
            $c = $src[$i];
            if ($quote !== null) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === $quote) {
                    $quote = null;
                }

                continue;
            }
            if ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === $open) {
                $depth++;
            } elseif ($c === $close && --$depth === 0) {
                $value = json_decode(substr($src, $start, $i - $start + 1), true);

                return is_array($value) ? $value : null;
            }
        }

        return null;
    }

    private function packSeed(string $html): ?array
    {
        if (! preg_match('/id="pcSrc"[^>]*>([^<]*)</', $html, $m)) {
            return null;
        }
        $pack = base64_decode(trim($m[1]), true);

        return $pack === false ? null : self::jsLiteral($pack, 'SEED');
    }

    private function map(array $raw, array $sources): array
    {
        $this->nonRepris = [];
        $this->alertes = [];

        $updates = [];
        foreach (array_merge($raw['TPI_SERVICE_UPDATE_NOV2026'] ?? [], $raw['TPI_SERVICE_UPDATE_NOV2026_B'] ?? []) as $u) {
            $updates[(string) ($u['id'] ?? '')] = $u;
        }

        $personnel = [];
        foreach ($raw['initialPeople'] ?? [] as $p) {
            $ref = (string) ($p['id'] ?? '');
            $u = $updates[$ref] ?? [];
            $email = $this->clean($p['email'] ?? null);
            if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->nonRepris[] = ['source' => 'personnel', 'ref' => $ref, 'motif' => "adresse e-mail invalide « {$email} » non reprise"];
                $email = null;
            }
            $categorie = StaffImport::categoryCode($p['categorie'] ?? null);
            if (! $categorie) {
                $this->alertes[] = "personnel {$ref} : catégorie « ".($p['categorie'] ?? '').' » inconnue';
            }
            $personnel[] = [
                'source_ref' => $ref,
                'matricule' => $this->clean($p['matricule'] ?? null),
                'nom' => $this->clean($p['nom'] ?? null),
                'prenoms' => $this->clean($p['prenoms'] ?? null),
                'fonction' => $this->clean($p['fonction'] ?? null),
                'categorie' => $categorie,
                'service' => $this->service($u['service'] ?? null),
                'bureau' => $this->clean($u['porte'] ?? null),
                'telephone' => $this->clean($p['telephone'] ?? null),
                'email' => $email,
                'statut' => mb_strtolower((string) ($p['statut'] ?? 'Actif')) === 'actif' ? 'actif' : 'sorti',
            ];
        }
        $staffRefs = array_flip(array_column($personnel, 'source_ref'));

        $seed = $raw['SEED'] ?? [];
        $benList = array_values($seed['ben'] ?? []); // l'inventaire des postes suit l'ordre des bénéficiaires
        $ben = [];
        foreach ($benList as $b) {
            if (isset($ben[$b['id']])) {
                $this->alertes[] = "identifiant de bénéficiaire {$b['id']} en double dans le pack ({$ben[$b['id']]['nom']} / {$b['nom']}) : une imprimante qui y renvoie est rattachée au premier";
            }
            $ben[$b['id']] ??= $b;
        }
        $equipements = [];
        $consommables = [];

        foreach ($seed['pc'] ?? [] as $i => $pc) {
            $b = $benList[$i] ?? [];
            if (($b['nom'] ?? null) !== ($pc['utilisateur'] ?? null)) {
                $this->alertes[] = 'poste ligne '.($i + 1).' : bénéficiaire du pack différent de l\'utilisateur de l\'inventaire';
            }
            // Les colonnes « imprimante » de l'inventaire des postes doublonnent la liste des imprimantes, reprise à part.
            $this->pc($pc, (string) ($i + 1), $staffRefs, $equipements);
        }
        foreach ($seed['imp'] ?? [] as $imp) {
            $this->imprimante($imp, $ben, $staffRefs, $equipements, $consommables);
        }
        foreach ($seed['scanners'] ?? [] as $i => $sc) {
            $this->scanner($sc, $i + 1, $equipements, $consommables);
        }
        $this->flagDuplicateSerials($equipements);

        $services = array_values(array_unique(array_filter(array_merge(
            array_column($personnel, 'service'), array_column($equipements, 'service')
        ))));
        sort($services);
        ksort($consommables);

        return [
            'format' => self::FORMAT,
            'version' => 1,
            'extrait_le' => now()->toIso8601String(),
            'sources' => $sources,
            'institution' => ['code' => 'TPI-BKE', 'nom' => 'Tribunal de première instance de Bouaké', 'categorie' => 'Juridiction', 'ville' => 'Bouaké'],
            'services' => $services,
            'personnel' => $personnel,
            'equipements' => $equipements,
            'consommables' => array_values(array_map(fn ($c) => array_diff_key($c, ['imprimantes' => 1]), $consommables)),
            'non_repris' => $this->nonRepris,
            'alertes' => array_values(array_unique($this->alertes)),
        ];
    }

    /**
     * Une ligne de l'inventaire des postes donne une unité centrale et, le cas échéant, un écran.
     * Dans l'inventaire d'origine, les colonnes situées après « Windows activé » sont décalées d'un cran ;
     * elles sont lues ici selon leur contenu réel (voir le rapport de reprise).
     */
    private function pc(array $pc, string $ligne, array $staffRefs, array &$out): void
    {
        $modele = $this->clean($pc['modeleUniteCentrale'] ?? null);
        $serie = $this->serial($pc['numeroSerieUC'] ?? null);
        $nomPc = $this->clean($pc['nomPC'] ?? null);
        $user = $this->clean($pc['utilisateur'] ?? null);
        if (! $modele && ! $serie && ! $nomPc) {
            $this->nonRepris[] = ['source' => 'inventaire des postes', 'ref' => "ligne {$ligne} (n° {$pc['numero']}, {$user})", 'motif' => 'ligne vide : ni modèle, ni n° de série, ni nom de poste'];

            return;
        }
        $staff = $this->staffRef($pc['personnelId'] ?? null, $staffRefs);
        $service = $this->service($pc['service'] ?? null);
        $bureau = $this->clean($pc['porte'] ?? null);
        $origine = $this->origine($user, $pc['fonction'] ?? null, $staff);

        $ram = trim(implode(' ', array_filter([
            $this->clean($pc['ramQuantite'] ?? null) ? $pc['ramQuantite'].' ×' : null,
            $this->clean($pc['ramTailleGo'] ?? null) ? $pc['ramTailleGo'].' Go' : null,
            $this->clean($pc['ramType'] ?? null),
            $this->clean($pc['ramFrequenceMHz'] ?? null) ? preg_replace('/\s*(MHz|MT)?$/i', '', $pc['ramFrequenceMHz']).' MHz' : null,
        ])));
        $etatUc = $this->clean($pc['etatUC'] ?? null);
        $caracteristiques = $this->lines([
            'Nom du poste' => $nomPc,
            'Processeur' => trim(($this->clean($pc['processeurType'] ?? null) ?? '').' '.($this->clean($pc['processeurFrequenceGHz'] ?? null) ? str_replace(',', '.', $pc['processeurFrequenceGHz']).' GHz' : '')),
            'Mémoire' => $ram,
            'Disque' => trim(($this->clean($pc['disqueType'] ?? null) ?? '').' '.($this->clean($pc['disqueTailleGo'] ?? null) ? $pc['disqueTailleGo'].' Go' : '')),
            'Système' => $this->clean($pc['systemeExploitation'] ?? null),
            'Windows activé' => $this->clean($pc['systemeProduit'] ?? null),
            'Office' => $this->clean($pc['systemeActivation'] ?? null),
            'Office activé' => $this->clean($pc['officeProduit'] ?? null),
            'Adresse IP' => $this->clean($pc['dateMiseEnService'] ?? null),
            'Adresse MAC' => $this->clean($pc['adresseIP'] ?? null),
        ]);
        $miseEnService = $this->clean($pc['officeActivation'] ?? null);
        $out[] = [
            'source_ref' => "pc:{$ligne}",
            'type' => 'Ordinateur de bureau',
            'designation' => $modele ? "Unité centrale {$modele}" : 'Unité centrale',
            'marque' => $this->marque($modele),
            'modele' => $modele,
            'numero_serie' => $serie,
            'caracteristiques' => $caracteristiques,
            'date_acquisition' => $this->date($miseEnService),
            'statut' => 'affecte',
            'etat' => $this->etat($etatUc, $pc['adresseMAC'] ?? null),
            'service' => $service,
            'bureau' => $bureau,
            'staff_ref' => $staff,
            'observations' => $this->lines([
                'Utilisateur (inventaire d\'origine)' => $origine,
                'État constaté' => $etatUc && ! preg_match('/^bon( etat)?$/i', $etatUc) ? $etatUc : null,
                'Ancienneté' => $this->clean($pc['adresseMAC'] ?? null),
                'Mise en service' => $miseEnService && ! $this->date($miseEnService) ? $miseEnService : null,
                'Observations' => $this->clean($pc['imprimanteObservations'] ?? null),
                'Contrôle de l\'inventaire' => $this->clean($pc['controleAffectation'] ?? null),
            ]),
        ];

        $ecranModele = $this->clean($pc['observationsUC'] ?? null);
        $ecranSerie = $this->serial($pc['ecranTaille'] ?? null);
        if ($ecranModele || $ecranSerie) {
            $out[] = [
                'source_ref' => "ecran:{$ligne}",
                'type' => 'Écran',
                'designation' => $ecranModele ? "Écran {$ecranModele}" : 'Écran',
                'marque' => $this->marque($ecranModele),
                'modele' => $ecranModele,
                'numero_serie' => $ecranSerie,
                'caracteristiques' => $this->lines(['Taille' => $this->clean($pc['ecranMarque'] ?? null)]),
                'date_acquisition' => null,
                'statut' => 'affecte',
                'etat' => 'bon',
                'service' => $service,
                'bureau' => $bureau,
                'staff_ref' => $staff,
                'observations' => $this->lines(['Utilisateur (inventaire d\'origine)' => $origine, 'Poste associé' => $nomPc ?? $serie]),
            ];
        }
    }

    private function imprimante(array $imp, array $ben, array $staffRefs, array &$out, array &$consommables): void
    {
        $b = $ben[$imp['ags'][0] ?? ''] ?? [];
        $modele = $this->clean($imp['modele'] ?? null);
        $enReseau = $modele && preg_match('/^en r[ée]seau$/iu', $modele);
        if ($enReseau) {
            $this->alertes[] = "imprimante {$imp['id']} : modèle non renseigné (« {$modele} »), reprise comme « Imprimante en réseau »";
            $modele = null;
        }
        $observation = $this->clean($imp['observations'] ?? null);
        $parti = $observation && preg_match('/quitt/i', $observation);
        $staff = $this->staffRef($b['personnelId'] ?? null, $staffRefs);
        $ref = $this->clean($imp['ref'] ?? null);
        $out[] = [
            'source_ref' => 'imp:'.$imp['id'],
            'type' => $modele && preg_match('/scan/i', $modele) ? 'Scanner' : 'Imprimante',
            'designation' => $modele ? "Imprimante {$modele}" : ($enReseau ? 'Imprimante en réseau' : 'Imprimante'),
            'marque' => $this->marque($this->clean($imp['marque'] ?? null) ?? $modele),
            'modele' => $modele,
            'numero_serie' => $this->serial($imp['serie'] ?? null),
            'caracteristiques' => $this->lines(['Cartouche' => $ref, 'Adresse IP' => $this->clean($imp['adresseIP'] ?? null)]),
            'date_acquisition' => $this->date($imp['dateDeploiement'] ?? null),
            'statut' => $parti ? 'en_stock' : 'affecte',
            'etat' => $observation && preg_match('/bourrage|panne|d[ée]fect/i', $observation) ? 'moyen' : 'bon',
            'service' => $parti ? null : $this->service($b['svc'] ?? null),
            'bureau' => $parti ? null : $this->clean($b['porte'] ?? null),
            'staff_ref' => $parti ? null : $staff,
            'observations' => $this->lines([
                'Utilisateur (inventaire d\'origine)' => $this->origine($this->clean($b['nom'] ?? null), $b['fn'] ?? null, $staff),
                'Bureau d\'origine' => $parti ? trim(($b['svc'] ?? '').' porte '.($b['porte'] ?? '')) : null,
                'Ancienneté' => $this->clean($imp['etat'] ?? null),
                'État constaté' => $observation && ! preg_match('/^bon( etat)?$/i', $observation) ? $observation : null,
            ]),
        ];
        $this->cartouche($ref, $modele, $consommables);
    }

    private function scanner(array $sc, int $n, array &$out, array &$consommables): void
    {
        $modele = $this->clean($sc['modele'] ?? null);
        $serie = $this->serial($sc['numeroSerie'] ?? null);
        if (! $modele && ! $serie) {
            $this->nonRepris[] = ['source' => 'scanners', 'ref' => "ligne {$n} (".($sc['utilisateur'] ?? '').')', 'motif' => 'ni modèle ni n° de série'];

            return;
        }
        foreach ($out as $e) {
            if ($serie && $e['numero_serie'] && strcasecmp($e['numero_serie'], $serie) === 0) {
                $this->nonRepris[] = ['source' => 'scanners', 'ref' => "ligne {$n} ({$modele}, n° {$serie})", 'motif' => "même appareil que {$e['source_ref']}, déjà repris"];

                return;
            }
        }
        $out[] = [
            'source_ref' => "scan:{$n}",
            'type' => 'Scanner',
            'designation' => $modele ? (preg_match('/scan/i', $modele) ? $modele : "Scanner {$modele}") : 'Scanner',
            'marque' => $this->marque($modele),
            'modele' => $modele,
            'numero_serie' => $serie,
            'caracteristiques' => $this->lines(['Cartouche' => $this->clean($sc['cartouche'] ?? null), 'Adresse IP' => $this->clean($sc['adresseIP'] ?? null)]),
            'date_acquisition' => $this->date($sc['dateDeploiement'] ?? null),
            'statut' => 'affecte',
            'etat' => 'bon',
            'service' => $this->service($sc['service'] ?? null),
            'bureau' => $this->clean($sc['porte'] ?? null),
            'staff_ref' => null,
            'observations' => $this->lines([
                'Utilisateur (inventaire d\'origine)' => $this->origine($this->clean($sc['utilisateur'] ?? null), $sc['fonction'] ?? null, null),
                'État constaté' => $this->clean($sc['etat'] ?? null) ?? $this->clean($sc['observations'] ?? null),
            ]),
        ];
        $this->cartouche($sc['cartouche'] ?? null, $modele, $consommables);
    }

    private function cartouche(?string $ref, ?string $modele, array &$consommables): void
    {
        $ref = $this->clean($ref);
        if (! $ref || preg_match('/^(aucune?|neant)$/i', $ref)) {
            return;
        }
        $ref = mb_strtoupper($ref);
        if (! preg_match('/^\d{2,3}[A-Z]?$/', $ref)) {
            $this->alertes[] = "référence de cartouche « {$ref} » (".($modele ?? 'modèle inconnu').') non reconnue, non reprise dans les consommables';

            return;
        }
        // 0xxA : cartouches Canon (067A…) ; les autres (26A, 59A, 85A…) sont des références HP.
        $reference = str_starts_with($ref, '0') ? "Canon {$ref}" : "HP {$ref}";
        $consommables[$reference] ??= ['reference' => $reference, 'designation' => "Toner {$reference}", 'categorie' => 'toner', 'imprimantes' => []];
        if ($modele) {
            $consommables[$reference]['imprimantes'][mb_strtoupper(preg_replace('/\s+/', ' ', $modele))] = true;
        }
        $consommables[$reference]['imprimantes_compatibles'] = mb_substr(implode(', ', array_keys($consommables[$reference]['imprimantes'])), 0, 255) ?: null;
    }

    private function flagDuplicateSerials(array &$equipements): void
    {
        $seen = [];
        foreach ($equipements as $e) {
            if ($e['numero_serie']) {
                $seen[mb_strtoupper($e['numero_serie'])][] = $e['source_ref'];
            }
        }
        foreach ($seen as $serie => $refs) {
            if (count($refs) > 1) {
                $this->alertes[] = "n° de série {$serie} présent sur plusieurs lignes (".implode(', ', $refs).') : chaque ligne est reprise, à vérifier sur place';
            }
        }
    }

    private function staffRef(?string $id, array $staffRefs): ?string
    {
        return $id && isset($staffRefs[$id]) ? $id : null;
    }

    private function origine(?string $user, ?string $fonction, ?string $staff): ?string
    {
        if (! $user) {
            return null;
        }
        $fonction = $this->clean($fonction);

        return $user.($fonction ? " — {$fonction}" : '').($staff ? " (fiche du personnel {$staff})" : ' (non rapproché d\'une fiche du personnel)');
    }

    private function service(?string $name): ?string
    {
        $name = $this->clean($name);
        if (! $name) {
            return null;
        }
        $name = preg_replace('/\s+/', ' ', $name);

        return self::SERVICE_FIXES[$name] ?? $name;
    }

    private function etat(?string $etat, ?string $anciennete): string
    {
        return match (true) {
            $etat && preg_match('/plante|eteint|éteint|panne|hs/i', $etat) => 'mauvais',
            $etat && preg_match('/lent/i', $etat) => 'moyen',
            ! $etat && $this->clean($anciennete) === 'NOUVEAU' => 'neuf',
            default => 'bon',
        };
    }

    private function marque(?string $text): ?string
    {
        $first = mb_strtoupper(strtok(trim((string) $text), ' ') ?: '');

        return in_array($first, self::MARQUES, true) ? ($first === 'HP' ? 'HP' : ucfirst(mb_strtolower($first))) : null;
    }

    /** Année seule, date « AAAA-MM-JJ … » ou numéro de série de date Excel (ex. 45988). */
    private function date(?string $value): ?string
    {
        $value = $this->clean($value);

        return match (true) {
            ! $value => null,
            (bool) preg_match('/^(19|20)\d{2}$/', $value) => $value.'-01-01',
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $value) => substr($value, 0, 10),
            (bool) preg_match('/^4\d{4}$/', $value) => Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString(),
            default => null,
        };
    }

    private function serial(?string $value): ?string
    {
        $value = $this->clean($value);

        return $value && ! preg_match('/^(aucune?|neant|néant|n\/a)$/iu', $value) ? $value : null;
    }

    /** Valeur nettoyée ; « 0 » et les chaînes vides valent « non renseigné ». */
    private function clean(mixed $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' || $value === '0' ? null : $value;
    }

    private function lines(array $pairs): ?string
    {
        $lines = [];
        foreach ($pairs as $label => $value) {
            if ($value !== null && trim((string) $value) !== '') {
                $lines[] = "{$label} : ".trim((string) $value);
            }
        }

        return $lines ? implode("\n", $lines) : null;
    }
}
