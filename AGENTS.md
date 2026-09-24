# AGENTS.md — GEC

**Note technique** : Claude Code ne lit PAS ce fichier automatiquement — il lit
uniquement `CLAUDE.md`. Ce fichier n'est utile que si vous utilisez aussi un autre
outil d'IA (Cursor, Windsurf, etc.) qui, lui, lit `AGENTS.md`. Pour l'instant vous
travaillez uniquement avec Claude Code, donc ce fichier est optionnel — gardez-le
seulement si vous prévoyez d'utiliser un autre agent plus tard.

Ce fichier s'applique à tout agent de code travaillant sur ce dépôt. Il pointe vers
les mêmes règles que `CLAUDE.md` — les deux doivent rester synchronisés si l'un est
modifié.

## Lire avant de coder, dans cet ordre

1. `PRD.md` — quel est le périmètre exact de la phase 1 (ne pas coder hors scope)
2. `ARCHITECTURE-ESSENTIALS.md` — les règles non négociables (résumé)
3. `ARCHITECTURE.md` — si besoin du détail (structure de dossiers, modèle de données)
4. `DECISIONS.md` — décisions déjà prises, ne pas les reproposer/changer sans le noter

## Règles non négociables (résumé — détail dans CLAUDE.md)

- Tâches lourdes → Job en queue, jamais synchrone
- Composants Livewire légers (primitifs uniquement en propriétés publiques)
- Stockage fichiers en S3-compatible uniquement
- Historique append-only
- Droits d'accès via Policy, jamais un `if` dans la vue
- Toute nouvelle décision d'architecture → ajouter une ligne dans `DECISIONS.md`

## Comportement attendu de l'agent

- Ne pas inventer de nouvelle structure de dossiers si `scaffold.sh` en propose déjà une.
- Ne pas relancer un choix technique déjà tranché (voir `DECISIONS.md`) sans le signaler
  explicitement à l'utilisateur d'abord.
- En cas d'ambiguïté sur le périmètre (module hors scope phase 1 ?), demander plutôt
  que de deviner.
- Poser une question courte plutôt que de faire une supposition risquée sur un point
  de sécurité (droits d'accès, SLA, historique).
