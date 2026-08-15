# Testplan 0.1.8

## A. Update / Rollback

1. Bestehende 0.1.4/0.1.7-Instanz aktualisieren, keine neue Instanz anlegen.
2. GUS-, Panik-, Push-, SMTP- und Zeitkonfiguration muss erhalten bleiben.
3. Neue GUS-Schalter müssen für alle konfigurierten GUS erscheinen und initial EIN sein.
4. Update/ApplyChanges ohne aktiven Alarm darf keinen LCN-, TV-, Push- oder Mail-Befehl auslösen.

## B. GUS-Schalter

1. Anlage AUS: jeden GUS-Schalter einzeln EIN/AUS, Zustand muss persistent bleiben.
2. Anlage EIN, alle GUS frei: einen GUS AUS, nur dieser darf danach keinen Alarm auslösen.
3. Anderer GUS bleibt EIN und muss weiterhin Alarm auslösen.
4. GUS während Bewegung AUS und anschließend wieder EIN: kein Sofortalarm; Status wartet bis GUS frei ist.
5. Alle GUS AUS: Status muss `ALARMANLAGE EIN – keine GUS aktiv` zeigen und kein GUS darf auslösen.
6. Einen freien GUS wieder EIN: Anlage wird wieder scharf.
7. Während aktivem Alarm einen GUS AUS: laufender Alarm darf dadurch nicht beendet werden.
8. Während rearm_wait einen aktiven GUS AUS: Wieder-scharf-Logik muss nur noch aktive GUS berücksichtigen.

## C. Parallelität

1. Zwei oder mehr aktive GUS nahezu gleichzeitig auslösen.
2. Nur eine Alarm-Session; alle aktiven GUS-Ereignisse im Bewegungsprofil.
3. Ein deaktivierter GUS darf parallel beliebig wechseln, ohne Alarm/Profil zu beeinflussen.

## D. Samsung-TV

1. Alarmdauer für den Test >= 90 s.
2. TV AUS, Alarm auslösen: `SamsungTizen_WakeUp()` muss im ersten Alarmtrigger vor Panik/Benachrichtigung ausgeführt werden.
3. TV muss so schnell starten wie beim manuellen Aufwecken der SamsungTizen-Instanz.
4. TV nach 5 s weiterhin AUS: maximal ein zweiter WakeUp.
5. TV vor Alarm EIN: nach Alarmende EIN lassen.
6. TV durch Alarm gestartet: bei Quittierung/Timeout AUS, 10-s-Nachkontrolle begrenzt.
7. Quittierung unmittelbar nach Alarmstart: kein späterer Wake-Retry; verspätet hochfahrender Alarm-TV wird wieder ausgeschaltet.
8. Samsung-Fehler dürfen Arm, AlarmActive, CurrentSession, Automatik und Rearm nicht verändern.

## E. Regression 0.1.4

- Paniklicht EIN/AUS
- Quittierung über Panik-Lichttaster
- Push genau einmal pro Session
- E-Mail genau einmal je Empfänger und Session
- automatisches Alarmende
- Countdown / Wieder-scharf
- manuelles Alarm deaktivieren lässt Hauptschalter EIN
- kein LCN-Polling
