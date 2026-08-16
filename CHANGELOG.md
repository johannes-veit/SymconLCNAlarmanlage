# Changelog

## 0.1.12

- Vollständiges Direktupdate auf Basis der stabil getesteten Alarmanlage 0.1.11; Bibliotheks-GUID, Hauptmodul-GUID, Prefix, Properties und bestehende Variablen-Idents bleiben erhalten.
- Der real vollständig getestete Samsung-Alarmvideo-Pfad aus `Samsung Alarmvideo Test 0.2.6` ist jetzt direkt in die Alarmanlage integriert.
- Alarmstart: TV bei Bedarf per `SamsungTizen_WakeUp()` wecken, einmaliger Wake-Retry nach 5 s, Alarmvideo nach 4 s starten, begrenzte Videostart-Retries, MPEG/MP4-Fallback und bestätigte Endlosschleife über fortlaufende `SetNextAVTransportURI`-Nachladung.
- Alarmende, Quittierung und vollständiges Ausschalten: Alarmvideo wird wie ein weiteres Paniklicht sofort per UPnP `Stop` beendet. War der TV vor Alarm EIN, bleibt er EIN; wurde er vom Alarm gestartet, wird er danach wie bisher per `KEY_POWER` ausgeschaltet und nachkontrolliert.
- Der in 0.2.6 getestete DLNA-Medienserver ist als interne Alarmanlagen-Hilfsinstanz integriert; `ALARM.mp4` und `ALARM_DLNA.mpeg` sind Bestandteil derselben Bibliothek.
- Der Medienserver wird einmalig automatisch vorbereitet und danach wiederverwendet. Beim Alarmtrigger gibt es einen schnellen Pfad ohne erneutes `ApplyChanges()`/Socket-Neustart.
- Für eine sichere Migration kann das alte Testmodul parallel installiert bleiben: ist Port 8090 noch durch dessen Server Socket belegt, wählt die Alarmanlage automatisch den nächsten freien Port bis +20 und verwendet diesen dauerhaft.
- Neue Konfigurationswerte: TV-IP, SymBox-IP, Medienserver-Wunschport und Video-Startverzögerung; Defaults entsprechen dem real getesteten Aufbau (`192.168.103.54`, `192.168.103.59`, `8090`, `4000 ms`).
- Die gesamte TV-/Videofunktion bleibt über `TVEnabled` optional. Fehler des optionalen Videozweigs werden protokolliert und dürfen Alarmkern, GUS, Paniklicht, Quittierung, Push, E-Mail, Nachlauf oder Wieder-scharf-Logik nicht stilllegen.
- Keine Änderung an der eigentlichen Alarmkernlogik aus 0.1.11.

## 0.1.11

- Vollständiges Direktupdate auf Basis von 0.1.10; ein vorheriges Installieren von 0.1.10 ist nicht erforderlich.
- Enthält den JSON-Kompatibilitätsfix aus 0.1.10 für `UpdateVisualizationValue()` und behebt damit den v0.1.9-RPC-Typfehler bei ApplyChanges sowie bei Kachelaktionen wie EIN→AUS.
- Kompakte HTML-Kachel optisch an die vorhandenen Symcon-Module angepasst: Poppins/Segoe-UI-Schriftstack, kompaktere Abstände, keine zusätzliche eigene Kachelüberschrift.
- Unter **Überwachte Räume** zeigt der Punkt jetzt ausschließlich den Überwachungszustand: grün = GUS aktiv, grau = GUS deaktiviert.
- Technische Alarm-Endgründe wie `acknowledged` werden nicht mehr bei **Letzter Alarm** angezeigt; sie bleiben intern in der Sessionhistorie erhalten.
- Aufbau mit **Überwachte Räume**, **Protokoll** und scrollbar begrenzter **Historie** bleibt unverändert.
- Keine Änderung an Alarm-Nachlauf, GUS-Auswertung, Paniklicht, Quittierung, Push, E-Mail, Samsung-TV oder Wieder-scharf-Logik.

## 0.1.10

- Reiner Kompatibilitätsfix für die HTML-SDK-Kachel in Symcon 9.
- `UpdateVisualizationValue()` erhält den komplexen Zustand jetzt JSON-codiert statt als PHP-Array.
- Behebt `Cannot auto-convert value for parameter Value (Type is not supported)` beim Übernehmen von Instanzeigenschaften.
- Keine Änderung an Alarm-Nachlauf, GUS, Paniklicht, Quittierung, Push, E-Mail oder Samsung-TV.

## 0.1.9

- **Alarmdauer ist jetzt Nachlaufzeit:** kein Alarm-Endtimer mehr ab dem ersten Trigger.
- Die Nachlaufzeit beginnt erst, wenn alle aktuell überwachten GUS `AUS/frei` melden.
- Jede neue Bewegung während des Nachlaufs bricht ihn sofort ab; bei erneut vollständig freiem Melderfeld startet die volle Nachlaufzeit neu.
- Gleichzeitige und wechselnde GUS bleiben vollständig kollisionsgeschützt und werden weiter chronologisch protokolliert.
- Neustart/ApplyChanges rekonstruiert eine bereits laufende Nachlauf-Deadline persistent; bei aktiver Bewegung gibt es keinen Alarm-Endtimer.
- Kompakte Kachel via offiziellem Symcon HTML-SDK: **Überwachte Räume** und **Protokoll** sind einklappbar.
- Unter **Überwachte Räume** stehen die konfigurierten GUS mit eigenem EIN/AUS-Schalter; die vorhandenen nativen Schalter bleiben als Fallback erhalten.
- **Protokoll** enthält Erstauslöser, letzte Bewegung, Anzahl Bewegungen und letzten Alarm.
- **Historie** zeigt die Bewegungsereignisse des aktuellen/letzten Alarms chronologisch in einem begrenzten, scrollbareren Bereich.
- Der Alarm-Nachlauf wird in der Kachel lokal sekundengenau als `Nachlauf noch ... s` angezeigt, ohne zusätzlichen LCN-Traffic.
- Samsung-, Panik-, Push-, E-Mail-, Automatik- und Quittierungslogik aus 0.1.8 bleiben funktional unverändert.

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

## 0.1.13
- WOL-Pfad bleibt unverändert: sofort `SamsungTizen_WakeUp()`, einmaliger Retry nach 5 s.
- Fehler in der TV-Nachlaufsteuerung behoben: `TVOffCheck` sendet keine wiederholten `KEY_POWER`-Befehle mehr.
- War der TV beim Alarmende noch nicht als EIN bestätigt, wird kein später Abschaltauftrag hinterlassen.
- Wurde der vom Alarm gestartete TV bereits als EIN bestätigt, erfolgt beim Alarmende maximal ein unmittelbarer AUS-Befehl; nach 10 s wird nur noch der Status kontrolliert.
- Dadurch kann die Phase „Wieder scharf in …“ keinen später manuell eingeschalteten TV mehr abschalten.
- HTML-Kachel bleibt funktional identisch zu 0.1.11; Datei erhält lediglich einen Versionsmarker, um einen Frontend-Cache sicher zu aktualisieren.
