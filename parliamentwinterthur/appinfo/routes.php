<?php

declare(strict_types=1);

return [
    'routes' => [
        // Hauptseite
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // Geschäfte
        ['name' => 'geschaeft#index',  'url' => '/geschaefte',           'verb' => 'GET'],
        ['name' => 'geschaeft#show',   'url' => '/geschaefte/{id}',      'verb' => 'GET'],
        ['name' => 'geschaeft#update', 'url' => '/geschaefte/{id}',      'verb' => 'PUT'],

        // Sitzungen
        ['name' => 'sitzung#index',    'url' => '/sitzungen',            'verb' => 'GET'],
        ['name' => 'sitzung#show',     'url' => '/sitzungen/{id}',       'verb' => 'GET'],
        ['name' => 'sitzung#update',   'url' => '/sitzungen/{id}',       'verb' => 'PUT'],

        // Traktanden
        ['name' => 'traktandum#index',  'url' => '/sitzungen/{sitzungId}/traktanden',      'verb' => 'GET'],
        ['name' => 'traktandum#update', 'url' => '/sitzungen/{sitzungId}/traktanden/{id}', 'verb' => 'PUT'],

        // Mitglieder
        ['name' => 'mitglied#index',   'url' => '/mitglieder',           'verb' => 'GET'],
        ['name' => 'mitglied#show',    'url' => '/mitglieder/{id}',      'verb' => 'GET'],

        // Kommissionen
        ['name' => 'kommission#index', 'url' => '/kommissionen',         'verb' => 'GET'],
        ['name' => 'kommission#show',  'url' => '/kommissionen/{id}',    'verb' => 'GET'],

        // Fraktionen
        ['name' => 'fraktion#index',   'url' => '/fraktionen',           'verb' => 'GET'],
        ['name' => 'fraktion#show',    'url' => '/fraktionen/{id}',      'verb' => 'GET'],

        // Einstellungen (Admin-API)
        ['name' => 'settings#get',     'url' => '/settings',             'verb' => 'GET'],
        ['name' => 'settings#set',     'url' => '/settings',             'verb' => 'POST'],

        // Manuelle Synchronisation auslösen (SettingsController::run)
        ['name' => 'settings#run',     'url' => '/sync',                 'verb' => 'POST'],
    ],
];
