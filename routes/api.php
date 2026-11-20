<?php

use App\Http\Controllers\AmiController;
use App\Http\Controllers\CompteController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

// API de Gbaka Abidjan (préfixe /api). Jeton Sanctum dans l'en-tête « Authorization: Bearer … ».

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/inscription', [CompteController::class, 'inscription']);
    Route::post('/connexion', [CompteController::class, 'connexion']);
});

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('/moi', [CompteController::class, 'moi']);
    Route::post('/deconnexion', [CompteController::class, 'deconnexion']);
    Route::put('/appareil', [CompteController::class, 'appareil']);
    Route::delete('/compte', [CompteController::class, 'supprimer']);

    Route::get('/amis', [AmiController::class, 'liste']);
    Route::post('/amis', [AmiController::class, 'demander'])->middleware('throttle:20,1');
    Route::post('/amis/{amitie}/accepter', [AmiController::class, 'accepter']);
    Route::delete('/amis/{pseudo}', [AmiController::class, 'retirer']);

    Route::post('/invitations', [InvitationController::class, 'envoyer'])->middleware('throttle:30,1');
    Route::get('/invitations', [InvitationController::class, 'recues']);
    Route::get('/invitations/envoyees', [InvitationController::class, 'envoyees']);
    Route::post('/invitations/annuler', [InvitationController::class, 'annuler']);
    Route::post('/invitations/{invitation}/reponse', [InvitationController::class, 'repondre']);
});
