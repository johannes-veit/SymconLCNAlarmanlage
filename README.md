# LCN Alarmanlage – Version 0.1.1

Kern-Testversion für IP-Symcon 9.0. Version 0.1.1 ist ein gezieltes, rollbackfähiges Update auf 0.1.0. GUIDs, Prefix, Property-Namen und bestehende Sensorzuordnungen bleiben erhalten.

## Enthalten

- native LCN-GUS-Binärstatus-Booleanvariablen als Alarmquellen
- vollständig ereignisgesteuert über `VM_UPDATE`
- kein LCN-Polling und kein `LCN_RequestStatus()` im Normalbetrieb
- Flankenerkennung je GUS
- zentrale Alarm-Session mit Semaphore-Kollisionsschutz
- gleichzeitige Meldungen beliebig vieler GUS
- Bewegungsprofil mit Reihenfolge und Millisekunden-Zeitstempeln
- manuell EIN/AUS
- Zeitautomatik mit manueller Übersteuerung bis zur nächsten Zeitgrenze
- automatisches Ende der Alarmsignalisierung nach konfigurierbarer Zeit
- Quittierung beendet ausschließlich den aktuellen Alarm; die Anlage bleibt EIN
- nach Alarmende: erst freie Melder, danach Countdown `Wieder scharf in N s`
- persistente aktuelle/letzte Alarm-Session und Wiederanlauf nach Symcon-Neustart
- sicheres Abschalten bei Verlust einer konfigurierten Sensorvariable

Noch **nicht** enthalten: Paniklicht, LCN-Quittierung über GT8/GT2, Push, E-Mail, Samsung-TV und TV-Alarm-App.

## Update von 0.1.0

Repository-Ordner vollständig durch Version 0.1.1 ersetzen und das Modul in Symcon aktualisieren. Die bestehende Instanz bleibt erhalten; die drei konfigurierten GUS sowie Alarmdauer, Wieder-Scharf-Verzögerung und Automatikwerte werden nicht umbenannt oder migriert.

## Verhalten von `Alarm deaktivieren`

`Alarm deaktivieren` ist **keine Unscharfschaltung**. Ablauf:

1. aktive Alarm-Session wird quittiert und gegen neue Trigger verriegelt
2. `ALARM AUSGELÖST` wird beendet
3. Hauptschalter `Alarmanlage EIN/AUS` bleibt EIN
4. solange ein GUS aktiv ist: `Warte auf freie Bewegungsmelder`
5. sobald alle GUS frei sind, startet die konfigurierte Verzögerung
6. Status zählt lokal in Symcon: `Wieder scharf in 60 s`, `59 s`, ...
7. danach wieder `ALARMANLAGE SCHARF`

Nur der Hauptschalter `Alarmanlage EIN/AUS` schaltet die gesamte Anlage unscharf.

## Traffic

Der neue Countdown-Timer läuft nur während der kurzen Wieder-Scharf-Phase einmal pro Sekunde und aktualisiert ausschließlich die lokale Symcon-Statusvariable. Er fragt weder LCN noch GUS ab und erzeugt daher keinen LCN-Busverkehr.

## Test

Siehe `docs/TESTPLAN-0.1.1.md`. Vor Erweiterungen um Paniklicht, Push, E-Mail oder Samsung-TV sollen insbesondere Quittierung, Countdown, Mehrfach-GUS und Neustartverhalten getestet werden.
