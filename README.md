# Gbaka API

Serveur des comptes en ligne de **Gbaka Abidjan** : pseudo et mot de passe, amis, invitations à jouer avec notification
(Firebase Cloud Messaging). Laravel 12, MySQL, jetons Sanctum. Adresse : `https://gbakaapi.vitpronet.ci/api`.

Le jeu (dépôt GbakaAbidjan, `Assets/Gbaka/Scripts/Compte.cs`) crée la partie en ligne avec Unity Relay, puis envoie le code
à un ami par `POST /api/invitations`. L'ami reçoit une notification ; en la touchant, le jeu s'ouvre sur « Accepter / Refuser ».

## Mise en ligne

1. Racine web du domaine sur le dossier `public/` (PHP 8.2 ou plus, extensions openssl, pdo_mysql, mbstring).
2. `composer install --no-dev --optimize-autoloader`
3. `cp .env.example .env`, remplir la base MySQL, puis `php artisan key:generate`
4. `php artisan migrate --force`
5. `php artisan config:cache && php artisan route:cache`
6. Firebase : déposer la clé du compte de service **hors du dossier public** et mettre son chemin dans `FCM_COMPTE_SERVICE`.
   Sans elle, tout marche, mais les invitations ne s'affichent que jeu ouvert (le jeu interroge le serveur toutes les 20 s).

## Routes (préfixe `/api`, JSON, `Authorization: Bearer <jeton>` sauf inscription et connexion)

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/inscription` | `pseudo`, `mot_de_passe` → `jeton` |
| POST | `/connexion` | `pseudo`, `mot_de_passe` → `jeton` |
| POST | `/deconnexion` | oublie le jeton et le téléphone |
| GET | `/moi` | joueur, invitations reçues en attente, nombre de demandes d'amis |
| PUT | `/appareil` | `fcm_token` du téléphone |
| DELETE | `/compte` | `mot_de_passe` : supprime le compte, ses amis et ses invitations |
| GET | `/amis` | amis, demandes reçues et envoyées |
| POST | `/amis` | `pseudo` : demande d'ami (acceptée d'office si l'autre avait demandé) |
| POST | `/amis/{id}/accepter` | accepte une demande reçue |
| DELETE | `/amis/{pseudo}` | refuse, annule ou retire |
| POST | `/invitations` | `pseudo`, `mode` (coop, course), `code`, `libelle` : invite un ami (notification) |
| GET | `/invitations` | invitations reçues valables (10 minutes) |
| GET | `/invitations/envoyees` | suivi de l'hôte : acceptée, refusée |
| POST | `/invitations/{id}/reponse` | `reponse` : accepter ou refuser (l'hôte est prévenu) |
| POST | `/invitations/annuler` | l'hôte ferme sa partie |

## Tests

`php artisan test` : parcours complet (comptes, amitié, invitation refusée puis acceptée, expiration).
