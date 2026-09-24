#!/bin/bash
# .claude/hooks/log-changes.sh
# Journalise automatiquement chaque fichier créé/modifié par Claude Code.
# Tourne après CHAQUE Write/Edit/MultiEdit — garanti, indépendant de ce que
# l'agent "décide" de faire (contrairement à la Règle n°8 de CLAUDE.md, qui
# capture le "pourquoi" mais peut en théorie être oubliée par l'agent).

INPUT=$(cat)
FILE=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')
TOOL=$(echo "$INPUT" | jq -r '.tool_name // empty')

mkdir -p .claude/logs

if [ -n "$FILE" ]; then
    CHEMIN_RELATIF=$(realpath --relative-to="$(pwd)" "$FILE" 2>/dev/null || echo "$FILE")
    echo "$(date '+%Y-%m-%d %H:%M:%S') | $TOOL | $CHEMIN_RELATIF" >> .claude/logs/changes.log
fi

exit 0
