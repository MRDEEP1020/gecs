<?php

namespace App\Http\Controllers;

use App\Models\PieceJointe;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PieceJointeDownloadController extends Controller
{
    // Module 1 — téléchargement d'une pièce jointe : consultation du
    // courrier + courriers.telecharger (2026-09-23, CourrierPolicy::telecharger).
    public function __invoke(PieceJointe $pieceJointe): StreamedResponse
    {
        Gate::authorize('telecharger', $pieceJointe->courrier);
        Gate::authorize('voirPiecesJointes', $pieceJointe->courrier);

        return Storage::disk('s3')->download($pieceJointe->fichier_path, $pieceJointe->nom_original);
    }
}
