# Testplan 0.1.5 – Samsung-TV Power

## Update / Regression

1. Bestehende 0.1.4-Instanz auf 0.1.5 aktualisieren.
2. GUS-, Panik-, Push-, SMTP- und Zeitkonfiguration müssen erhalten bleiben.
3. TV-Funktion muss nach Update zunächst AUS sein.
4. Ohne aktivierte TV-Funktion darf ApplyChanges keinen TV-Befehl erzeugen.
5. Push-Auswahl darf nur echte Kachelvisualisierungen akzeptieren.

## TV-Konfiguration

1. `Samsung-TV – Status`: reale Boolean-Statusvariable der Samsung-Tizen-Instanz auswählen.
2. `Samsung-TV – Ein/Aus Impulsbutton (PowerFix)`: den von PowerFix erzeugten Integer-Button `Ein/Aus` auswählen.
3. TV-Funktion aktivieren und Übernehmen.

## T01 – Alarm bei ausgeschaltetem TV

- TV bestätigt AUS.
- Anlage scharf, GUS auslösen.
- Genau ein PowerFix-EIN-Impuls.
- TV startet.
- Weitere GUS derselben Session dürfen keinen weiteren EIN-Impuls erzeugen.

## T02 – Alarm bei bereits eingeschaltetem TV

- TV bestätigt EIN.
- GUS auslösen.
- Kein Power-Impuls beim Alarmstart.
- Alarmfunktionen bleiben normal aktiv.

## T03 – Quittierung

- Alarm bei TV EIN.
- `Alarm deaktivieren` drücken oder Panik-Lichttaster verwenden.
- Alarmanlage EIN/AUS bleibt EIN.
- TV erhält AUS-Anforderung.
- Nach ca. 10 s lokale Statuskontrolle.
- TV bleibt AUS; Wieder-scharf-Countdown unverändert.

## T04 – Sehr schnelle Quittierung während TV noch startet

- TV AUS, Alarm auslösen und innerhalb weniger Sekunden quittieren.
- Ein PowerFix-Start kann noch laufen.
- Alarmmodul überwacht danach bis 60 s.
- Falls TV verspätet auf EIN springt, wird AUS angefordert.
- Kein dauerhafter eingeschalteter TV nach Alarmende.

## T05 – Automatisches Alarmende

- Nicht quittieren; Alarmtimeout abwarten.
- TV-AUS-Pfad muss identisch starten.
- Alarmanlage bleibt EIN und geht nach freiem Melderfeld/Countdown wieder scharf.

## T06 – Neue Alarm-Session während alter TV-Nachlauf noch aktiv

- Nach beendetem Alarm läuft TV-AUS-Nachkontrolle.
- Neue Alarm-Session erzeugen, sobald Anlage wieder scharf ist.
- Alter AUS-Auftrag darf den TV der neuen Session nicht ausschalten.

## T07 – TV nicht erreichbar

- TV-/PowerFix-Aktion provoziert Fehler bzw. Status bleibt falsch.
- Alarmkern, Panik, Push und E-Mail müssen weiterarbeiten.
- Nach begrenztem Prüfzeitraum nur Logeintrag; keine Endlosschleife.

## T08 – Neustart

- Neustart während aktivem Alarm: TV EIN wird anhand derselben aktiven Session rekonstruiert.
- Neustart während `rearm_wait`: begrenzte TV-AUS-Überwachung wird neu gestartet.
- Neustart ohne Alarm-Session darf einen normal eingeschalteten TV nicht ausschalten.
