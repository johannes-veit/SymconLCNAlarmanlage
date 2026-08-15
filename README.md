# LCN Alarmanlage für IP-Symcon 9

## Version 0.1.3

Version 0.1.3 erweitert den real getesteten Alarmkern 0.1.1 ausschließlich um Paniklicht und eine lokale LCN-/Licht-Quittierung. Push, E-Mail und Samsung-TV bleiben absichtlich noch außen vor.

### Alarmquellen

Vorhandene native LCN-GUS-Binäreingänge werden als Boolean-Variablen ausgewählt. Die Auswertung erfolgt ereignisgesteuert über `VM_UPDATE`; es gibt kein zyklisches LCN-Polling.

### Paniklicht

Als Panikziel wird die **Integer-Statusvariable** der vorhandenen `LCNLightGroup` ausgewählt. Die Alarmanlage verwendet die vorhandene Variablenaktion:

- Alarmbeginn: Zielwert `1` (EIN)
- Quittierung / automatisches Alarmende / Anlage AUS bei aktivem Alarm: Zielwert `0` (AUS)

Die bestehende Gruppenlogik entscheidet weiterhin selbst, welche Gruppenmitglieder tatsächlich einen LCN-Kurzbefehl benötigen. Das Alarmmodul verändert weder die Lichtgruppenbibliothek noch deren Konfiguration.

### Quittierung über GT8/GT2/Licht

Für jedes gewünschte Paniklicht wird dessen **Boolean-Statusvariable der LCN-Light-Instanz** ausgewählt. Während eines aktiven Alarms sind die Paniklichter eingeschaltet. Eine echte Statusflanke `EIN -> AUS`, z. B. als Ergebnis eines normalen kurzen GT8/GT2-Tastendrucks, quittiert die aktuelle Alarm-Session.

Das Alarmmodul erkennt dabei bewusst **nicht das rohe GT8/GT2-Tastentelegramm**, sondern die zuverlässig rückgemeldete Ausschaltung des ausgewählten Lichts. Deshalb würde auch eine andere reale Bedienung, die eines dieser ausgewählten Paniklichter während des aktiven Alarms von EIN auf AUS schaltet, quittieren.

Wichtig: Das Alarmmodul wertet nicht einfach „irgendeine Statusänderung“ aus. `AUS -> EIN` durch PANIK EIN quittiert nie. Beim PANIK AUS ist die Session bereits auf Wieder-Scharf-Wartezustand verriegelt und kann sich deshalb nicht selbst quittieren.

### Quittierung

Eine Quittierung beendet nur den aktuellen Alarm. Der Hauptschalter bleibt EIN. Nach freien Bewegungsmeldern läuft der Countdown `Wieder scharf in …`; anschließend ist die Anlage wieder scharf.

### Updateprinzip

- Bibliotheks-GUID bleibt `{931F4DEE-ED55-42F9-9DDB-A8C23293A89D}`
- Modul-GUID bleibt `{F5B0CD30-B98C-4580-BD71-432F3018628F}`
- Prefix bleibt `LCNALARM`
- bestehende Sensor-, Zeit- und Alarmkonfigurationen aus 0.1.1 bleiben erhalten
- neue Panik-/Quittierfelder sind standardmäßig leer und damit wirkungslos


## Paniklicht 0.1.3

Die `LCNLightGroup`-Statusvariable ist eine reine Integer-Rückmeldung. Das Alarmmodul benutzt sie nicht als Aktionsvariable. Geschaltet werden stattdessen die explizit konfigurierten Boolean-Statusvariablen der Gruppenmitglieder. Nur abweichende Zustände werden per `RequestActionEx()` geändert; zwischen notwendigen Befehlen liegen 100 ms.
