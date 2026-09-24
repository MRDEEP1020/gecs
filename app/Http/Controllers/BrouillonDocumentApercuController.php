<?php

namespace App\Http\Controllers;

use App\Models\CourrierBrouillon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BrouillonDocumentApercuController extends Controller
{
    // Module 1/2 — aperçu du document scanné AVANT la création du Courrier
    // (flux scan-first, panneau "Aperçu du document" du formulaire
    // d'enregistrement), affiché dans le navigateur (Content-Disposition:
    // inline) — même principe que CourrierDocumentApercuController, pour un
    // CourrierBrouillon plutôt qu'un Courrier. Droits : mêmes que
    // l'utilisation du brouillon (Règle n°6, CourrierBrouillonPolicy::utiliser).
    public function __invoke(CourrierBrouillon $brouillon): StreamedResponse
    {
        Gate::authorize('utiliser', $brouillon);

        if (! $brouillon->fichier_path) {
            throw new NotFoundHttpException;
        }

        return Storage::disk('s3')->response($brouillon->fichier_path);
    }
}
