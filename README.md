# LCN Alarmanlage für IP-Symcon 9

## Version 0.1.14

Version 0.1.14 baut direkt auf 0.1.13 auf. Der bereits erfolgreich getestete Alarmkern sowie Samsung-WOL, Alarmvideo, Endlosschleife und Video-Stopp wurden nicht verändert. Geändert wurden ausschließlich die Lichtlogik und der obere Abstand der Visualisierung.

LCN Light Control **0.6.1** wird über die feste Modul-GUID von `LCNLight` erkannt. Vor jeder Alarmaktion speichert die Alarmanlage den sicher bekannten EIN/AUS-Zustand aller LCN-Lichtinstanzen. Nur ausgewählte Paniklichter, die zu diesem Zeitpunkt AUS sind, werden eingeschaltet. Bei Quittierung, automatischem Alarmende oder vollständigem Ausschalten wird der gespeicherte Vor-Alarm-Zustand aller bekannten LCN-Lichter wiederhergestellt.

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

Push und E-Mail entsprechen dem getesteten Stand 0.1.4. Pro Alarm-Session wird nur beim ersten GUS-Trigger eine Benachrichtigung eingeplant. Weitere Bewegungen ergänzen ausschließlich das Bewegungsprofil. Persönliche Empfängeradressen bleiben ausschließlich in der lokalen Symcon-Instanzkonfiguration.

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
- alle bestehenden Properties und Variablen-Idents aus 0.1.11 bleiben erhalten
- neue Video-Properties werden ergänzt; bestehende TV-Auswahl bleibt erhalten
- `ApplyChanges()` richtet bei aktivierter TV-Funktion nur den lokalen Medienserver ein, sendet aber keinen TV-/Alarmbefehl

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
