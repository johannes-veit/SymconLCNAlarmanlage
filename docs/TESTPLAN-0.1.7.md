# Testplan 0.1.7 – Samsung-TV entkoppelt

## Voraussetzungen

- bestehende, getestete Alarmkonfiguration aus 0.1.4
- SamsungTizen-Instanz funktioniert manuell
- direkte Funktion „Aufwecken“ schaltet den TV zuverlässig ein
- TV-Statusvariable meldet EIN/AUS korrekt
- Alarmdauer für TV-Test mindestens 90 s

## T01 Regression Alarmkern ohne TV

TV-Funktion AUS. GUS, Paniklicht, Quittierung, Push, E-Mail und Countdown wie in 0.1.4 testen. Es darf keine Veränderung geben.

## T02 TV AUS vor Alarm

TV vollständig AUS, TV-Funktion EIN, Alarm auslösen. Erwartung: WakeUp sofort; TV startet ohne Abhängigkeit vom Countdown. Weitere GUS erzeugen keinen neuen Startauftrag.

## T03 TV bereits EIN vor Alarm

TV EIN, Alarm auslösen und quittieren/Timeout abwarten. Erwartung: TV bleibt EIN.

## T04 Quittierung kurz nach Alarmstart

TV AUS, Alarm auslösen, nach 2–3 s quittieren. Erwartung: ausstehender Wake-Retry wird gestoppt. Falls der erste WOL den TV noch einschaltet, wird er im Nachlauf wieder ausgeschaltet. Hauptschalter der Alarmanlage bleibt EIN.

## T05 Automatisches Alarmende

TV AUS, Alarm auslösen, Alarm automatisch enden lassen. Erwartung: TV wird nur dann ausgeschaltet, wenn er vom Alarm gestartet wurde. Danach Wieder-scharf-Logik unverändert.

## T06 Neue Session während TV-AUS-Nachlauf

Nach Alarmende vor Abschluss der TV-Nachkontrolle erneut Alarm erzeugen. Erwartung: alter AUS-Auftrag stoppt; neue Session hat Vorrang und der TV bleibt für den neuen Alarm verfügbar.

## T07 Vollständiges Ausschalten

Während aktivem Alarm Hauptschalter Alarmanlage auf AUS. Erwartung: Alarmkern wird sauber unscharf; TV-Helfer darf den Hauptschalter anschließend niemals wieder auf EIN setzen.

## T08 Update/ApplyChanges

TV AUS und kein Alarm. Modul übernehmen/aktualisieren. Erwartung: keinerlei WakeUp/KEY_POWER allein durch ApplyChanges.
