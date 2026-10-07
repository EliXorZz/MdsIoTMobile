# 0004 — Cycle de vie des commandes, timeouts et idempotence

## Contexte

Un utilisateur envoie une commande depuis le mobile (ex. activer la ventilation). Le backend publie cette commande sur MQTT. L'objet peut ne pas répondre (hors ligne, délai réseau, crash), répondre en retard ou recevoir la commande deux fois. Il faut définir un comportement déterministe pour chacun de ces cas.

## Décisions

### Cycle de vie et statuts

Le cycle de vie d'une commande est représenté par l'enum `CommandStatus` :

```
PENDING → SENT → ACKNOWLEDGED
                → FAILED
         → FAILED   (publication MQTT échouée)
(any non-terminal) → TIMEOUT  (calculé à la lecture)
```

- **`PENDING`** : commande créée en base, publication MQTT pas encore tentée.
- **`SENT`** : broker MQTT a accepté le message. Le backend ne sait pas si l'objet l'a reçu.
- **`ACKNOWLEDGED`** : objet a exécuté la commande et publié un résultat `executed`.
- **`FAILED`** : publication MQTT échouée **ou** objet a retourné un résultat `rejected`.
- **`TIMEOUT`** : statut calculé à la lecture par `getEffectiveStatusAttribute()` — jamais persisté. Si `status ∉ {ACKNOWLEDGED, FAILED, TIMEOUT}` et `timeout_at < now()`, l'API retourne `TIMEOUT`.

Ce choix (timeout calculé, non persisté) évite un job d'expiration et maintient la cohérence sans écriture supplémentaire. La contrepartie est qu'une commande `SENT` dont le timeout est passé n'émet pas de notification proactive côté mobile : la mise à jour est visible au prochain poll ou refetch.

### Délai de timeout

30 secondes (constante `TIMEOUT_SECONDS` dans `SendCommand`). Ce délai correspond au cas d'usage : un utilisateur attend une confirmation visible avant de considérer la commande comme perdue.

### Comportement pour un ACK tardif

Un ACK tardif est un résultat arrivé alors que `timeout_at < now()` mais que le statut en base est encore `SENT` (le timeout est calculé, pas écrit).

**Décision : l'ACK tardif est accepté et la commande passe à `ACKNOWLEDGED`.**

Justification : l'objet a réellement exécuté la commande. Rejeter l'ACK laisserait une incohérence entre l'état réel de l'objet (ventilation activée) et l'état base (TIMEOUT). Accepter l'ACK reflète fidèlement ce qui s'est passé. L'`acked_at` dans les logs indique que l'exécution a eu lieu après le délai attendu.

Ce comportement est loggué par `command.late_ack` avec `current: TIMEOUT` dans le contexte, ce qui permet d'identifier l'étape.

**Alternative rejetée** : rejeter l'ACK tardif et persister `TIMEOUT`. Cela introduit une incohérence durable entre l'état DB et l'état physique de l'objet, ce qui rend les diagnostics plus difficiles.

### Idempotence des commandes

**Côté objet (simulateur)** : le dictionnaire `results` indexé par `command_id` garantit qu'une commande rejouée retourne le même résultat sans ré-exécuter l'action. Si le même `command_id` arrive avec un payload différent, c'est une erreur de protocole → `rejected`.

**Côté backend** : avant tout `CommandResult::create`, `HandleCommandResult` vérifie `CommandResult::where('command_id')->exists()`. Un doublon est détecté, loggué `command.duplicate` et silencieusement ignoré. Le statut de la commande reste inchangé.

Cette double protection (objet + backend) couvre les cas QoS 1 (livraison dupliquée par le broker) et les cas de rejeu applicatif.

### Topics MQTT

| Direction | Topic | Contenu |
|-----------|-------|---------|
| Backend → Objet | `campus/v1/devices/{device_id}/commands` | Payload de commande avec `command_id`, `action`, `params`, `expires_at` |
| Objet → Backend | `campus/v1/devices/{device_id}/results` | Résultat avec `command_id`, `status`, `ventilation`, `executed_at` |

Le `command_id` est le seul lien de corrélation entre les deux topics.

## Conséquences

- Un `command_id` peut être filtré dans les logs pour reconstruire l'intégralité du parcours : `command.issued` → `command.sent` → `command_result.received` → `command.acknowledged` / `command.failed` / `command.late_ack` / `command.duplicate`.
- Le mobile doit gérer trois états visuels distincts : commande en attente (PENDING/SENT), confirmée (ACKNOWLEDGED), et expirée/échouée (TIMEOUT/FAILED). Il ne doit pas présenter une commande comme réussie avant réception de l'ACK.
- Une commande `SENT` dont le timeout est dépassé n'est pas notifiée proactivement. Si une notification temps réel est nécessaire, il faudra ajouter un job planifié qui écrit `TIMEOUT` en base et publie un événement SSE.
