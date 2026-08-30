# Testplan 0.1.25 – Samsung Alarm-Lautstärke Long-Press (isolierte Teststufe)

## Ziel
Nur Press/Release am realen Samsung Q90 testen. In 0.1.25 ist die Funktion noch nicht an Alarmstart, Alarmende, WOL, Video oder Power-Off angebunden.

## T01 – Regression vor Lautstärketest
1. Alarmanlage normal öffnen.
2. Prüfen, dass Konfiguration/Visualisierung wie 0.1.24 vorhanden ist.
3. Keine Alarmaktion auslösen.

Erwartung: Keine Änderung am bisherigen Verhalten.

## T02 – VOLUP Long-Press
1. Samsung-TV manuell einschalten und warten, bis er vollständig bedienbar ist.
2. In der Instanzkonfiguration `TEST VOLUP – 3 s halten` drücken.
3. Lautstärke am TV beobachten.

Erwartung: `KEY_VOLUP Press`, ca. 3 Sekunden halten, dann `Release`. Die Lautstärke steigt kontinuierlich/deutlich. Kein Alarmzustand ändert sich.

## T03 – VOLDOWN Long-Press
1. TV eingeschaltet lassen.
2. `TEST VOLDOWN – 3 s halten` drücken.
3. Lautstärke beobachten.

Erwartung: `KEY_VOLDOWN Press`, ca. 3 Sekunden halten, dann `Release`. Die Lautstärke sinkt kontinuierlich/deutlich. Kein Alarmzustand ändert sich.

## T04 – Fehlerisolation
1. Optional TV ausschalten oder Verbindung kurz nicht verfügbar machen.
2. Einen Testbutton auslösen.

Erwartung: Test meldet Fehler/Fehlschlag, aber Alarmanlage, Automatik, GUS, Licht, WOL/Video und Power-Off bleiben unbeeinflusst.

## Freigabe für Folgeversion
Nur wenn T02 und T03 am Q90 korrekt arbeiten, wird die Zusatzfunktion in einer Folgeversion an diese Punkte angebunden:
- Video tatsächlich läuft -> VOLUP Press -> 3 s -> Release.
- Quittierung/Alarmende -> Video stoppen -> VOLDOWN Press -> 3 s -> Release -> unabhängig vom Ergebnis normaler bestehender TV-Power-Off.
