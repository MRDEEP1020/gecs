# AI Employee Company — système de test réaliste pour GEC

Simulation multi-agents d'une journée de travail réelle sur GEC : des
"employés" (Operations, Administrator, External Correspondent, Product
Manager, Finance, UX Researcher, Security Engineer, QA Engineer, CEO/Test
Director) utilisent l'application exactement comme de vrais utilisateurs,
pour trouver les bugs qu'une suite de tests isolés ne trouve pas —
permissions, logique métier, intégrité des données, sécurité, régressions.

Voir le plan d'architecture complet :
`C:\Users\NSIANV-EDS-ANK\.claude\plans\lovely-splashing-yeti.md`.

## Ce que ce système N'EST PAS

- **Pas un remplacement de la suite PHPUnit** (`tests/Feature`, `tests/Unit`)
  — celle-ci tourne sur une base sqlite jetable (`phpunit.xml`), ce
  système-ci tourne **contre la vraie base de dev `gec`** (mysql), avec
  nettoyage explicite après coup. Les deux coexistent, ne se remplacent pas.
- **Pas un outil de test visuel/navigateur** — aucune automatisation de
  navigateur n'existe dans cet environnement. Aucun test CSS, responsive,
  ni erreur console JS. Dit explicitement dans chaque rapport généré.

## Mécanisme technique clé

`Livewire::test()` fonctionne parfaitement depuis un script PHP autonome
(pas seulement PHPUnit) — voir `bootstrap.php`. Chaque scénario boote
l'application réelle et pilote les VRAIS composants Livewire
(`RegistrationForm`, `ShowCourrier`, etc.) contre la VRAIE base `gec`, avec
authentification/validation/autorisation réelles.

**Piège découvert en construisant ce système** (à ne jamais réintroduire) :
si un composant lève une erreur d'autorisation (403) EN COURS de méthode
(pas au `mount()`), le snapshot retourné par le harnais de test Livewire
devient corrompu — tout appel suivant sur le MÊME résultat (y compris
`->assertForbidden()`) lève alors une `InvalidArgumentException` de bas
niveau ("Invalid Livewire snapshot structure...") sans rapport avec le
vrai verdict. **Ne jamais juger un scénario sur la nature de l'exception
levée dans ce cas — toujours vérifier l'EFFET RÉEL en base** (ex. l'objet
d'un courrier a-t-il vraiment changé ?). Voir `sec-03-idor-editform-courrierid-swap.php`
pour le patron correct.

## Lancer un run complet

```
php tests/ai-company-sim/scenarios/_setup.php              # Phase 0 — note le run_id affiché
php tests/ai-company-sim/scenarios/operations/op-01-*.php <run_id>
... (chaque scénario de chaque rôle, voir le plan pour l'ordre par phase)
php tests/ai-company-sim/scenarios/qa/qa-verify-bugs.php <run_id>
php tests/ai-company-sim/scenarios/ceo/ceo-01-smoke-test-and-spotcheck.php <run_id>
php tests/ai-company-sim/scenarios/ceo/ceo-02-generate-report.php <run_id>   # écrit runs/<run_id>/daily-report.md
```

Chaque scénario écrit dans `runs/<run_id>/events.jsonl` (mémoire partagée),
`manifest.json` (toutes les entités taguées créées), et `bugs/*.json` pour
tout défaut signalé.

## Nettoyer un run (TOUJOURS après, jamais laissé en l'état)

```
php tests/ai-company-sim/cleanup.php <run_id>              # dry-run, affiche ce qui serait supprimé
php tests/ai-company-sim/cleanup.php <run_id> --confirm    # suppression réelle + vérification "zéro résidu"
```

Ne supprime QUE les ids exacts du `manifest.json` de ce run précis — jamais
une correspondance par tag seule, donc jamais une ligne réelle non créée
par ce run.

## Convention de tag

Toute donnée créée est préfixée `AICO-<seed>` (`TaggedFactory::tag()`), avec
des données RÉALISTES (vrais types de courrier assurance, vrais noms
d'organisation) — jamais `test123`.

## Règles de sécurité

- `cleanup.php` ne supprime jamais rien sans `--confirm` explicite.
- Aucune exécution automatique/planifiée — toujours invoqué manuellement.
- Toute action réellement destructive/irréversible HORS du périmètre de ce
  système (ex. modifier une config serveur globale) reste soumise à
  l'approbation explicite de l'utilisateur, comme pour tout le reste de ce
  projet.
