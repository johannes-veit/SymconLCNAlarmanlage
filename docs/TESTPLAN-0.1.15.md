# Testplan 0.1.15

## Ziel

0.1.15 ist eine rollbackfähige Erweiterung der funktionierenden 0.1.14. Abgenommen werden der zusätzliche Visualisierungsabstand, die Neustart-/Ausfallsicherheit und die optionale E-Mail-Quittierung. Lichtzustandswiederherstellung sowie Samsung-WOL/Video müssen unverändert weiter funktionieren.

## Vorbedingungen

- Backup der Symcon-Konfiguration vorhanden.
- ZIP `LCN-Alarmanlage_v0.1.14.zip` als Rollback-Basis aufbewahren.
- LCN Light Control 0.6.1 unverändert installiert.
- Samsung-Testmodule bis zum Abschluss von T01-T10 noch installiert lassen.

## T01 – normale Alarmfunktion, TV bereits EIN

1. TV einschalten.
2. Alarmanlage scharf schalten und Bewegung auslösen.
3. Erwartung: Alarm, Paniklicht und Alarmvideo starten wie in 0.1.14.
4. Quittieren.
5. Erwartung: Video stoppt; TV bleibt EIN; Lichtzustände werden auf Vor-Alarm-Zustand zurückgestellt.

## T02 – normale Alarmfunktion, TV AUS / WOL

1. TV ausreichend lange AUS lassen.
2. Alarm auslösen.
3. Erwartung: WOL startet TV; Alarmvideo startet und läuft endlos.
4. Quittieren.
5. Erwartung: Video stoppt; vom Alarm gestarteter TV wird einmalig ausgeschaltet und später nicht erneut durch einen alten AUS-Auftrag beeinflusst.

## T03 – Lichtzustände unverändert korrekt

1. Ein Paniklicht vorher AUS, ein anderes vorher EIN.
2. Alarm auslösen.
3. Erwartung: nur das vorher AUS befindliche Paniklicht wird eingeschaltet.
4. Über beliebigen GT8-Lichtschalter quittieren.
5. Erwartung: alle LCN-Lichter exakt auf Vor-Alarm-Zustand zurück.

## T04 – Visualisierung

1. Kachel vollständig neu laden.
2. Erwartung: `Alarmanlage EIN/AUS` liegt deutlich unter der Symcon-Kachelüberschrift, ohne Überlappung.
3. `Überwachte Räume` und `Protokoll` auf-/zuklappen.
4. Erwartung: beide Bereiche unverändert funktionsfähig.

## T05 – Neustart im Zustand AUS

1. Alarmanlage AUS.
2. Symcon/SymBox kontrolliert neu starten.
3. Erwartung: kein Alarm, Anlage bleibt AUS.

## T06 – Neustart im Zustand SCHARF, alle GUS frei

1. Alarmanlage EIN und `ALARMANLAGE SCHARF`.
2. Alle überwachten GUS frei lassen.
3. Symcon/SymBox neu starten.
4. Erwartung unmittelbar nach Start: kein Alarm; Status zeigt zunächst Sensorabgleich/Initialisierung.
5. Erwartung nach frischen GUS-Rückmeldungen: Hauptschalter weiterhin EIN und Anlage wieder `ALARMANLAGE SCHARF`.
6. Erst danach eine echte neue Bewegung erzeugen; diese muss normal Alarm auslösen.

## T07 – Neustart bei bereits aktivem GUS

1. Alarmanlage EIN.
2. Einen GUS aktiv halten und währenddessen Symcon neu starten.
3. Erwartung: der beim Hochfahren vorhandene TRUE-Zustand erzeugt **keinen Fehlalarm**.
4. Erwartung: Anlage bleibt EIN, aber nicht auslösebereit, solange der Melder aktiv/Startstatus unvollständig ist.
5. GUS frei werden lassen.
6. Erwartung: Anlage wird danach scharf. Erst eine folgende neue Bewegung darf Alarm auslösen.

## T08 – Neustart während aktivem Alarm

1. Alarm auslösen und nicht quittieren.
2. Symcon neu starten.
3. Erwartung: keine zweite Alarm-Session und keine doppelte Erstbenachrichtigung.
4. Nach Sensorabgleich wird die vorhandene Alarm-Session fortgeführt; Panik-/Videozustand wird deterministisch wieder angefordert.
5. Danach normal quittieren und Wieder-scharf-Ablauf prüfen.

## T09 – E-Mail-Quittierung

1. E-Mail-Versand und `Quittieren-Button in Alarm-E-Mail` aktivieren.
2. Gültige von außen erreichbare HTTPS-Basis-URL eintragen.
3. Alarm auslösen.
4. E-Mail öffnen und `Alarm quittieren` drücken.
5. Erwartung: erste Seite zeigt nur `Alarm quittieren?`; Alarm bleibt aktiv.
6. Erst `Alarm jetzt quittieren` drücken.
7. Erwartung: aktuelle Session wird quittiert; Licht/Video werden wie bei GT8-Quittierung beendet/wiederhergestellt; Hauptschalter bleibt EIN.
8. Den alten Link erneut öffnen. Erwartung: Link ist ungültig/verbraucht.

## T10 – automatisches Alarmende / Wieder scharf

1. Alarm auslösen, nicht quittieren.
2. Alle GUS frei werden lassen und Alarm-Nachlauf abwarten.
3. Erwartung: Licht-/Videozustände werden sauber beendet/wiederhergestellt.
4. `Wieder scharf in …` abwarten.
5. Erwartung: erneute Scharfschaltung nur bei freien und frisch bekannten Meldern.

## T11 – Testmodule entfernen

Erst nach erfolgreichem T01-T10:

1. `Samsung Alarmvideo Test` löschen.
2. alten `Samsung Alarmvideo MediaServer Helper` des Testmoduls löschen.
3. dessen alten Server Socket löschen, sofern er ausschließlich zum Testmodul gehört.
4. **Nicht löschen:** `LCN Alarmanlage MediaServer (intern)` und `LCN Alarmanlage Video HTTP`.
5. Danach T01 und T02 nochmals ohne Testmodule durchführen.

## Rollback

Bei einem Fehler den vollständigen Repository-Inhalt wieder durch Version 0.1.14 ersetzen und die Bibliothek aktualisieren. GUIDs, Prefix und alle 0.1.14-Properties/Variablen-Idents sind in 0.1.15 unverändert. Die zwei neuen E-Mail-Properties und die neuen internen Attribute werden von 0.1.14 nicht benötigt.
