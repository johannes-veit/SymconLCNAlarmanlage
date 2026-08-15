# Testplan 0.1.1

Version 0.1.1 ändert bewusst nur Quittierung und Wieder-Scharf-Anzeige. Die bereits erfolgreich getestete native GUS-Eingangslogik bleibt unverändert.

## A. Updatepfad

- Bestehende 0.1.0-Instanz aktualisieren, nicht neu anlegen.
- Prüfen: gleiche Instanz-ID, drei GUS unverändert vorhanden, Bezeichnungen unverändert.
- Alarmdauer und Wieder-Scharf-Verzögerung müssen erhalten bleiben.
- Automatikzeiten und aktueller Automatikschalter müssen erhalten bleiben.
- Keine neue LCN-Instanz, kein virtuelles Relais, keine LED und keine LCN-Hilfsvariable.

## B. Manuelle Quittierung – Kernkorrektur

1. Alle GUS frei, Alarmanlage EIN.
2. GUS auslösen -> `ALARM AUSGELÖST`.
3. Während des Alarms `Alarm deaktivieren` betätigen.
4. Erwartung unmittelbar danach:
   - `Alarmanlage EIN/AUS` bleibt **EIN**.
   - `ALARM AUSGELÖST` wird AUS/ausgeblendet.
   - aktuelle Session startet **keinen** zweiten Alarm.
5. Ist noch mindestens ein GUS AN: Status `Warte auf freie Bewegungsmelder`.
6. Alle GUS freigeben: Countdown beginnt.
7. Status zählt `Wieder scharf in N s` bis 0/Ende.
8. Danach `ALARMANLAGE SCHARF`.

## C. Quittierung bei gleichzeitig mehreren GUS

- Zwei oder drei GUS gleichzeitig aktivieren.
- Während weitere Statuswechsel eintreffen quittieren.
- Anlage muss EIN bleiben.
- Kein verspätetes `ALARM AUSGELÖST` nach der Quittierung.
- Alle Bewegungen/Freimeldungen der bestehenden Session sollen weiter nachvollziehbar bleiben.
- Countdown darf erst beginnen, wenn **alle** konfigurierten GUS frei sind.

## D. Countdown-Abbruch durch neue Bewegung

- Alarm quittieren oder automatisch enden lassen.
- Alle GUS frei -> Countdown läuft.
- Während des Countdowns einen GUS erneut aktivieren.
- Erwartung: Countdown stoppt sofort, kein neuer Alarm, Status `Warte auf freie Bewegungsmelder`.
- GUS wieder frei -> Countdown startet vollständig neu mit der konfigurierten Zeit.

## E. Automatisches Alarmende

- Testdauer z. B. 20 s.
- Alarm nicht quittieren.
- Nach 20 s endet nur die Alarm-Signalisierungsphase; Hauptschalter bleibt EIN.
- Danach identischer Wieder-Scharf-Ablauf wie bei manueller Quittierung.
- Letzter Alarm muss als `automatic-timeout` abgeschlossen werden.

## F. Hauptschalter AUS

- Während eines laufenden Alarms Hauptschalter `Alarmanlage EIN/AUS` auf AUS.
- Erwartung: gesamte Anlage sofort unscharf, aktive Session beendet, Alarm- und Countdown-Timer aus.
- Es darf kein automatisches Wieder-Scharfschalten aus dieser Session erfolgen.

## G. Neustart / ApplyChanges

- Während `Warte auf freie Bewegungsmelder` ApplyChanges/Neustart: Zustand muss rekonstruiert werden, kein historischer Alarm.
- Während laufendem Countdown ApplyChanges/Neustart: verbleibende Zeit aus `RearmNotBefore` rekonstruieren, nicht von vorn beginnen.
- Nach Wiederanlauf kein LCN-Polling erzeugen.

## H. Traffic

- Im Debug/Busmonitor prüfen: der 1-s-Countdown verändert nur die lokale Statusanzeige.
- Während des Countdowns dürfen keine zusätzlichen LCN-Statusabfragen oder Kommandos durch das Alarmmodul entstehen.
