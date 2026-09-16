# 0001 — Déduplication de la télémétrie par `message_id`

## Contexte

Le contrat MQTT du kit indique que la QoS 1 peut livrer des doublons, et que l'incident `duplicate` republie la dernière mesure avec **la même identité et la même date** (voir [contrat-mqtt.md](../contrat-mqtt.md#doublons-et-limites-de-persistance)). Il fallait décider où et comment garantir qu'un message rejoué ne produise pas une deuxième ligne d'historique.

## Décision

Le `message_id` du contrat MQTT est utilisé comme **clé primaire** de la table `telemetry`, et l'insertion se fait avec `insertOrIgnore` ([`StoreTelemetry`](../../backend/app/Listeners/StoreTelemetry.php)). Un message rejoué avec le même `message_id` est silencieusement ignoré par la base de données elle-même, sans logique applicative supplémentaire à maintenir.

## Alternatives envisagées

- **Dédoublonnage applicatif (vérifier avant insertion)** : rejeté, car source d'une condition de concurrence (deux messages quasi simultanés) et redondant avec une contrainte d'unicité native.
- **Table d'historique des `message_id` traités séparée** : rejeté, complexité inutile alors que `message_id` est déjà l'identité naturelle de la mesure.

## Conséquences

- Rejouer un message strictement identique (même `message_id`) est sans effet, y compris après un redémarrage du backend.
- Un message avec un `message_id` différent mais des valeurs différentes (ex. incident `delay`) est traité comme une nouvelle mesure distincte — voir la décision [0002](0002-ordre-chronologique-mesures.md) pour la façon dont l'ordre chronologique est malgré tout garanti.
