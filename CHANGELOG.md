# Changelog

## 0.1.3

- Korrigiert die Panik-Ansteuerung aus 0.1.2: Die sichtbare Integer-Statusvariable von `LCNLightGroup` ist eine read-only Rückmeldung und besitzt keine VariableAction.
- Die Panik-Lichtgruppe wird deshalb nicht mehr über deren Statusvariable geschaltet.
- Stattdessen werden die bereits ausgewählten `LCN Licht -> Status`-Booleanvariablen verwendet. Nur Leuchten mit abweichendem Istzustand erhalten einen Befehl.
- Zwischen tatsächlich notwendigen Lichtbefehlen liegen 100 ms; der Timer ist nur während der kurzen Befehlsserie aktiv und erzeugt kein Polling.
- EIN-Aufträge einer inzwischen quittierten Session werden abgebrochen; alte AUS-Aufträge dürfen keine neue aktive Session ausschalten.
- Die optionale Gruppen-Statusvariable bleibt als reine Kontrollreferenz erhalten.
- GUIDs, Prefix und bestehende Property-Namen bleiben unverändert; vorhandene 0.1.2-Konfigurationen werden übernommen.

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
