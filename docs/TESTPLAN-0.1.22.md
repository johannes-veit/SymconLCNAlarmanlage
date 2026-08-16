# Testplan 0.1.22 – mobile Visualisierung

## Smartphone

1. Alarmstatus und beide Automatikzeiten müssen sofort korrekt sichtbar sein.
2. Alarmanlage EIN/AUS und Automatik mehrfach per Touch schalten; kein Zurückspringen durch alte Statusmeldungen.
3. Überwachte Räume öffnen. Die eigene Scrollleiste muss erscheinen, wenn der Inhalt höher als das Fenster ist.
4. Im Inhalt nach oben/unten wischen. Die Liste muss direkt folgen; durch die Wischgeste darf kein Raum-Schalter betätigt werden.
5. Den Scrollgriff rechts mit dem Finger ziehen und die Scrollleiste antippen; die Liste muss die Position ändern.
6. Einen Raum-Schalter normal antippen; Bedienung muss weiterhin funktionieren.
7. Kategorie schließen und wieder öffnen; Scrollposition muss erhalten bleiben.
8. Status Bewegungsmelder öffnen und Wischen/Scrollgriff identisch prüfen. Während Live-Statuswechseln darf die Position nicht springen.
9. Protokoll öffnen und Wischen/Scrollgriff identisch prüfen. Erstauslöser, letzte Bewegung, Anzahl, letzter Alarm und Historie müssen sichtbar bleiben.
10. Kategorien mehrfach öffnen/schließen. Alarmstatus und Zeiten dürfen nicht verschwinden oder auf Defaultwerte zurückspringen.

## Desktop

11. Alle drei Kategorien öffnen/schließen und prüfen, dass die normale Desktop-Darstellung unverändert funktioniert.
12. Alarmanlage, Automatik, Raum-Schalter und Zeitfelder bedienen.

## Regression

13. Alarm auslösen, Paniklicht/TV/Video prüfen, quittieren und Wieder-scharf-Phase abwarten.
14. Keine neuen sichtbaren Variablen, Timer oder LCN-Polling-Aufrufe dürfen entstanden sein.
