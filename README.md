# LCN Alarmanlage für IP-Symcon 9

## Version 0.1.18

0.1.18 ist eine reine, rollbackfähige Visualisierungskorrektur auf Basis von 0.1.17. Der Alarmkern und `module.php` bleiben byteidentisch zu 0.1.17. Für die HTML-Kachel werden die von der Symcon-Kachelvisualisierung bereitgestellten CSS-Farben `--content-color` und `--card-color` verwendet. Die bisherige globale CSS-Angabe `color-scheme: light dark` und die Systemfarbe `Canvas` wurden entfernt, weil diese Kombination in der Smartphone-App je nach WebView/Farbschema zu schwarzem Kopfbereich bzw. unsichtbarer Schrift führen konnte. Desktop- und Smartphone-Darstellung verwenden nun dieselben Symcon-Farben. WOL, Alarmvideo, Lichtzustandswiederherstellung, GUS-Logik, Neustartschutz, E-Mail-Quittierung, Variablen und Polling-Verhalten sind unverändert.

## Version 0.1.17

0.1.17 ist eine reine, rollbackfähige Visualisierungskorrektur auf Basis von 0.1.16. Beim Scrollen wird der Bereich unter der nativen Symcon-Kachelüberschrift nun mit einem festen, farbschemaabhängigen Hintergrund geschützt, sodass keine Texte mehr unter die Überschrift laufen. In **Status Bewegungsmelder** wird der Begriff `Bewegungsmelder`/`Bewegungsmeder` aus dem sichtbaren Namen entfernt; rechts steht analog zur Lichtstatusdarstellung `Bewegung` bzw. `Ruhe`. Grauer Punkt = Ruhe, grüner Punkt = Bewegung. Alarmkern, Lichtzustandswiederherstellung, Startschutz, E-Mail-Quittierung, Samsung-WOL/Video, vorhandene Variablen und Polling-Verhalten bleiben unverändert. 0.1.14 bleibt die bewährte Rollback-Basis.

## Version 0.1.16

0.1.16 enthält vollständig den Stand 0.1.15 und ergänzt ausschließlich die Visualisierung: mehr Abstand oben und die aufklappbare Kategorie **Status Bewegungsmelder**. Die Liste liest vorhandene native GUS-Statuswerte direkt; grün bedeutet aktiv/AN, grau bedeutet frei/AUS. Alle konfigurierten GUS werden gezeigt, zusätzlich werden eindeutig benannte native LCN-Bewegungsmelder automatisch gefunden. Dafür werden keine zusätzlichen sichtbaren Symcon-Variablen erzeugt und es gibt kein zyklisches LCN-Polling. 0.1.14 bleibt die Rollback-Basis.


## Version 0.1.15

Version 0.1.15 baut direkt auf der funktionierenden **0.1.14** auf und bleibt auf diese Version rollbackfähig. Die real getestete Lichtzustandslogik sowie Samsung-WOL, Alarmvideo, Endlosschleife und Video-Stopp wurden nicht verändert. Ergänzt wurden ausschließlich die Start-/Neustartsicherung, die optionale sichere E-Mail-Quittierung und zusätzlicher Abstand am oberen Rand der Visualisierung.

### Neustart- und Ausfallsicherheit

Der vor einem Symcon-Ausfall gespeicherte Zustand **Alarmanlage EIN/AUS** bleibt erhalten. War die Anlage vorher EIN, wird sie nach dem Neustart nicht blind sofort freigegeben, sondern zunächst in eine Schutzphase gesetzt:

1. `RuntimeReady` bleibt während der Rekonstruktion intern AUS.
2. Alle konfigurierten GUS werden als aktuelle Baseline eingelesen und für `VM_UPDATE` registriert.
3. Pro tatsächlichem LCN-Aktormodul wird einmal `LCN_RequestStatus()` angefordert; nach 8 s ist bei fehlenden Rückmeldungen genau ein begrenzter Retry vorgesehen.
4. Jede GUS-Rückmeldung während dieser Phase aktualisiert nur die Baseline und kann **keinen neuen Alarm** erzeugen.
5. Erst wenn alle konfigurierten GUS mindestens eine frische Statusbestätigung geliefert haben und alle aktuell überwachten Melder frei sind, wird `ArmedReady` wieder gesetzt.
6. Fehlt eine frische Rückmeldung, bleibt der Hauptschalter zwar im zuvor gespeicherten Zustand EIN, die Anlage aber fail-safe **nicht auslösebereit**, bis der fehlende Melder aktualisiert wurde. Es gibt danach kein periodisches Polling.

