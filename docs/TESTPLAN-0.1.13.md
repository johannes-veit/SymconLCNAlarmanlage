# Testplan 0.1.13

1. TV vor Alarm EIN: Alarm startet Video; Alarmende stoppt Video; TV bleibt EIN.
2. TV vor Alarm AUS und lange genug aus: WOL sofort + optionaler Retry nach 5 s; Video startet.
3. Alarm endet, bevor TV als EIN erkannt wurde: keine spätere TV-Off-Schleife; manuelles Einschalten wird nicht abgeschaltet.
4. Vom Alarm gestarteter TV ist bei Alarmende EIN: genau ein KEY_POWER AUS; nach 10 s nur Statusprüfung, kein zweites KEY_POWER.
5. Während „Wieder scharf in …“ darf kein TV-Aus-Befehl erzeugt werden.
6. Neue Alarm-Session stoppt eine noch ausstehende reine TV-Nachkontrolle.
7. HTML-Kachel enthält weiterhin die `<details>`-Bereiche „Überwachte Räume“ und „Protokoll“.
8. Alarmkern-/GUS-/Panik-/Push-/SMTP-Funktionen gegenüber 0.1.12 nicht ändern.
