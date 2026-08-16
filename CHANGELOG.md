## 0.1.22

- Smartphone-Aufklappbereiche verwenden keinen verschachtelten nativen WebView-Scroll mehr
- eigener mobiler Scrollmechanismus für Überwachte Räume, Status Bewegungsmelder und Protokoll
- vertikales Wischen verschiebt den jeweiligen Kategorieninhalt direkt per Pointer-/Touch-Ereignis
- eigene sichtbare Scrollleiste mit ziehbarem Scrollgriff; Antippen der Leiste springt an die gewünschte Position
- Scrollgesten lösen keine Raum-/GUS-Schalter aus
- Scrollposition bleibt beim Auf-/Zuklappen und bei Live-Updates erhalten
- Fallback für ältere WebViews ohne Pointer Events über Touch Events
- Desktop-Darstellung bleibt beim bisherigen Verhalten; manueller Mobil-Scroller ist dort deaktiviert
- `LCNAlarmanlage/module.php`, Alarmkern, Sensorlogik, Quittierung, Lichtzustandswiederherstellung, Neustartschutz, E-Mail, WOL und Video gegenüber 0.1.21 unverändert
- keine zusätzlichen Symcon-Variablen, Timer, Properties oder LCN-Abfragen

## 0.1.21

- Smartphone-Schalter vollständig auf eigene touch-stabile Button-Switches umgestellt; keine versteckten nativen Checkboxen mehr
- optimistische Bedienung mit Pending-Guard: alte bzw. parallel eintreffende Visualisierungsmeldungen dürfen einen gerade gesetzten Schalter bis zur Modulbestätigung nicht zurückspringen lassen
- Pending-Zustände werden für maximal 5 s lokal zwischengespeichert, sodass auch ein kurzfristiger WebView-Rebuild den gerade gewählten Wert nicht verwirft
- nach jeder Bedienung erfolgt ein begrenzter rein lesender Zustandsabgleich; bei ausbleibender Bestätigung fällt die Anzeige nach spätestens 5 s auf den echten Modulzustand zurück
- Schalter Alarmanlage, Automatik und alle Raum-/GUS-Schalter verwenden denselben abgesicherten Bedienpfad
- Zeitfelder Scharf ab / Unscharf ab werden während eines geöffneten mobilen Zeitdialogs nicht mehr von asynchronen Statusmeldungen überschrieben; Change+Blur erzeugen keinen Doppelauftrag
- Scrollpositionen der drei Aufklappbereiche bleiben auch bei Listenaktualisierungen erhalten
- zusätzlicher Touch-Scroll-Fallback für mobile WebViews, falls verschachteltes CSS-Scrolling von der App abgefangen wird
- Alarmkern, Sensorlogik, Quittierung, Lichtzustandswiederherstellung, Neustartschutz, E-Mail, WOL und Video gegenüber 0.1.20 unverändert
- keine zusätzlichen Symcon-Variablen, Timer, Properties oder LCN-Abfragen

## 0.1.20

- mobile Kachelstabilität korrigiert: native `<details>` durch eigene CSS/JS-Akkordeons ersetzt
- jede geöffnete Kategorie besitzt auf Smartphone ein eigenes Touch-Scrollfenster (`overflow-y: scroll`, `max-height: 46vh`)
- Auf-/Zuklappen sendet keine Modulaktion und verändert keinen Alarmzustand
- letzter bestätigter Visualisierungszustand wird für kurze WebView-Rebuilds maximal 20 s lokal zwischengespeichert
- Zustandsupdates werden feldweise gemergt; leere/ungültige/partielle Zwischenmeldungen löschen keine bereits angezeigten Werte
- Uhrzeiten werden nie mehr auf HTML-Defaults `22:00`/`06:00` zurückgesetzt; sie werden nur bei tatsächlich empfangenem Modulwert aktualisiert
- GUS-, Bewegungsmelder- und Protokolllisten werden nur bei tatsächlich empfangenen Arraydaten neu aufgebaut; dadurch kein kurzzeitiges `Keine ... gefunden` bei Reloads
- neuer rein lesender RequestAction-Pfad `RefreshVisualization` liefert nach einem mobilen WebView-Reload einmalig den vollständigen Istzustand
- `RefreshVisualization` verändert keine Variable, keinen Alarmzustand, keinen Timer, keine LCN-Abfrage und keinen Aktor
- keine zusätzlichen Symcon-Variablen und kein Polling
- Alarmkern, Lichtlogik, Neustartschutz, E-Mail, WOL und Video gegenüber 0.1.19 unverändert

