<?php

namespace AiCompanySim;

// Mémoire partagée de l'entreprise simulée — chaque invocation d'agent lit
// l'état existant AVANT d'agir et écrit immédiatement après chaque action
// (jamais en mémoire tampon), pour qu'un agent interrompu laisse quand même
// une trace exploitable. Rien ici n'est propre à GEC — c'est le socle
// générique que tous les rôles utilisent.
class CompanyMemory
{
    public readonly string $runDir;

    public function __construct(string $runDir)
    {
        $this->runDir = rtrim($runDir, '/\\');

        if (! is_dir($this->runDir)) {
            mkdir($this->runDir, 0777, true);
            mkdir($this->runDir.'/bugs', 0777, true);
        }
    }

    public static function creerNouveauRun(int $seed): self
    {
        $horodatage = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z');
        $runDir = AICO_ROOT."/runs/{$horodatage}-seed-{$seed}";
        $memoire = new self($runDir);

        file_put_contents($memoire->runDir.'/events.jsonl', '');
        file_put_contents($memoire->runDir.'/manifest.json', json_encode([], JSON_PRETTY_PRINT));
        file_put_contents($memoire->runDir.'/run.json', json_encode([
            'run_id' => basename($memoire->runDir),
            'seed' => $seed,
            'started_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            'phases_completed' => [],
            'variants_chosen' => [],
        ], JSON_PRETTY_PRINT));

        return $memoire;
    }

    public static function ouvrirRunExistant(string $runId): self
    {
        return new self(AICO_ROOT."/runs/{$runId}");
    }

    // ===== events.jsonl =====

    public function enregistrerEvenement(array $evenement): void
    {
        $evenement['ts'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM);

        $ligne = json_encode($evenement, JSON_UNESCAPED_UNICODE)."\n";

        $fp = fopen($this->runDir.'/events.jsonl', 'a');
        flock($fp, LOCK_EX);
        fwrite($fp, $ligne);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public function lireEvenements(?int $dernieresLignes = null): array
    {
        $lignes = array_filter(explode("\n", file_get_contents($this->runDir.'/events.jsonl')));

        if ($dernieresLignes !== null) {
            $lignes = array_slice($lignes, -$dernieresLignes);
        }

        return array_map(fn ($l) => json_decode($l, true), $lignes);
    }

    // ===== manifest.json =====

    // $id doit être l'id réel DB (jamais null) — appelé juste après la
    // création confirmée, pas avant, pour que cleanup.php n'ait jamais à
    // deviner un id qui n'a pas abouti.
    public function enregistrerEntite(string $type, int|string $id, array $meta = []): void
    {
        $fp = fopen($this->runDir.'/manifest.json', 'c+');
        flock($fp, LOCK_EX);

        $contenu = stream_get_contents($fp);
        $manifest = $contenu !== '' ? json_decode($contenu, true) : [];
        $manifest[$type] ??= [];
        $manifest[$type][] = array_merge(['id' => $id], $meta);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public function lireManifest(): array
    {
        return json_decode(file_get_contents($this->runDir.'/manifest.json'), true) ?: [];
    }

    // ===== bugs/ =====

    public function prochainBugId(): string
    {
        $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $existants = glob($this->runDir."/bugs/BUG-{$date}-*.json");
        $sequence = count($existants) + 1;

        return sprintf('BUG-%s-%04d', $date, $sequence);
    }

    public function signalerBug(array $bug): string
    {
        $bug['bug_id'] ??= $this->prochainBugId();
        $bug['status'] ??= 'reported';
        $bug['date_time'] ??= (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM);

        file_put_contents(
            $this->runDir."/bugs/{$bug['bug_id']}.json",
            json_encode($bug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $this->enregistrerEvenement([
            'actor_role' => $bug['detected_by']['role'] ?? 'inconnu',
            'action' => 'bug_reported',
            'bug_id' => $bug['bug_id'],
            'severity' => $bug['severity'] ?? null,
            'title' => $bug['title'] ?? null,
        ]);

        return $bug['bug_id'];
    }

    public function listerBugs(): array
    {
        $fichiers = glob($this->runDir.'/bugs/BUG-*.json');

        return array_map(fn ($f) => json_decode(file_get_contents($f), true), $fichiers);
    }

    public function mettreAJourBug(string $bugId, array $champs): void
    {
        $chemin = $this->runDir."/bugs/{$bugId}.json";
        $bug = json_decode(file_get_contents($chemin), true);
        $bug = array_merge($bug, $champs);
        file_put_contents($chemin, json_encode($bug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // ===== run.json =====

    public function marquerPhaseTerminee(string $phase): void
    {
        $chemin = $this->runDir.'/run.json';
        $run = json_decode(file_get_contents($chemin), true);
        $run['phases_completed'][] = $phase;
        file_put_contents($chemin, json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function enregistrerVariantChoisi(string $pool, string $valeur): void
    {
        $chemin = $this->runDir.'/run.json';
        $run = json_decode(file_get_contents($chemin), true);
        $run['variants_chosen'][$pool] = $valeur;
        file_put_contents($chemin, json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function lireRunInfo(): array
    {
        return json_decode(file_get_contents($this->runDir.'/run.json'), true);
    }
}
