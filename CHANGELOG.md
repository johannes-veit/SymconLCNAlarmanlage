# Changelog

## 0.1.2

- stabile 0.1.1-Alarmkernlogik unverändert fortgeführt
- optionale Panik-Lichtgruppe über deren Integer-Statusvariable integrierbar
- Alarmbeginn fordert die Gruppe deterministisch mit Zielwert `1 = EIN` an
- Quittierung, automatisches Alarmende und vollständiges Ausschalten fordern `0 = AUS` an
- Panikbefehle laufen außerhalb der Alarm-Engine-Semaphore; Session-ID wird vor/nach PANIK EIN geprüft
- mehrere GUS starten weiterhin nur eine Alarm-Session und damit nur einen PANIK-EIN-Ablauf
- optionale Quittier-Lichter als Boolean-Statusvariablen auswählbar
- nur echte `EIN -> AUS`-Flanke eines Quittier-Lichts während `ALARM AKTIV` quittiert
- PANIK EIN (`AUS -> EIN`) kann den Alarm nicht selbst quittieren
- PANIK AUS erfolgt erst nach Verriegelung der Session auf `rearm_wait` und kann deshalb ebenfalls keine Selbstquittierung erzeugen
- kein Polling und keine zusätzlichen LCN-Ressourcen
- Bibliotheks-GUID, Modul-GUID, Prefix und alle 0.1.1-Properties/Idents bleiben erhalten

## 0.1.1

- „Alarm deaktivieren“ beendet nur die aktuelle Alarm-Session; die Anlage bleibt EIN
- sichtbarer Countdown „Wieder scharf in …“
- bei aktiven GUS: „Warte auf freie Bewegungsmelder“
- neue Bewegung während Countdown stoppt diesen und startet ihn nach erneuter Freimeldung neu
- automatisches Alarmende nutzt denselben Wieder-Scharf-Ablauf
