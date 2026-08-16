# Testplan 0.1.20 – Mobile Visualisierung

## Ziel

Korrektur der mobilen HTML-SDK-Kachel ohne Änderung des Alarmkerns.

## Statische Regression

1. PHP-Syntax beider Module prüfen.
2. Sämtliche JSON-Dateien validieren.
3. Bibliotheks-GUID, Hauptmodul-GUID und Prefix unverändert.
4. Kritische Alarmkernfunktionen gegenüber 0.1.19 unverändert.
5. Samsung-Mediendateien byteidentisch zu 0.1.19.
6. Keine neuen Modulvariablen/Timer/Properties durch 0.1.20.
7. `RefreshVisualization` darf ausschließlich `PushVisualizationState()` aufrufen und unmittelbar zurückkehren.
8. Keine nativen `<details>`-Elemente mehr in `module.html`.
9. Mobile Akkordeon-Panels besitzen begrenztes `max-height` und `overflow-y: scroll`.
10. `handleMessage()` ignoriert leere/ungültige Nachrichten und ersetzt den bestätigten Zustand nicht.
11. `autoFrom`/`autoTo` werden nur gesetzt, wenn die Felder im bestätigten Zustand vorhanden sind; keine festen 22:00/06:00-Fallbacks im Renderpfad.
12. GUS-/Bewegungsmelder-/Historien-DOM wird nur neu aufgebaut, wenn das jeweilige Array tatsächlich vorliegt und sich geändert hat.
13. Akkordeon-Auf/Zuklappen ruft kein `requestAction()` auf.
14. Alle Desktop-Aktionen bleiben vorhanden: Arm, Automatic, AutoFrom, AutoTo, WatchSensor*, Acknowledge.

## Realtest Symcon-App

1. 0.1.20 installieren und App-Kachel vollständig neu öffnen.
2. Alle drei Kategorien einzeln öffnen; jede lange Kategorie muss innerhalb ihres Bereichs per Finger scrollbar sein.
3. Kategorie mehrfach auf/zu: Alarmstatus und Uhrzeiten dürfen weder verschwinden noch springen.
4. Bewegungsmelderstatus: keine kurzzeitige Meldung `Keine Bewegungsmelder gefunden`, wenn zuvor Daten angezeigt wurden.
5. Überwachte Räume: keine kurzzeitige Meldung `Keine GUS konfiguriert`, wenn zuvor Daten angezeigt wurden.
6. Protokoll: Werte und Historie dürfen beim Auf-/Zuklappen nicht leer werden.
7. Zeitwerte ändern, Kategorie auf/zu und App-Kachel verlassen/sofort wieder öffnen: Werte müssen stabil bleiben.
8. GUS auslösen: Punkt/Status muss ohne Vollreload aktualisieren.
9. Alarm auslösen und quittieren: mobile Bedienung muss identisch zur Desktop-Variante funktionieren.
10. Anschließend Desktop-Kachel gegenprüfen.