Damit wird ein beim Hochfahren nachgelieferter `TRUE`-Wert eines bereits aktiven Bewegungsmelders nicht als neue `FALSE -> TRUE`-Flanke interpretiert. Ein vor dem Ausfall laufender Alarm bzw. eine laufende Wieder-scharf-Phase wird nach dem Sensorabgleich aus den persistenten Sessiondaten fortgeführt. Die Zeitautomatik wird beim Neustart nicht rückwirkend neu bewertet; sie läuft ab der nächsten regulären Zeitgrenze weiter.

### Sichere E-Mail-Quittierung

Optional kann die Alarm-E-Mail einen Button **Alarm quittieren** enthalten. Diese Funktion ist nach dem Update standardmäßig AUS und muss ausdrücklich aktiviert werden. Zusätzlich ist eine von außen erreichbare **HTTPS-Basis-URL** einzutragen.

- pro aktiver Alarm-Session wird ein kryptographischer 256-Bit-Einmal-Token erzeugt
- persistent gespeichert wird nur dessen SHA-256-Hash, nicht der Klartext-Token
- der Token ist an genau die aktuelle Alarm-Session gebunden und maximal 24 Stunden gültig
- der Link in der E-Mail führt zunächst nur auf eine Bestätigungsseite; ein normaler GET-Aufruf verändert keinen Alarmzustand
- erst der dortige POST-Button **Alarm jetzt quittieren** ruft denselben zentralen Quittierungsweg wie Visu/GT8 auf
- nach Quittierung, automatischem Alarmende oder vollständigem Ausschalten wird der Token ungültig

Dadurch können automatische Link-Prüfungen eines Mailproviders den Alarm nicht allein durch das Abrufen des Links quittieren.

### Variablenverbrauch 0.1.15

0.1.15 legt gegenüber 0.1.14 **keine zusätzliche feste Symcon-Statusvariable** an. Technische Startzustände, E-Mail-Token, TV-/Videozustände und Warteschlangen liegen ausschließlich in Modul-Attributen, Buffern oder Timern. Der interne DLNA-Medienserver erzeugt ebenfalls keine sichtbaren Symcon-Variablen. Dynamisch bleibt lediglich die bereits vorhandene `WatchSensor<ID>`-Booleanvariable pro konfiguriertem GUS bestehen, weil sie dessen Ein-/Ausschaltung direkt in der Visualisierung ermöglicht.

### Samsung-Testmodule nach erfolgreicher Abnahme

Die produktive Alarmanlage enthält den vollständigen getesteten Samsung-Video-Pfad und ihren eigenen internen Medienserver. Die Bibliothek benötigt weder `Samsung Alarmvideo Test` noch dessen alten `Samsung Alarmvideo MediaServer Helper`. Nach einem erfolgreichen vollständigen Test von 0.1.15 können beide Testmodule samt deren altem Server Socket gelöscht werden. **Nicht löschen**: `LCN Alarmanlage MediaServer (intern)` und `LCN Alarmanlage Video HTTP`; diese beiden versteckten technischen Instanzen gehören zur Alarmanlage selbst.

### Unveränderte Visualisierung aus 0.1.11

- keine zusätzliche eigene Überschrift innerhalb der HTML-Kachel; die Kachelbezeichnung kommt ausschließlich von Symcon
- Schriftstack `Poppins / Segoe UI / Arial` passend zur übrigen Visualisierung
- kompaktere Abstände
- **Überwachte Räume** bleibt aufklappbar
- Punkt vor jedem Raum: **grün = überwacht/aktiv**, **grau = deaktiviert**
- **Protokoll** bleibt aufklappbar
- **Historie** bleibt in einem höhenbegrenzten, scrollbareren Bereich
- technische Endgründe wie `acknowledged` werden bei **Letzter Alarm** nicht mehr angezeigt

### Alarmquellen

