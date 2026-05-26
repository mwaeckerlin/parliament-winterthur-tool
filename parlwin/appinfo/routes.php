<?php

declare(strict_types=1);

return [
    'routes' => [
        // Hauptseite
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // Geschäfte
        ['name' => 'geschaeft#index', 'url' => '/geschaefte', 'verb' => 'GET'],
        ['name' => 'geschaeft#show', 'url' => '/geschaefte/{id}', 'verb' => 'GET'],
        ['name' => 'geschaeft#update', 'url' => '/geschaefte/{id}', 'verb' => 'PUT'],
        ['name' => 'geschaeft#addNotiz', 'url' => '/geschaefte/{id}/notizen', 'verb' => 'POST'],
        ['name' => 'geschaeft#updateNotiz', 'url' => '/geschaefte/{id}/notizen/{aktionId}', 'verb' => 'PUT'],
        ['name' => 'geschaeft#deleteNotiz', 'url' => '/geschaefte/{id}/notizen/{aktionId}', 'verb' => 'DELETE'],
        ['name' => 'geschaeft#addBeschluss', 'url' => '/geschaefte/{id}/beschluesse', 'verb' => 'POST'],
        ['name' => 'geschaeft#removeBeschluss', 'url' => '/geschaefte/{id}/beschluesse', 'verb' => 'DELETE'],
        ['name' => 'geschaeft#addVotum', 'url' => '/geschaefte/{id}/voten', 'verb' => 'POST'],
        ['name' => 'geschaeft#updateVotum', 'url' => '/geschaefte/{id}/votum', 'verb' => 'PUT'],
        ['name' => 'geschaeft#archiviereVotum', 'url' => '/geschaefte/{id}/votum/archivieren', 'verb' => 'POST'],
        ['name' => 'geschaeft#votumPdf', 'url' => '/geschaefte/{id}/votum/pdf', 'verb' => 'GET'],
        ['name' => 'geschaeft#dokumente', 'url' => '/geschaefte/{id}/dokumente', 'verb' => 'GET'],
        ['name' => 'geschaeft#dokumentErstellen', 'url' => '/geschaefte/{id}/dokumente', 'verb' => 'POST'],

        // Sitzungen
        ['name' => 'sitzung#index', 'url' => '/sitzungen', 'verb' => 'GET'],
        ['name' => 'sitzung#create', 'url' => '/sitzungen', 'verb' => 'POST'],
        ['name' => 'sitzung#show', 'url' => '/sitzungen/{id}', 'verb' => 'GET'],
        ['name' => 'sitzung#update', 'url' => '/sitzungen/{id}', 'verb' => 'PUT'],

        // Sitzungs-Vorlagen / Sitzungstypen.
        // Hinweis: Die spezifischen /nc/groups und /nc/users Routen MUESSEN
        // vor den generischen /{id}-Routen stehen, sonst koennte das Routing
        // "nc" als id-Parameter interpretieren (Typkonvertierung schlaegt
        // dann fehl und es kommt 404 / leere Antwort). Zusaetzlich ist {id}
        // auf reine Ziffern eingeschraenkt (requirements).
        ['name' => 'sitzungstyp#ncGroups', 'url' => '/sitzungstypen/nc/groups', 'verb' => 'GET'],
        ['name' => 'sitzungstyp#ncUsers', 'url' => '/sitzungstypen/nc/users', 'verb' => 'GET'],
        ['name' => 'sitzungstyp#index', 'url' => '/sitzungstypen', 'verb' => 'GET'],
        ['name' => 'sitzungstyp#create', 'url' => '/sitzungstypen', 'verb' => 'POST'],
        ['name' => 'sitzungstyp#vorschau', 'url' => '/sitzungstypen/{id}/vorschau', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzungstyp#show', 'url' => '/sitzungstypen/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzungstyp#update', 'url' => '/sitzungstypen/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzungstyp#destroy', 'url' => '/sitzungstypen/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],

        // Traktanden
        ['name' => 'traktandum#index', 'url' => '/sitzungen/{sitzungId}/traktanden', 'verb' => 'GET'],
        ['name' => 'traktandum#update', 'url' => '/sitzungen/{sitzungId}/traktanden/{id}', 'verb' => 'PUT'],

        // Mitglieder
        ['name' => 'mitglied#index', 'url' => '/mitglieder', 'verb' => 'GET'],
        ['name' => 'mitglied#show', 'url' => '/mitglieder/{id}', 'verb' => 'GET'],

        // Kommissionen
        ['name' => 'kommission#index', 'url' => '/kommissionen', 'verb' => 'GET'],
        ['name' => 'kommission#show', 'url' => '/kommissionen/{id}', 'verb' => 'GET'],

        // Fraktionen
        ['name' => 'fraktion#index', 'url' => '/fraktionen', 'verb' => 'GET'],
        ['name' => 'fraktion#show', 'url' => '/fraktionen/{id}', 'verb' => 'GET'],

        // Einstellungen (Admin-API)
        ['name' => 'settings#get', 'url' => '/settings', 'verb' => 'GET'],
        ['name' => 'settings#set', 'url' => '/settings', 'verb' => 'POST'],
        ['name' => 'settings#fraktionMitglieder', 'url' => '/settings/fraktion-mitglieder', 'verb' => 'GET'],
        ['name' => 'settings#saveFraktionMitgliederMapping', 'url' => '/settings/fraktion-mitglieder/mappings', 'verb' => 'POST'],
        ['name' => 'settings#provisionFraktionMitglieder', 'url' => '/settings/fraktion-mitglieder/anlegen', 'verb' => 'POST'],
        ['name' => 'settings#getFraktionssitzung', 'url' => '/settings/fraktionssitzung', 'verb' => 'GET'],
        ['name' => 'settings#setFraktionssitzung', 'url' => '/settings/fraktionssitzung', 'verb' => 'POST'],
        ['name' => 'settings#setProtokollfuehrer', 'url' => '/settings/protokollfuehrer', 'verb' => 'POST'],
        ['name' => 'settings#setFraktionspraesident', 'url' => '/settings/fraktionspraesident', 'verb' => 'POST'],
        ['name' => 'settings#addPraesidiumStellvertretung', 'url' => '/settings/praesidium-stellvertretung', 'verb' => 'POST'],
        ['name' => 'settings#addProtokollfuehrerStellvertretung', 'url' => '/settings/protokollfuehrer-stellvertretung', 'verb' => 'POST'],
        ['name' => 'settings#addKommissionsmitglied', 'url' => '/settings/kommissionsmitglied', 'verb' => 'POST'],

        // Manuelle Synchronisation auslösen (SettingsController::run)
        ['name' => 'settings#syncStatus', 'url' => '/sync/status', 'verb' => 'GET'],
        ['name' => 'settings#cancelSync', 'url' => '/sync/cancel', 'verb' => 'POST'],
        ['name' => 'settings#run', 'url' => '/sync', 'verb' => 'POST'],
    ],
];
