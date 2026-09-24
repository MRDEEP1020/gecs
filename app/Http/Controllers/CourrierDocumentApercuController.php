<?php

namespace App\Http\Controllers;

use App\Models\Courrier;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CourrierDocumentApercuController extends Controller
{
    // Module 2 — aperçu du document principal scanné, affiché dans le
    // navigateur (Content-Disposition: inline) plutôt que téléchargé —
    // distinct de CourrierDocumentDownloadController (attachment, nom de
    // fichier propre). Mêmes droits que la consultation du courrier
    // (Règle n°6). PDF/JPG/PNG uniquement (seuls types acceptés à l'upload,
    // voir ScanForm/CourrierForm) : tous nativement affichables par le
    // navigateur, pas de conversion nécessaire.
    public function __invoke(Courrier $courrier): StreamedResponse
    {
        Gate::authorize('view', $courrier);

        if (! $courrier->fichier_path) {
            throw new NotFoundHttpException;
        }

        return Storage::disk('s3')->response($courrier->fichier_path);
    }
}