## 0.1.19

- Smartphone-Scrollen für lange aufgeklappte HTML-Bereiche korrigiert
- expliziter Touch-Scroll-Container für Android-/iOS-WebViews
- auf Smartphone kein verschachteltes Scrollfenster mehr in der Historie
- alle IDs, Bedienelemente und JavaScript-Aktionen der Desktop-Variante bleiben erhalten
- `module.php`, MediaServer, Alarmkern, WOL/Video, Lichtlogik, E-Mail-Quittierung und Neustartschutz unverändert zu 0.1.18
- keine zusätzlichen Variablen, Timer oder LCN-Abfragen

## 0.1.18

- reine Visualisierungskorrektur; Alarmkern und `LCNAlarmanlage/module.php` byteidentisch zu 0.1.17
- globale `color-scheme: light dark` entfernt
- Textfarbe verwendet `var(--content-color, #202124)`
- fester Schutzbereich unter der nativen Kachelüberschrift verwendet `var(--card-color, #ffffff)` statt der WebView-Systemfarbe `Canvas`
- Uhrzeitfelder verwenden ebenfalls `--content-color`
- keine neuen Variablen, Timer, Properties, LCN-Abfragen oder Hardwarebefehle
- rollbackfähig auf 0.1.17; 0.1.14 bleibt bewährte Rollback-Basis

## 0.1.17

- reine, rollbackfähige Visualisierungskorrektur auf Basis 0.1.16; 0.1.14 bleibt die bewährte Rollback-Basis
- fester, farbschemaabhängiger Hintergrundstreifen unter der nativen Symcon-Kachelüberschrift verhindert beim Scrollen optische Überschneidungen
- `Status Bewegungsmelder`: sichtbarer Name wird um `Bewegungsmelder` bzw. den vorhandenen Tippfehler `Bewegungsmeder` bereinigt
- Statusdarstellung analog zur Licht-/Panikliste: Punkt links, Bezeichnung mittig, rechts `Bewegung` oder `Ruhe`
- grün/türkis = Bewegung, grau = Ruhe
- keine zusätzlichen Symcon-Variablen, keine neue LCN-Abfrage und kein zusätzliches Polling
- Alarmkern, GUS-Auswertung, Lichtzustandswiederherstellung, Quittierung, Startschutz, E-Mail, WOL und Video unverändert gegenüber 0.1.16

## 0.1.16

- direkte, rollbackfähige Erweiterung von 0.1.15; 0.1.14 bleibt die bewährte Rollback-Basis
- alle Alarm-, Lichtzustands-, Startschutz-, E-Mail-Quittierungs-, Samsung-WOL- und Videoabläufe aus 0.1.15 bleiben unverändert
- oberer Abstand der HTML-Kachel von 50 px auf 72 px erhöht, damit `Alarmanlage EIN/AUS` sicher unterhalb der Symcon-Kachelüberschrift liegt
- neue aufklappbare Kategorie **Status Bewegungsmelder** zwischen `Überwachte Räume` und `Protokoll`
- Statusliste zeigt alle konfigurierten GUS sowie zusätzlich automatisch gefundene native LCN-Unit-Instanzen mit eindeutiger Bewegungsmelder-/GUS-Bezeichnung
- grauer Punkt = Melder AUS/frei, grüner Punkt = Melder AN/aktiv; die Statusanzeige ist rein lesend
- für die neue Statusliste werden **keine zusätzlichen Symcon-Statusvariablen** erzeugt; vorhandene native Boolean-Statuswerte werden direkt gelesen
- nicht als Alarmquelle konfigurierte, automatisch gefundene GUS erhalten nur eine ereignisgesteuerte `VM_UPDATE`-Subscription für die HTML-Anzeige; daraus kann niemals eine Alarm-Session entstehen
- keine zyklische LCN-Abfrage und kein zusätzliches LCN-Polling für die Statusanzeige

## 0.1.15

