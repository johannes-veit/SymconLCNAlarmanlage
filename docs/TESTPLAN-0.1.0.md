# Testplan 0.1.0

## A. Konfiguration

- Mindestens zwei bereits getestete LCN-GUS-Binärstatus-Booleanvariablen auswählen.
- Eindeutige Bezeichnungen vergeben.
- Automatik zunächst AUS lassen.
- Prüfen, dass im Normalbetrieb kein zyklischer LCN-Traffic durch das Modul erzeugt wird.

## B. Scharfschaltung

- Alle GUS frei: EIN -> `ALARMANLAGE SCHARF`.
- Ein GUS bereits aktiv: EIN -> `SCHARFSCHALTUNG – warte auf freie Melder`; erst nach Freiwerden aller GUS `ALARMANLAGE SCHARF`.
- Ein bereits aktiver GUS beim Scharfschalten darf **keinen** Alarm erzeugen.

## C. Einzelmelder

- GUS 1: AUS -> AN -> Alarm genau einmal.
- AN -> AN-Update darf keinen zweiten Bewegungseintrag erzeugen.
- AN -> AUS -> AN muss eine neue Bewegung desselben Melders protokollieren.
- Bewegungsende muss als `frei` im Profil erscheinen.

## D. Mehrere Melder / Kollisionsprüfung

- GUS 1 und GUS 2 nahezu gleichzeitig auslösen.
- Beide Bewegungen müssen protokolliert werden.
- Nur eine Alarm-Session darf existieren.
- Schnellfolge GUS 1 -> GUS 2 -> GUS 1 prüfen.
- Wenn möglich drei oder mehr GUS in kurzer Folge auslösen.
- Während weiterer GUS-Meldungen `Alarm deaktivieren` betätigen: nach der Quittierung darf keine verspätete lokale Alarmaktivierung wieder erscheinen.

## E. Quittierung

- Während Alarm `Alarm deaktivieren` betätigen.
- `AlarmActive` AUS, Anlage AUS, Alarmtimer beendet.
- Bewegungsprofil muss als letzter Alarm erhalten bleiben.
- Alternativ den normalen Alarmanlage-EIN/AUS-Schalter auf AUS stellen; Ergebnis muss ebenfalls sicher AUS sein.

## F. Automatisches Ende

- Testweise Alarmdauer z. B. 20 s einstellen.
- Alarm auslösen und nicht quittieren.
- Nach Ablauf `AlarmActive` AUS.
- Alarmanlage bleibt grundsätzlich EIN/SCHARF.
- Solange noch ein GUS aktiv ist: keine Wiederbereitschaft.
- Nach allen GUS AUS + Wiederbereitschaftszeit: Status wieder `ALARMANLAGE SCHARF`.
- Während der Wiederbereitschaft erneut Bewegung auslösen: Ruhezeit muss neu beginnen, kein neuer Alarm darf sofort starten.

## G. Automatik

- Automatik mit kurzfristig erreichbaren Testzeiten einschalten.
- Scharf- und Unscharf-Zeitgrenze jeweils prüfen.
- Innerhalb eines Automatikfensters manuell AUS stellen: Anlage muss bis zur nächsten Zeitgrenze manuell übersteuert bleiben.
- Außerhalb des Automatikfensters manuell EIN stellen: ebenfalls bis zur nächsten Zeitgrenze übersteuern.
- Automatik anschließend wieder auf die gewünschten Zeiten einstellen.

## H. Neustart / ApplyChanges

- Anlage scharf, kein Alarm: `ApplyChanges` bzw. Symcon-Neustart; vorhandener aktiver Melder darf beim Initialisieren keinen historischen Alarm erzeugen.
- Laufender Alarm: `ApplyChanges`/Neustart testen; vorhandene Session muss rekonstruiert werden, ohne zweite Session oder zweiten Bewegungseintrag zu erzeugen.
- Der verbleibende Alarmtimer muss wieder aufgebaut werden.

## I. Fehlerfälle

- Testweise eine konfigurierte Sensorvariable entfernen/ungültig machen (nur wenn gefahrlos möglich).
- Modul muss auf Störung gehen und darf sich nicht scharf schalten lassen.
- Nach Wiederherstellung der gültigen Konfiguration und `Übernehmen` muss die Anlage wieder sauber initialisieren.
