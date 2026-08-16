# Testplan 0.1.17

## Ziel

Reine Visu-Abnahme. Alle Prozessfunktionen bleiben gegenüber 0.1.16 unverändert.

## Prüfpunkte

1. Bibliothek von 0.1.16 auf 0.1.17 aktualisieren; bestehende Instanz und Konfiguration müssen erhalten bleiben.
2. Kachel oben öffnen: `Alarmanlage EIN/AUS` liegt weiterhin unterhalb der nativen Kachelüberschrift.
3. `Status Bewegungsmelder` öffnen und innerhalb der Kachel nach unten scrollen. Kein Text darf sichtbar unter die native Kachelüberschrift laufen.
4. In der Bewegungsmelderliste darf `Bewegungsmelder` bzw. `Bewegungsmeder` nicht mehr vor den Raumnamen stehen.
5. Jeder Eintrag zeigt links einen Punkt und rechts einen Zustand.
6. GUS AUS: grauer Punkt und `Ruhe`.
7. GUS AN: grüner/türkiser Punkt und `Bewegung`.
8. Statuswechsel muss ereignisgesteuert ohne neues Polling erscheinen.
9. `Überwachte Räume` und `Protokoll` bleiben aufklappbar und funktional.
10. Alarmtest, Quittierung, Licht-Restore, WOL/Video und Neustartschutz als Regression nur stichprobenartig prüfen; Code dieser Pfade ist gegenüber 0.1.16 unverändert.

## Rollback

0.1.14 bleibt die bewährte Rollback-Basis. Ein Rücksprung darf keine GUIDs, Prefixe oder bestehende Konfiguration verlieren.
