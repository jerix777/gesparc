# API GesParc v1

API JSON utilisée par l'application TPI (et par tout autre outil autorisé). Adresse de base : `https://<serveur>/api/v1`.

- **Authentification** : `POST /jetons` renvoie un jeton valable 30 jours, à envoyer dans l'en-tête `Authorization: Bearer <jeton>`.
- **Droits** : les mêmes que dans l'interface web, avec le même cloisonnement par institution
  (un équipement d'une autre institution renvoie 404).
- **Erreurs** : 401 jeton absent ou expiré · 403 action non autorisée · 404 introuvable ·
  422 données invalides (`{"message": "...", "errors": {"champ": ["..."]}}`) · 429 trop de requêtes.
- **Limites** : 10 connexions par minute et par adresse IP, 120 requêtes par minute ensuite.
- Verrouillage du compte 15 minutes après 5 mots de passe erronés (comme sur le web).

| Méthode | Chemin | Qui | Rôle |
| --- | --- | --- | --- |
| POST | `/jetons` | tous | Obtenir un jeton. Corps : `email`, `password`, `appareil` (facultatif) |
| DELETE | `/jetons/courant` | connecté | Révoquer le jeton utilisé |
| GET | `/moi` | connecté | Profil, institution et droits (`voir_inventaire`, `deposer_demande`, `traiter_demandes`…) |
| GET | `/mes-equipements` | connecté | Matériel affecté à l'utilisateur |
| GET | `/equipements?q=&statut=&par_page=` | voir l'inventaire | Inventaire paginé (`donnees`, `pagination`) |
| GET | `/equipements/{id}` | voir l'inventaire ou affectataire | Un équipement |
| POST | `/equipements/{id}/pannes` | informaticien ou affectataire | Signaler une panne. Corps : `description` |
| GET | `/demandes?statut=&miennes=1&limite=` | connecté | Demandes (les siennes, ou toutes pour le Service informatique) |
| POST | `/demandes` | agent rattaché à une institution | Déposer une demande. Corps : `materiel`, `quantite`, `caracteristiques`, `observations` |
| POST | `/demandes/{id}/decision` | informaticien, gestionnaire de stock (cartouches) | Corps : `decision` (`accorder`/`refuser`), `motif_refus` (si refus), `consumable_id` (facultatif : sort la quantité du stock) |
| GET | `/consommables` | voir l'inventaire | Stock des consommables, avec `en_alerte` |

Valeurs de `materiel` : Clavier, Souris, Écran, Ordinateur portable, Ordinateur de bureau, Unité centrale, Onduleur,
Disque dur externe, Clé USB, Câble réseau RJ45, Câble HDMI, Câble VGA, Chargeur d’ordinateur, Batterie d’ordinateur,
Adaptateur, Multiprise, Cartouche d’encre, Autres accessoires informatiques.

## Exemple

```bash
JETON=$(curl -s -X POST https://gesparc.exemple.ci/api/v1/jetons -H 'Accept: application/json' \
  -d email=agent@exemple.ci -d password='...' | jq -r .jeton)

curl -s -X POST https://gesparc.exemple.ci/api/v1/demandes -H "Authorization: Bearer $JETON" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"materiel":"Souris","quantite":1}'
```

```json
{"donnees":{"id":12,"numero":"DEM-2026-012","categorie":"materiel","materiel":"Souris","quantite":1,
  "statut":"en_attente","statut_libelle":"En attente de traitement","demandeur":{"id":7,"nom":"Traoré Mariam"}, "...": "..."}}
```
