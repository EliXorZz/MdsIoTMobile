# 0002 — Dernière mesure = plus grand `observed_at`, pas la dernière ligne insérée

## Contexte

Le simulateur peut émettre une mesure en retard (incident `delay` : nouvelle mesure horodatée 60 secondes dans le passé). Si le « dernier état » d'un objet était déterminé par l'ordre d'insertion en base, un message en retard pourrait remplacer une mesure plus récente déjà connue et afficher une valeur périmée comme si elle était la plus fraîche.

## Décision

La relation `Device::latestTelemetry()` ([`Device.php`](../../backend/app/Models/Device.php)) utilise `TelemetryOneMinute` (vue continue TimescaleDB `telemetry_1m`, agrégats à la minute) avec `ofMany(['bucket' => 'MAX'])` : la « dernière mesure » est le bucket dont le `time_bucket('1 minute', observed_at)` est le plus grand, indépendamment de l'ordre d'arrivée ou d'insertion.

## Alternatives envisagées

- **Dernière ligne insérée sur la table raw (ORDER BY id / created_at DESC LIMIT 1)** : rejeté, car un message en retard ou reçu après une coupure réseau serait considéré à tort comme la mesure la plus récente.
- **`latestOfMany('observed_at')` sur la table brute `telemetry`** : envisagé initialement, mais remplacé par l'agrégat `telemetry_1m` pour bénéficier des valeurs lissées (médiane, p5/p95) cohérentes avec les graphiques, tout en gardant la même garantie d'ordre chronologique via `MAX(bucket)`.
- **Rejeter en base tout message dont `observed_at` est antérieur au maximum connu** : rejeté — on préfère conserver la mesure en retard dans l'historique (utile pour les graphiques et l'audit) tout en l'excluant du calcul du « dernier état ».

## Conséquences

- Un message en retard tombe dans un bucket passé et n'affecte jamais le bucket courant (`MAX(bucket)`).
- Les valeurs affichées sur la carte capteur (température, CO₂) sont les médianes du dernier bucket d'une minute, pas un relevé brut isolé — cohérent avec ce qu'affichent les graphiques.
- Ce choix est ce qui permet, côté mobile, un rafraîchissement automatique après une coupure réseau sans risquer d'afficher une valeur plus ancienne que celle déjà vue par l'utilisateur (voir [J2.md](../J2.md)).
