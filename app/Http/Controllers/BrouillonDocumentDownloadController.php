<?php

namespace App\Http\Controllers;

use App\Models\CourrierBrouillon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BrouillonDocumentDownloadController extends Controller
{
    // Module 1/2 — téléchargement du document scanné AVANT la création du
    // Courrier (bouton "Télécharger" de la modale "Aperçu du courrier") —
    // même principe que CourrierDocumentDownloadController (attachment, nom
    // de fichier propre), pour un CourrierBrouillon. Droits : mêmes que
    // l'utilisation du brouillon + courriers.telecharger (2026-09-23,
    // CourrierBrouillonPolicy::telecharger).
    public function __invoke(CourrierBrouillon $brouillon): StreamedResponse
    {
        Gate::authorize('telecharger', $brouillon);

        if (! $brouillon->fichier_path) {
            throw new NotFoundHttpException;
        }

        return Storage::disk('s3')->download(
            $brouillon->fichier_path,
            $brouillon->nom_original,
        );
    }
}
