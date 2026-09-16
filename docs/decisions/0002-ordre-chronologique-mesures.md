# 0002 — Dernière mesure = plus grand `observed_at`, pas la dernière ligne insérée

## Contexte

Le simulateur peut émettre une mesure en retard (incident `delay` : nouvelle mesure horodatée 60 secondes dans le passé). Si le « dernier état » d'un objet était déterminé par l'ordre d'insertion en base, un message en retard pourrait remplacer une mesure plus récente déjà connue et afficher une valeur périmée comme si elle était la plus fraîche.

## Décision

La relation `Device::latestTelemetry()` utilise `hasOne(...)->latestOfMany('observed_at')` ([`Device.php`](../../backend/app/Models/Device.php)) : la « dernière mesure » est celle dont le champ métier `observed_at` est le plus grand, indépendamment de l'ordre d'arrivée ou d'insertion.

## Alternatives envisagées

- **Dernière ligne insérée (ORDER BY id / created_at DESC LIMIT 1)** : rejeté, car un message en retard ou reçu après une coupure réseau serait considéré à tort comme la mesure la plus récente.
- **Rejeter en base tout message dont `observed_at` est antérieur au maximum connu** : rejeté pour cette version — on préfère conserver la mesure en retard dans l'historique (utile pour les graphiques et l'audit) tout en l'excluant du calcul du « dernier état ».

## Conséquences

- Un message en retard est conservé dans l'historique mais n'écrase jamais l'affichage du dernier état si une mesure plus récente existe déjà.
- Ce choix est ce qui permet, côté mobile, un rafraîchissement automatique après une coupure réseau sans risquer d'afficher une valeur plus ancienne que celle déjà vue par l'utilisateur (voir [J2.md](../J2.md)).
