# Testplan 0.1.6

## Regression Alarmkern
- GUS-Auslösung, Mehrfach-GUS, Bewegungsprofil wie 0.1.4/0.1.5.
- Panik, Quittierung, Countdown, Push und E-Mail unverändert testen.

## Samsung-TV
1. TV AUS, Alarm starten: PowerFix sendet sofort den ersten WOL.
2. Bleibt Status AUS, nach ca. 5 s zweiter WOL; nach ca. 10 s maximal dritter WOL.
3. Weitere GUS derselben Session erzeugen keine zusätzliche WOL-Serie.
4. Alarm vor TV-Start quittieren: ab Quittierung keine weiteren WOL-Versuche.
5. Fährt der vom Alarm gestartete TV danach verspätet hoch, wird er erkannt und wieder ausgeschaltet.
6. TV vor Alarm bereits EIN: kein WOL; nach Alarmende TV nicht ausschalten.
7. TV durch Alarm gestartet: bei Alarmende AUS anfordern; nach 10 s Status erneut prüfen und begrenzt nachführen.
8. Neue Alarm-Session darf einen alten Abschaltauftrag überstimmen.

## Update
- Bestehende Instanz 0.1.5 aktualisieren.
- Alle Sensor-, Panik-, Push-, E-Mail- und TV-Zuordnungen müssen erhalten bleiben.
- ApplyChanges im Ruhezustand darf keinen Hardwarebefehl senden.
