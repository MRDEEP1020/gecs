<?php

namespace App\Http\Controllers;

use App\Models\Courrier;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CourrierDocumentDownloadController extends Controller
{
    // Module 2 — téléchargement du document principal scanné (fichier_path,
    // distinct des pièces jointes). Consultation du courrier +
    // courriers.telecharger (2026-09-23, CourrierPolicy::telecharger).
    public function __invoke(Courrier $courrier): StreamedResponse
    {
        Gate::authorize('telecharger', $courrier);

        if (! $courrier->fichier_path) {
            throw new NotFoundHttpException;
        }

        return Storage::disk('s3')->download(
            $courrier->fichier_path,
            $courrier->numero_reference.'.'.pathinfo($courrier->fichier_path, PATHINFO_EXTENSION),
        );
    }
}