Vorhandene native LCN-GUS-Binäreingänge werden als Boolean-Variablen ausgewählt. Die Auswertung erfolgt ereignisgesteuert über `VM_UPDATE`; es gibt kein zyklisches LCN-Polling.


### GUS-Auswahl direkt in der Visualisierung

Für jeden in der Modulkonfiguration aktiv eingetragenen GUS wird dynamisch eine Boolean-Statusvariable angelegt. Ihr sichtbarer Name entspricht exakt der dort vergebenen Bezeichnung. Neue GUS starten mit `EIN`, damit ein Update das bisherige Überwachungsverhalten nicht stillschweigend reduziert.

- `EIN`: dieser GUS wird als Alarmquelle ausgewertet
- `AUS`: Status wird weiterhin intern mitgeführt, aber der GUS löst keinen Alarm aus
- die Auswahl bleibt über Neustarts/Updates erhalten
- Zuschalten eines gerade aktiven Melders erzeugt keinen Sofortalarm; die Anlage wartet erst auf einen freien Zustand
- wenn kein GUS aktiv ist, wird dies in der Statusanzeige ausdrücklich kenntlich gemacht

### Paniklicht und Quittierung

Die sichtbare Integer-Statusvariable der vorhandenen `LCNLightGroup` bleibt nur eine optionale Kontrollreferenz. Die weiterhin gespeicherte Property `AcknowledgeLights` definiert ab 0.1.14 ausschließlich, **welche LCN-Lichter bei Alarm als Paniklicht eingeschaltet werden dürfen**.

Vor dem ersten Panik-/TV-/Benachrichtigungsbefehl wird der Zustand aller installierten `LCNLight`-Instanzen gespeichert. Panik-EIN gilt nur für Lichter, die im Snapshot sicher AUS waren. Ein vorher bereits eingeschaltetes Licht erhält keinen Schaltbefehl. Geschaltet wird über die definierte `LCL_SetPower()`-Schnittstelle von LCN Light Control 0.6.1; unbekannte Zustände werden nicht blind getoggelt. Zwischen notwendigen Befehlen liegen 100 ms.

Zur Quittierung werden automatisch **alle** installierten `LCNLight`-Instanzen registriert, also auch Lichter außerhalb der Panikgruppe (z. B. OG Schlafen 1). Bei einem Nicht-Paniklicht quittiert jede echte Statusänderung während der aktiven Alarm-Session. Bei einem Paniklicht quittiert `EIN -> AUS`, damit das automatische Panik-EIN (`AUS -> EIN`) den Alarm nicht selbst beendet. Danach wird der gespeicherte Zustand aller LCN-Lichter wiederhergestellt: vorher AUS -> wieder AUS, vorher EIN -> wieder EIN. Die Alarmanlage selbst bleibt EIN und geht anschließend in den bekannten Wieder-scharf-Ablauf.

### Push und E-Mail

Pro Alarm-Session wird nur beim ersten GUS-Trigger eine Benachrichtigung eingeplant. Weitere Bewegungen ergänzen ausschließlich das Bewegungsprofil. Persönliche Empfängeradressen bleiben ausschließlich in der lokalen Symcon-Instanzkonfiguration. Die optionale E-Mail-Quittierung aus 0.1.15 ergänzt diesen Versand nur um einen sicheren sessiongebundenen Bestätigungslink; sie verändert den Alarmkern nicht.

### Samsung-TV / Alarmvideo

Die gesamte TV-/Videofunktion bleibt über **Samsung-Alarmvideo bei Alarm verwenden** optional. Beim Update werden keine Alarm- oder TV-Befehle ausgelöst.

Konfiguriert werden:

- vorhandene `SamsungTizen`-Instanz
- vorhandene Boolean-Statusvariable des TV
- TV-IP für UPnP/AVTransport
- SymBox-IP, unter der der TV den internen DLNA-Server erreicht
- Medienserver-Wunschport, Standard `8090`
- Video-Startverzögerung, Standard `4000 ms`

Ablauf beim Alarmstart:

