<?php

namespace AiCompanySim;

use Throwable;

// Enveloppe une action réelle (appel Livewire::test(), requête curl, etc.)
// avec une capture d'erreur et un événement systématique — "Gestion des
// échecs" du plan : un échec est toujours capturé et journalisé, jamais
// ignoré silencieusement, mais JAMAIS transformé automatiquement en bug —
// un agent (surtout un employé "normal") qui échoue doit pouvoir continuer
// à travailler, pas que le harnais décide seul que c'est un défaut.
class Scenario
{
    public static function executer(CompanyMemory $memoire, string $role, string $nom, callable $action): mixed
    {
        $memoire->enregistrerEvenement([
            'actor_role' => $role,
            'action' => 'scenario_started',
            'scenario' => $nom,
        ]);

        try {
            $resultat = $action();

            $memoire->enregistrerEvenement([
                'actor_role' => $role,
                'action' => 'scenario_completed',
                'scenario' => $nom,
                'result' => 'success',
            ]);

            return $resultat;
        } catch (Throwable $e) {
            $memoire->enregistrerEvenement([
                'actor_role' => $role,
                'action' => 'scenario_failed',
                'scenario' => $nom,
                'result' => 'exception',
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            fwrite(STDERR, "[{$role}] {$nom} a levé une exception : ".$e->getMessage()."\n");

            return null;
        }
    }
}
