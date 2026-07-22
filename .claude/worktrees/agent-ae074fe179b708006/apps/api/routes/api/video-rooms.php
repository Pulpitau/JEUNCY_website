<?php

use App\Http\Controllers\PublicVideoRoomController;
use App\Http\Controllers\VideoRoomController;
use Illuminate\Support\Facades\Route;

// Public : consultation d'une salle via son lien d'invitation (UUID non
// devinable), aucune authentification requise — voir CLAUDE.md section 7.
Route::get('video-rooms/room/{roomName}', [PublicVideoRoomController::class, 'show']);

Route::prefix('video-rooms')->middleware(['auth:api', 'role:COMPANY,CFA,ADMIN'])->group(function () {
    Route::get('/', [VideoRoomController::class, 'index']);
    Route::post('/', [VideoRoomController::class, 'store']);
    Route::post('{videoRoom}/start', [VideoRoomController::class, 'start']);
    Route::post('{videoRoom}/end', [VideoRoomController::class, 'end']);
});