1. internen DLNA-Medienserver prüfen bzw. einmalig automatisch anlegen
2. TV-Status lokal lesen
3. ist der TV AUS: sofort `SamsungTizen_WakeUp()`; nach 5 s maximal ein zweiter Wake-Befehl
4. Alarmvideo nach 4 s starten; maximal drei Videostart-Versuche
5. bevorzugt getestetes MPEG-DLNA-Profil, MP4 als Fallback
6. `SetAVTransportURI` + `Play`; Endlosschleife über `SetNextAVTransportURI`
7. nach bestätigtem Medienabruf wird `NextURI` alle 30 s nachgeladen, solange die Alarm-Session aktiv ist

Ablauf beim Alarmende/Quittieren:

1. alle Video-Start-/Retry-/Loop-Timer sofort stoppen
2. Wiedergabe per UPnP `Stop` beenden – unabhängig davon, ob der TV vor dem Alarm schon EIN war
3. war der TV vorher EIN: TV bleibt EIN
4. wurde der TV vom Alarm gestartet: danach `KEY_POWER` und begrenzte lokale Nachkontrolle wie bisher

Damit verhält sich das Alarmvideo logisch wie ein weiteres Paniklicht: **Alarm aktiv = Video EIN**, **Alarm beendet = Video AUS**. Die TV-/Video-Helfer setzen niemals `Arm`, `AlarmActive`, `CurrentSession`, Automatik oder Wieder-scharf-Countdown.

Der DLNA-Server wird nach einmaliger Einrichtung wiederverwendet. Wenn während der Migration das alte Testmodul Port 8090 noch belegt, sucht die Alarmanlage automatisch den nächsten freien Port bis `8090 + 20`; dadurch können altes Testmodul und neue Alarmanlage zunächst parallel getestet werden.

### Updateprinzip

- Bibliotheks-GUID bleibt `{931F4DEE-ED55-42F9-9DDB-A8C23293A89D}`
- Modul-GUID bleibt `{F5B0CD30-B98C-4580-BD71-432F3018628F}`
- Prefix bleibt `LCNALARM`
- alle bestehenden Properties und Variablen-Idents aus 0.1.14 bleiben erhalten
- neu sind nur die optionalen Properties `EmailAcknowledgeEnabled` und `EmailAcknowledgeBaseURL`; beide greifen nicht in alte Konfigurationen ein
- es werden keine zusätzlichen festen Statusvariablen erzeugt
- `ApplyChanges()` richtet bei aktivierter TV-Funktion nur den lokalen Medienserver ein, sendet aber keinen neuen Alarmbefehl; Sensorstatus wird ausschließlich zur sicheren Start-Baseline angefordert
- Rollback: der komplette Repository-Inhalt kann wieder durch 0.1.14 ersetzt werden; GUIDs, Prefix, bestehende Properties und Variablen bleiben kompatibel

## Alarm-Nachlauf 0.1.9

Die Eigenschaft `AlarmDurationSeconds` bleibt aus Update-Kompatibilitätsgründen unverändert, hat aber ab 0.1.9 die Bedeutung **Nachlaufzeit nach letzter Bewegung**:

1. Alarm startet sofort mit Panik, TV und Benachrichtigungen.
2. Solange mindestens ein aktiv überwachter GUS Bewegung meldet, läuft **kein** Alarm-Endtimer.
3. Sobald alle aktiv überwachten GUS frei sind, startet die konfigurierte Nachlaufzeit.
4. Neue Bewegung bricht den Nachlauf ab.
5. Erst nach erneut vollständig freiem Melderfeld startet die volle Nachlaufzeit neu.
6. Nach Ablauf werden Panik und ein vom Alarm gestarteter TV beendet; danach folgt die getrennte Wieder-scharf-Verzögerung.

## Kompakte Kachel

Die HTML-SDK-Kachel zeigt die kritischen Bedienelemente direkt und fasst Detailinformationen platzsparend zusammen:

- **Überwachte Räume**: einklappbar, je GUS Raumname + EIN/AUS-Schalter.
- **Protokoll**: einklappbar mit Erstauslöser, letzter Bewegung, Bewegungsanzahl und letztem Alarm.
- **Historie**: chronologische Bewegungen des aktuellen bzw. letzten Alarms mit Name und Zeitstempel in einem scrollbar begrenzten Bereich.

Die ursprünglichen Modulvariablen werden nicht entfernt und bleiben in der Listen-/Fallbackdarstellung verfügbar.
