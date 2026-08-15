# Changelog

## 0.1.6

- Samsung-TV-Start robuster: erster Start weiterhin über PowerFix, danach bei aktivem Alarm maximal zwei zusätzliche native Wake-on-LAN-Versuche nach 5 s und 10 s.
- Keine weiteren Wake-Versuche nach Quittierung oder automatischem Alarmende.
- TV wird nur dann nach Alarmende ausgeschaltet, wenn er vor dem Alarm AUS war bzw. von der Alarmanlage übernommen wurde.
- Bereits vor dem Alarm eingeschalteter TV bleibt nach Alarmende eingeschaltet.
- Spät hochfahrender, vom Alarm gestarteter TV wird weiterhin nach Alarmende überwacht und wieder ausgeschaltet.
- Bestehende GUIDs, Prefixe, Properties und Konfigurationen bleiben unverändert.

## 0.1.5

- Samsung-TV optional über bestehende PowerFix-Variablen angebunden; keine direkte Abhängigkeit von einer Samsung-Modul-GUID.
- Alarmstart: TV nur bei bestätigtem AUS über den PowerFix-Ein/Aus-Impulsbutton einschalten.
- Alarmende/Quittierung/Unscharfschalten während aktivem Alarm: TV AUS anfordern.
- Nach Alarmende 10-s-Nachkontrolle bis maximal 60 s; verspätetes Hochfahren nach WOL wird abgefangen.
- Danach höchstens ein letzter OFF-Impuls plus Abschlusskontrolle; keine Endlosschleife.
- Neue Alarm-Session überstimmt einen noch laufenden alten TV-AUS-Auftrag.
- TV-Steuerung ist optional und nach Update standardmäßig AUS. Fehler am TV blockieren den Alarmkern nicht.
- Push-Auswahl wird dynamisch auf echte Kachelvisualisierungen eingeschränkt und zusätzlich zur Laufzeit validiert.
- Alarmbildschirm/Sirene auf dem TV noch nicht Bestandteil dieser Version.

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
