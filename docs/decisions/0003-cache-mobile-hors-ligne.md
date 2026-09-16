# 0003 — Cache mobile hors-ligne : persistance du cache RTK Query plutôt qu'un store dédié

## Contexte

L'application mobile devait rester utilisable (affichage des dernières données connues) en cas de coupure réseau, de fermeture/relance de l'app, et reprendre automatiquement à la reconnexion, sans dupliquer l'historique ni écraser une mesure récente par une donnée mise en cache plus ancienne.

## Décision

Le cache RTK Query existant (`api` reducer de [`store/api.ts`](../../mobile/src/store/api.ts)) est persisté tel quel via `redux-persist` + `AsyncStorage`, sans créer de store de cache parallèle ([`store/index.ts`](../../mobile/src/store/index.ts)). Trois compléments :

- `refetchOnReconnect` / `refetchOnFocus` activés sur l'`api`, câblés à `NetInfo` et `AppState` (React Native n'émet pas les événements navigateur `online`/`visibilitychange` utilisés par défaut par RTK Query).
- L'horodatage `fulfilledTimeStamp`, déjà fourni par RTK Query, est utilisé tel quel pour afficher « Dernière mise à jour » — aucun horodatage maison à maintenir.
- Chaque requête **remplace** intégralement les données en cache (pas de fusion/append), ce qui évite tout doublon d'historique côté client, y compris après plusieurs cycles offline/online.

## Alternatives envisagées

- **Store de cache dédié (ex. table SQLite locale avec logique de fusion)** : rejeté, complexité disproportionnée par rapport au besoin (l'API réexpose déjà un état cohérent et dédupliqué) et risque de divergence entre le cache local et la vérité serveur.
- **Persister uniquement `queries` sans `subscriptions`/`mutations` de l'`api` reducer** : envisagé mais non retenu pour l'instant — la gestion des abonnements/polling est reconstruite en mémoire à chaque démarrage par le middleware RTK Query, donc la persister n'a pas d'effet néfaste observé ; à revisiter si la taille du cache devient un problème.

## Conséquences

- Les capteurs restent visibles hors-ligne, y compris après avoir tué puis relancé l'application.
- Un bandeau dédié distingue explicitement « pas de réseau côté téléphone » (cache affiché) d'une véritable erreur serveur (voir [J2.md](../J2.md)).
- La fraîcheur affichée dépend uniquement de `fulfilledTimeStamp` : si l'utilisateur n'a jamais eu de connexion réussie, aucune donnée n'est affichée (pas de valeur par défaut trompeuse).
