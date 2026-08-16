# Testplan 0.1.23 – mobile Touch-/Scrollkorrektur

## Smartphone

1. Alarmanlage EIN/AUS und Automatik per Touch bedienen; kein Zurückspringen durch alte Zustandsmeldungen.
2. Scharf-ab- und Unscharf-ab-Zeit ändern; Werte dürfen beim Auf-/Zuklappen nicht zurückspringen.
3. Überwachte Räume öffnen. Scrollleiste muss bei langem Inhalt sichtbar sein.
4. Mit dem Finger mitten über den Raumnamen nach oben/unten wischen; Inhalt muss folgen.
5. Eine Wischbewegung über einem Raum-Schalter darf den Schalter nicht versehentlich auslösen; normales Tippen muss weiterhin funktionieren.
6. Scrollgriff rechts mit dem Finger ziehen; Inhalt muss proportional folgen.
7. Auf die freie Scrollleiste tippen; Inhalt muss an die entsprechende Position springen.
8. Kategorie schließen/öffnen; Scrollposition muss erhalten bleiben.
9. Status Bewegungsmelder identisch mit Wischen, Scrollgriff und Leistentap testen; Live-Zustandswechsel dürfen die Position nicht zurücksetzen.
10. Protokoll identisch testen; alle Werte und Historie müssen beim Scrollen/Aufklappen erhalten bleiben.
11. Kategorien mehrfach öffnen/schließen; Alarmstatus, Zeiten, GUS- und Protokolldaten dürfen nicht flackern/leerlaufen.

## Desktop

12. Alle Kategorien öffnen/schließen und normal mit Maus/Scrollrad bedienen.
13. Alarmanlage, Automatik, Raum-Schalter und Zeitfelder bedienen.

## Regression

14. Alarm auslösen, Paniklicht/TV/Video prüfen, quittieren und Wieder-scharf-Phase abwarten.
15. Keine neuen sichtbaren Variablen, Timer, Properties oder LCN-Abfragen.
