# Changelog

## 0.1.8

- v0.1.7 nochmals geprüft und TV-Pfad weiter abgesichert; Grundlage bleibt der stabile 0.1.4-Alarmkern
- direkter `SamsungTizen_WakeUp()` wird beim ersten Alarmtrigger jetzt vor Paniklicht und Benachrichtigungs-Queue aufgerufen
- SamsungTizen-Instanz wird technisch gegen die echte Modul-GUID geprüft; Statusvariable muss direkt zu dieser Instanz gehören
- Push-Auswahl wieder auf echte VISU-Instanzen eingeschränkt
- alte TV-Ownership wird bei ApplyChanges ohne aktive Alarm-Session sicher verworfen
- aktive Alarm-Session rekonstruiert nach Symcon-Neustart auch den TV-Helfer
- jeder konfigurierte GUS erhält eine eigene persistente Boolean-Statusvariable in der Visualisierung
- Bezeichnung der GUS-Schalter wird direkt aus der Modulkonfiguration übernommen
- GUS können in der Visualisierung einzeln EIN/AUS geschaltet werden, ohne die LCN-Konfiguration zu verändern
- ausgeschaltete GUS bleiben technisch registriert, lösen aber keinen Alarm aus und werden nicht in neue Bewegungsereignisse aufgenommen
- Zuschalten eines aktuell aktiven GUS erzeugt keinen Sofortalarm; die Anlage wartet zunächst auf einen freien Zustand
- Deaktivieren eines GUS während eines laufenden Alarms beendet die aktuelle Alarm-Session nicht
- wenn alle GUS deaktiviert sind, zeigt die Visualisierung ausdrücklich `ALARMANLAGE EIN – keine GUS aktiv` statt fälschlich `SCHARF`
- GUIDs, Prefix, bestehende Properties und bestehende Variablen-Idents bleiben unverändert

## 0.1.7

- basiert direkt auf der stabilen real getesteten 0.1.4; TV-Code aus 0.1.5/0.1.6 wurde vollständig verworfen
- Samsung-TV optional und nach Update standardmäßig AUS
- TV EIN direkt über `SamsungTizen_WakeUp()` unmittelbar beim ersten Alarmtrigger
- genau ein begrenzter Wake-Retry nach 5 s, falls die vorhandene TV-Statusvariable weiterhin AUS meldet
- PowerFix-Impulsvariable wird nicht mehr verwendet
- TV war vor Alarm bereits EIN: bleibt nach Alarmende EIN
- TV wurde vom Alarm gestartet: bei Alarmende `KEY_POWER` und lokale Nachkontrolle im 10-s-Abstand
- spät hochfahrender, vom Alarm gestarteter TV wird durch begrenzten Nachlauf wieder ausgeschaltet
- neue Alarm-Session stoppt einen alten TV-AUS-Nachlauf und hat Vorrang
- TV-Helfer ändern niemals Alarmanlagen-Hauptschalter, Alarm-Session, Automatik oder Wieder-scharf-Countdown
- Samsung-Fehler werden nur protokolliert; der Alarmkern bleibt betriebsfähig
- keine Hardwareaktion allein durch Update/ApplyChanges
- GUIDs, Prefix und sämtliche 0.1.4-Properties/Idents bleiben erhalten

## 0.1.4

- stabiler Alarmkern 0.1.3 unverändert fortgeführt
- optionale Push-Nachricht über eine explizit ausgewählte Kachelvisualisierung
- Push-Titel `ALARM AUSGELÖST!`, Sirenenton und direkter Sprung zur Alarmanlagen-Kachel
- optionale E-Mail über eine explizit ausgewählte SMTP-Instanz
- mehrere Empfänger per Semikolon, Komma oder Zeilenumbruch; jede Adresse wird einzeln mit `SMTP_SendMailEx()` bedient
- Push und E-Mail werden pro Alarm-Session ausschließlich beim ersten GUS-Trigger eingeplant
- Benachrichtigungen laufen außerhalb der Alarm-Engine-Semaphore in einer kurzen lokalen Warteschlange; langsames SMTP kann den Alarmkern nicht blockieren
- nach Kernel-/Modulneustart wird eine verlorene Benachrichtigungswarteschlange bewusst nicht rekonstruiert, damit ein bereits versendeter Alarm nicht doppelt zugestellt wird
- Push/E-Mail sind nach dem Update standardmäßig AUS und müssen nach Auswahl der Symcon-Instanzen explizit aktiviert werden
- Benachrichtigungsfehler legen den Alarmkern nicht still, sondern werden protokolliert
- persönliche E-Mail-Adressen werden nicht im Repository hinterlegt, sondern ausschließlich in der lokalen Instanzkonfiguration gespeichert
- GUIDs, Prefix, bestehende Properties und Variablen-Idents bleiben unverändert

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
