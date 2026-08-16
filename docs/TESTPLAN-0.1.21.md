# Testplan 0.1.21 – Mobile Bedienung

## Ziel

Touch-stabile Bedienung der mobilen HTML-SDK-Kachel ohne Änderung des Alarmkerns.

## Statische Regression

1. PHP-Syntax beider Module prüfen.
2. Sämtliche JSON-Dateien validieren.
3. Bibliotheks-GUID, Hauptmodul-GUID und Prefix unverändert.
4. `LCNAlarmanlage/module.php` byteidentisch zu 0.1.20.
5. MediaServer und Videodateien byteidentisch zu 0.1.20.
6. Keine neuen Modulvariablen, Timer oder Properties.
7. Keine versteckten nativen Checkbox-Schalter mehr in `module.html`.
8. Alarmanlage, Automatik und WatchSensor verwenden eigene `role=switch` Buttons.
9. Jeder Schalter setzt vor `requestAction()` einen lokalen Pending-Zustand.
10. Ein alter Serverwert darf während Pending die sichtbare Stellung nicht überschreiben.
11. Eine passende Serverbestätigung beendet Pending.
12. Pending endet spätestens nach fünf Sekunden und fordert dann einmal den echten Istzustand an.
13. Pending wird kurzzeitig lokal gespeichert, damit ein WebView-Rebuild den Bedienwert nicht verliert.
14. Zeitfelder werden bei Fokus nicht durch asynchrone Serverwerte überschrieben.
15. Change+Blur derselben Uhrzeit erzeugen keinen doppelten Auftrag.
16. Listenaktualisierungen erhalten die Scrollposition des jeweiligen Panels.
17. Auf-/Zuklappen einer Kategorie sendet weiterhin keine Modulaktion.
18. Touch-Scroll-Fallback greift nur bei einer tatsächlichen vertikalen Wischbewegung.
19. Acknowledge bleibt verfügbar und unverändert.

## Realtest Symcon-App

1. Alarmanlage EIN/AUS zehnmal einzeln betätigen; jeder Touch muss genau eine Zustandsänderung ergeben und darf nicht zurückspringen.
2. Automatik mehrfach EIN/AUS; Anzeige muss bis zur Modulbestätigung stabil bleiben.
3. In Überwachte Räume mehrere GUS-Schalter betätigen; Schalter und Statuspunkt müssen sofort gemeinsam umspringen und nach Bestätigung stabil bleiben.
4. Während eines Schaltvorgangs Bewegung an einem GUS erzeugen; die parallel eintreffende Statusmeldung darf den bedienten Schalter nicht zurücksetzen.
5. `Scharf ab` ändern; während der Zeitdialog geöffnet ist darf keine Hintergrundmeldung den Eingabewert überschreiben.
6. Dasselbe für `Unscharf ab`.
7. Alle drei Kategorien öffnen und scrollen; Inhalt muss vollständig erreichbar sein.
8. Während des Scrollens GUS auslösen; Scrollposition darf bei Aktualisierung nicht auf den Anfang springen.
9. Kategorien mehrfach auf/zu; Alarmstatus, Uhrzeiten, Bewegungsmelder und Protokollwerte bleiben sichtbar und korrekt.
10. Alarm auslösen und über die mobile Schaltfläche quittieren.
11. Danach Desktop-Kachel gegenprüfen; alle Funktionen müssen identisch reagieren.
12. Symcon-App kurz in den Hintergrund und wieder nach vorn bringen; bestätigte Zustände dürfen nicht springen.
