# Testplan 0.1.14

## Ziel

Geprüft werden ausschließlich die neue Licht-Zustandswiederherstellung, die Quittierung über alle LCN-Lichtschalter und der korrigierte obere Visualisierungsabstand. Samsung-WOL/Video und der Alarmkern entsprechen 0.1.13.

## Vorbedingungen

- LCN Light Control 0.6.1 installiert.
- Alarmanlage 0.1.14 aktualisiert und einmal **Übernehmen** ausgeführt.
- Paniklichter in der vorhandenen Liste `Panik-Lichter bei Alarm` kontrollieren.
- Für den Haupttest mindestens ein Paniklicht vorher AUS und mindestens ein Paniklicht vorher EIN lassen.

## T01 – Panik EIN erhält Vorzustand

1. Ein Paniklicht AUS lassen.
2. Ein anderes Paniklicht (z. B. Bad) vor dem Alarm EIN lassen.
3. Alarm auslösen.
4. Erwartung: das vorher AUS geschaltete Paniklicht geht EIN.
5. Erwartung: das vorher EIN geschaltete Paniklicht bleibt EIN und wird nicht getoggelt.

## T02 – Quittierung über Nicht-Paniklicht

1. Alarm erneut auslösen.
2. Einen beliebigen LCN-Lichtschalter außerhalb der Panikgruppe betätigen, bevorzugt **OG Schlafen 1**.
3. Erwartung: der aktive Alarm wird quittiert.
4. Erwartung: Video/Paniksignalisierung endet wie bisher.
5. Erwartung: alle LCN-Lichter werden auf ihren Zustand unmittelbar vor Alarmstart zurückgestellt, einschließlich des zur Quittierung betätigten Lichtes.

## T03 – Quittierung über Paniklicht

1. Alarm auslösen.
2. Ein leuchtendes Paniklicht am GT8 ausschalten.
3. Erwartung: Alarm wird quittiert.
4. Erwartung: alle Lichter erhalten wieder ihren Vor-Alarm-Zustand.

## T04 – automatisches Alarmende

1. Alarm auslösen und keinen Lichtschalter betätigen.
2. Bewegungsmelder frei werden lassen und Nachlauf abwarten.
3. Erwartung: beim automatischen Alarmende wird ebenfalls der komplette Vor-Alarm-Lichtzustand wiederhergestellt.
4. Erwartung: danach läuft ausschließlich der bekannte Countdown `Wieder scharf in …`.

## T05 – Visualisierung

1. Alarmanlagen-Kachel neu laden.
2. Erwartung: `Alarmanlage EIN/AUS` liegt deutlich unterhalb der Symcon-Kachelüberschrift `LCN Alarmanlage`; keine Überlappung.
3. `Überwachte Räume` und `Protokoll` bleiben aufklappbar.

## Sicherheitsregel

Ein LCN-Licht mit unbekanntem Rückmeldestatus wird weder für Panik-EIN noch für die Wiederherstellung blind getoggelt.