- direkte, rollbackfähige Erweiterung der funktionierenden 0.1.14; Bibliotheks-GUID, Modul-GUID, Prefix, bestehende Properties, Variablen-Idents, Lichtlogik sowie Samsung-WOL/Video bleiben erhalten
- oberer Abstand der HTML-Kachel von 30 px auf 50 px erhöht; `Überwachte Räume` und `Protokoll` bleiben unverändert aufklappbar
- neuer Startschutz gegen Fehlalarme nach Kernel-/Symcon-Neustart: `RuntimeReady=0`, frische Baseline aller GUS, begrenzter `LCN_RequestStatus()` je realem LCN-Aktormodul und Freigabe erst nach bestätigtem Sensorstatus
- alle konfigurierten GUS bleiben Teil der Erwartungsliste; kann ein technischer LCN-Pfad nicht eindeutig aufgelöst werden, wird der Sensor nicht stillschweigend als synchronisiert behandelt
- GUS-Updates während der Startschutzphase aktualisieren nur den Istzustand und können keine neue Alarm-Session auslösen
- war die Alarmanlage vor dem Ausfall EIN, bleibt dieser persistente Zustand erhalten; `ArmedReady` wird erst nach vollständigem frischem Sensorabgleich und freien überwachten Meldern wieder gesetzt
- fehlende Start-Rückmeldungen führen fail-safe zu `nicht auslösebereit`, nicht zu blindem Scharfschalten; nach maximal einem Retry gibt es kein periodisches LCN-Polling
- aktive Alarm-Session bzw. `rearm_wait` wird nach einem Neustart aus den persistenten Sessiondaten fortgeführt, ohne einen historischen Sensorwert als neuen Alarm zu interpretieren
- Zeitautomatik wird beim Kernelstart nicht rückwirkend neu ausgewertet; der gespeicherte Vor-Ausfall-Zustand bleibt erhalten und die Automatik setzt an der nächsten regulären Zeitgrenze fort
- optionale sichere E-Mail-Quittierung ergänzt: HTTPS-Basis-URL, 256-Bit-Einmal-Token, persistent nur SHA-256-Hash, Sessionbindung, 24-h-Ablauf und GET-Bestätigungsseite ohne Zustandsänderung; erst POST quittiert
- Quittierung per E-Mail verwendet denselben zentralen `AcknowledgeAlarmInternal()`-Pfad wie Visu/GT8; Token wird bei Quittierung, automatischem Alarmende und vollständigem Ausschalten ungültig
- keine zusätzlichen festen Symcon-Statusvariablen; neue technische Zustände liegen ausschließlich in Attributen, Buffern und einem internen Startschutz-Timer
- interner DLNA-Helfer bleibt ohne sichtbare Variablen; Testmodule sind nach erfolgreicher 0.1.15-Abnahme nicht mehr erforderlich

## 0.1.14

- basiert direkt auf 0.1.13; Samsung-WOL/Video- und Alarmkern bleiben unverändert
- Visualisierung oben um zusätzlichen Abstand ergänzt, damit die erste Zeile nicht mehr mit der Kachelüberschrift überlappt
- Property `AcknowledgeLights` bleibt aus Rollback-/Updategründen erhalten, definiert jetzt ausschließlich die Paniklichter
- alle installierten `LCNLight`-Instanzen aus LCN Light Control 0.6.1 werden automatisch als Quittierungs-Lichtschalter registriert; dadurch ist z. B. OG Schlafen 1 ohne Aufnahme in die Panikgruppe nutzbar
- vor der ersten Alarmaktion wird der bekannte EIN/AUS-Zustand aller LCN-Lichter in der Alarm-Session gespeichert
- PANIK EIN schaltet ausschließlich Paniklichter, die vor dem Alarm sicher AUS waren; vorher bereits eingeschaltete Lichter erhalten keinen Befehl
- Quittierung, automatisches Alarmende und vollständiges Ausschalten stellen den gespeicherten Ursprungszustand aller bekannten LCN-Lichter wieder her
- damit bleibt ein vor Alarm eingeschaltetes Licht EIN; ein vom Alarm eingeschaltetes Licht geht wieder AUS; ein zur Quittierung betätigter GT8 wird ebenfalls auf seinen Vor-Alarm-Zustand zurückgeführt
- Lichtbefehle verwenden die definierte `LCL_SetPower()`-/`LCL_GetPowerState()`-Schnittstelle von LCN Light Control 0.6.1; unbekannte Zustände werden niemals blind getoggelt
- 100-ms-Lichtwarteschlange bleibt ereignis-/auftragsbezogen und erzeugt kein Polling

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
