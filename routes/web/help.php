<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Documentation Center
|--------------------------------------------------------------------------
| Centre de documentation intégré à l'application. Aucune permission n'est
| requise pour ouvrir la page : le sommaire est filtré côté client à partir
| des permissions de l'utilisateur (resources/js/Lib/docs/registry.ts), de la
| même façon que la barre de navigation (docs/08-navigation.md §8.4).
*/

Route::get('help', fn () => Inertia::render('Help/Index'))->name('help.index');
