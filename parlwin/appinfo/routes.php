<?php

declare(strict_types=1);

return [
    'routes' => [
        // Hauptseite
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // Geschäfte
        ['name' => 'geschaeft#index', 'url' => '/geschaefte', 'verb' => 'GET'],
        // Vor der {id}-Route: sonst schluckt sie den Pfad als Geschäfts-ID.
        ['name' => 'geschaeft#statuswerte', 'url' => '/geschaefte/statuswerte', 'verb' => 'GET'],
        ['name' => 'geschaeft#show', 'url' => '/geschaefte/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'geschaeft#update', 'url' => '/geschaefte/{id}', 'verb' => 'PUT'],
        ['name' => 'geschaeft#setPrioritaet', 'url' => '/geschaefte/{id}/prioritaet', 'verb' => 'PUT'],
        ['name' => 'geschaeft#verknuepfen', 'url' => '/geschaefte/{id}/verknuepfen', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'geschaeft#verknuepfteEigene', 'url' => '/geschaefte/{id}/verknuepfte-eigene', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'dokument_link#index', 'url' => '/dokument-links/{objektTyp}/{objektId}', 'verb' => 'GET', 'requirements' => ['objektId' => '\d+']],
        ['name' => 'dokument_link#verknuepfe', 'url' => '/dokument-links/{objektTyp}/{objektId}', 'verb' => 'POST', 'requirements' => ['objektId' => '\d+']],
        ['name' => 'dokument_link#loese', 'url' => '/dokument-links/{objektTyp}/{objektId}/{fileId}', 'verb' => 'DELETE', 'requirements' => ['objektId' => '\d+', 'fileId' => '\d+']],
        ['name' => 'geschaeft#notizen', 'url' => '/geschaefte/{id}/notizen', 'verb' => 'GET'],
        ['name' => 'geschaeft#addNotiz', 'url' => '/geschaefte/{id}/notizen', 'verb' => 'POST'],
        ['name' => 'geschaeft#updateNotiz', 'url' => '/geschaefte/{id}/notizen/{aktionId}', 'verb' => 'PUT'],
        ['name' => 'geschaeft#deleteNotiz', 'url' => '/geschaefte/{id}/notizen/{aktionId}', 'verb' => 'DELETE'],
        ['name' => 'geschaeft#restoreNotiz', 'url' => '/geschaefte/{id}/notizen/{aktionId}/wiederherstellen', 'verb' => 'POST'],
        ['name' => 'geschaeft#notizRevisionen', 'url' => '/geschaefte/{id}/notizen/{aktionId}/revisionen', 'verb' => 'GET'],
        ['name' => 'geschaeft#addBeschluss', 'url' => '/geschaefte/{id}/beschluesse', 'verb' => 'POST'],
        ['name' => 'geschaeft#updateBeschluss', 'url' => '/geschaefte/{id}/beschluesse/{aktionId}', 'verb' => 'PUT'],
        ['name' => 'geschaeft#removeBeschluss', 'url' => '/geschaefte/{id}/beschluesse', 'verb' => 'DELETE'],
        ['name' => 'geschaeft#addVotum', 'url' => '/geschaefte/{id}/voten', 'verb' => 'POST'],
        ['name' => 'geschaeft#updateVotum', 'url' => '/geschaefte/{id}/votum', 'verb' => 'PUT'],
        ['name' => 'geschaeft#archiviereVotum', 'url' => '/geschaefte/{id}/votum/archivieren', 'verb' => 'POST'],
        ['name' => 'geschaeft#votumPdf', 'url' => '/geschaefte/{id}/votum/pdf', 'verb' => 'GET'],
        ['name' => 'geschaeft#dokumente', 'url' => '/geschaefte/{id}/dokumente', 'verb' => 'GET'],
        ['name' => 'geschaeft#dokumentErstellen', 'url' => '/geschaefte/{id}/dokumente', 'verb' => 'POST'],
        ['name' => 'geschaeft#dokumentHochladen', 'url' => '/geschaefte/{id}/dokumente/upload', 'verb' => 'POST'],
        ['name' => 'geschaeft#create', 'url' => '/geschaefte', 'verb' => 'POST'],
        ['name' => 'geschaeft#destroy', 'url' => '/geschaefte/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
        ['name' => 'geschaeft#updateStammdaten', 'url' => '/geschaefte/{id}/stammdaten', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],

        // Sitzungen
        ['name' => 'sitzung#index', 'url' => '/sitzungen', 'verb' => 'GET'],
        ['name' => 'sitzung#create', 'url' => '/sitzungen', 'verb' => 'POST'],
        ['name' => 'sitzung#show', 'url' => '/sitzungen/{id}', 'verb' => 'GET'],
        ['name' => 'sitzung#update', 'url' => '/sitzungen/{id}', 'verb' => 'PUT'],
        ['name' => 'sitzung#verknuepft', 'url' => '/sitzungen/{id}/verknuepft', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzung#verknuepfen', 'url' => '/sitzungen/{id}/verknuepfen', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzung#entkoppeln', 'url' => '/sitzungen/{id}/entkoppeln', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzung#dokumente', 'url' => '/sitzungen/{id}/dokumente', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzung#dokumentErstellen', 'url' => '/sitzungen/{id}/dokumente', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzung#geschaefte', 'url' => '/sitzungen/{id}/geschaefte', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzung#geschaeftVerlinken', 'url' => '/sitzungen/{id}/geschaefte', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzung#geschaeftEntlinken', 'url' => '/sitzungen/{id}/geschaefte/{geschaeftId}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+', 'geschaeftId' => '\d+']],
        ['name' => 'sitzung#vorstoesse', 'url' => '/sitzungen/{id}/vorstoesse', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzung#vorstossVerlinken', 'url' => '/sitzungen/{id}/vorstoesse', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzung#vorstossEntlinken', 'url' => '/sitzungen/{id}/vorstoesse/{vorstossId}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+', 'vorstossId' => '\d+']],
        ['name' => 'sitzung#todoErstellen', 'url' => '/sitzungen/{id}/todo', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],

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
        ['name' => 'sitzungstyp#fraktionsraumSicherstellen', 'url' => '/sitzungstypen/fraktionsraum-sicherstellen', 'verb' => 'POST'],

        // Traktanden
        ['name' => 'traktandum#index', 'url' => '/sitzungen/{sitzungId}/traktanden', 'verb' => 'GET'],
        ['name' => 'traktandum#update', 'url' => '/sitzungen/{sitzungId}/traktanden/{id}', 'verb' => 'PUT'],
        ['name' => 'traktandum#notizen', 'url' => '/sitzungen/{sitzungId}/traktanden/{id}/notizen', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'traktandum#addNotiz', 'url' => '/sitzungen/{sitzungId}/traktanden/{id}/notizen', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'traktandum#updateNotiz', 'url' => '/sitzungen/{sitzungId}/traktanden/{id}/notizen/{aktionId}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'traktandum#deleteNotiz', 'url' => '/sitzungen/{sitzungId}/traktanden/{id}/notizen/{aktionId}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'traktandum#restoreNotiz', 'url' => '/sitzungen/{sitzungId}/traktanden/{id}/notizen/{aktionId}/wiederherstellen', 'verb' => 'POST', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'traktandum#notizRevisionen', 'url' => '/sitzungen/{sitzungId}/traktanden/{id}/notizen/{aktionId}/revisionen', 'verb' => 'GET', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'sitzung#notizen', 'url' => '/sitzungen/{id}/notizen', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzung#addNotiz', 'url' => '/sitzungen/{id}/notizen', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'sitzung#updateNotiz', 'url' => '/sitzungen/{id}/notizen/{aktionId}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'sitzung#deleteNotiz', 'url' => '/sitzungen/{id}/notizen/{aktionId}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'sitzung#restoreNotiz', 'url' => '/sitzungen/{id}/notizen/{aktionId}/wiederherstellen', 'verb' => 'POST', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'sitzung#notizRevisionen', 'url' => '/sitzungen/{id}/notizen/{aktionId}/revisionen', 'verb' => 'GET', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],

        // Vorstösse
        ['name' => 'vorstoss#index', 'url' => '/vorstoesse', 'verb' => 'GET'],
        ['name' => 'vorstoss#fuerGeschaeft', 'url' => '/vorstoesse/geschaeft/{geschaeftId}', 'verb' => 'GET', 'requirements' => ['geschaeftId' => '\d+']],
        ['name' => 'vorstoss#create', 'url' => '/vorstoesse', 'verb' => 'POST'],
        ['name' => 'vorstoss#update', 'url' => '/vorstoesse/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
        ['name' => 'vorstoss#destroy', 'url' => '/vorstoesse/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
        // Vorstoss-Notizen — GLEICHE Endpunkte wie beim Geschäft (geteilter Notiz-Code)
        ['name' => 'vorstoss#notizen', 'url' => '/vorstoesse/{id}/notizen', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'vorstoss#addNotiz', 'url' => '/vorstoesse/{id}/notizen', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'vorstoss#updateNotiz', 'url' => '/vorstoesse/{id}/notizen/{aktionId}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'vorstoss#deleteNotiz', 'url' => '/vorstoesse/{id}/notizen/{aktionId}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'vorstoss#restoreNotiz', 'url' => '/vorstoesse/{id}/notizen/{aktionId}/wiederherstellen', 'verb' => 'POST', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'vorstoss#notizRevisionen', 'url' => '/vorstoesse/{id}/notizen/{aktionId}/revisionen', 'verb' => 'GET', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'vorstoss#verknuepfen', 'url' => '/vorstoesse/{id}/verknuepfen', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'vorstoss#dokumente', 'url' => '/vorstoesse/{id}/dokumente', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'vorstoss#dokumentErstellen', 'url' => '/vorstoesse/{id}/dokumente', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'vorstoss#dokumentHochladen', 'url' => '/vorstoesse/{id}/dokumente/upload', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],

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

        ['name' => 'settings#getStatusKuerzel', 'url' => '/settings/status-kuerzel', 'verb' => 'GET'],
        ['name' => 'settings#setStatusKuerzel', 'url' => '/settings/status-kuerzel', 'verb' => 'POST'],

        ['name' => 'settings#getEigeneTypen', 'url' => '/settings/eigene-typen', 'verb' => 'GET'],
        ['name' => 'settings#setEigeneTypen', 'url' => '/settings/eigene-typen', 'verb' => 'POST'],

        ['name' => 'settings#getBudgetKommissionZuordnung', 'url' => '/settings/budget-kommission-zuordnung', 'verb' => 'GET'],
        ['name' => 'settings#setBudgetKommissionZuordnung', 'url' => '/settings/budget-kommission-zuordnung', 'verb' => 'POST'],

        ['name' => 'settings#getSyncZeitplan', 'url' => '/settings/sync-zeitplan', 'verb' => 'GET'],
        ['name' => 'settings#setSyncZeitplan', 'url' => '/settings/sync-zeitplan', 'verb' => 'POST'],

        // Manuelle Synchronisation auslösen (SettingsController::run)
        ['name' => 'settings#syncStatus', 'url' => '/sync/status', 'verb' => 'GET'],
        ['name' => 'settings#cancelSync', 'url' => '/sync/cancel', 'verb' => 'POST'],
        ['name' => 'settings#run', 'url' => '/sync', 'verb' => 'POST'],

        // Budget (vor der {jahr}-Route stehen die spezifischen Pfade)
        ['name' => 'budget#jahre', 'url' => '/budget/jahre', 'verb' => 'GET'],
        ['name' => 'budget#verfuegbar', 'url' => '/budget/verfuegbar', 'verb' => 'GET'],
        ['name' => 'budget#antragErstellen', 'url' => '/budget/{jahr}/antraege', 'verb' => 'POST', 'requirements' => ['jahr' => '\d+']],
        ['name' => 'budget#verteilung', 'url' => '/budget/{jahr}/verteilung', 'verb' => 'PUT', 'requirements' => ['jahr' => '\d+']],
        ['name' => 'budget#importieren', 'url' => '/budget/{jahr}/import', 'verb' => 'POST', 'requirements' => ['jahr' => '\d+']],
        ['name' => 'budget#novemberbrief', 'url' => '/budget/{jahr}/novemberbrief', 'verb' => 'POST', 'requirements' => ['jahr' => '\d+']],
        ['name' => 'budget#antraegePdf', 'url' => '/budget/{jahr}/antraege-pdf', 'verb' => 'GET', 'requirements' => ['jahr' => '\d+']],
        ['name' => 'budget#ansicht', 'url' => '/budget/{jahr}', 'verb' => 'GET', 'requirements' => ['jahr' => '\d+']],
        ['name' => 'budget#antragAendern', 'url' => '/budget/antraege/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
        ['name' => 'budget#antragLoeschen', 'url' => '/budget/antraege/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
        ['name' => 'budget#entscheid', 'url' => '/budget/antraege/{id}/entscheid', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
        ['name' => 'budget#verknuepfen', 'url' => '/budget/antraege/{id}/verknuepfung', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
        ['name' => 'budget#pauschalErstellen', 'url' => '/budget/{jahr}/pauschal', 'verb' => 'POST', 'requirements' => ['jahr' => '\d+']],
        ['name' => 'budget#pauschalAendern', 'url' => '/budget/pauschal/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
        ['name' => 'budget#pauschalLoeschen', 'url' => '/budget/pauschal/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
        ['name' => 'budget#notizen', 'url' => '/budget/antraege/{id}/notizen', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'budget#addNotiz', 'url' => '/budget/antraege/{id}/notizen', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'budget#updateNotiz', 'url' => '/budget/antraege/{id}/notizen/{aktionId}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'budget#deleteNotiz', 'url' => '/budget/antraege/{id}/notizen/{aktionId}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'budget#restoreNotiz', 'url' => '/budget/antraege/{id}/notizen/{aktionId}/wiederherstellen', 'verb' => 'POST', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
        ['name' => 'budget#notizRevisionen', 'url' => '/budget/antraege/{id}/notizen/{aktionId}/revisionen', 'verb' => 'GET', 'requirements' => ['id' => '\d+', 'aktionId' => '\d+']],
    ],
];
