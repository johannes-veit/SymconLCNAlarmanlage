# LCN Alarmanlage für IP-Symcon 9

## Version 0.1.4

Version 0.1.4 erweitert den real getesteten Alarmkern 0.1.3 ausschließlich um Push und E-Mail. Samsung-TV bleibt absichtlich noch außen vor.

### Alarmquellen

Vorhandene native LCN-GUS-Binäreingänge werden als Boolean-Variablen ausgewählt. Die Auswertung erfolgt ereignisgesteuert über `VM_UPDATE`; es gibt kein zyklisches LCN-Polling.

### Paniklicht und Quittierung

Die sichtbare Integer-Statusvariable der vorhandenen `LCNLightGroup` ist nur eine optionale Kontrollreferenz. Geschaltet werden die explizit konfigurierten Boolean-Statusvariablen der Gruppenmitglieder. Nur abweichende Leuchten erhalten über ihre normale Symcon-Aktion einen Befehl; zwischen notwendigen Befehlen liegen 100 ms.

Eine echte Statusflanke `EIN -> AUS` eines freigegebenen Paniklichts während einer aktiven Alarm-Session quittiert den aktuellen Alarm. Die Alarmanlage selbst bleibt EIN. Nach freien Bewegungsmeldern läuft `Wieder scharf in …`; danach ist die Anlage wieder scharf.

### Push

Push ist nach dem Update standardmäßig AUS. Nach Auswahl der vorhandenen **Kachelvisualisierung** kann Push aktiviert werden. Beim ersten GUS-Trigger einer Alarm-Session wird genau eine Nachricht erzeugt:

- Titel: `ALARM AUSGELÖST!`
- Text: Erstauslöser und Zeitpunkt
- Ton: `siren`
- Antippen: öffnet direkt die Kachel dieser Alarmanlagen-Instanz

Weitere GUS-Bewegungen derselben Session erweitern nur das Bewegungsprofil und erzeugen keine weiteren Push-Nachrichten.

### E-Mail

E-Mail ist nach dem Update standardmäßig AUS. Es wird eine vorhandene **SMTP-Instanz** ausgewählt. Mehrere Empfänger können mit Semikolon, Komma oder Zeilenumbruch getrennt eingetragen werden. Jede gültige Adresse erhält beim ersten Trigger einer Alarm-Session genau eine E-Mail über `SMTP_SendMailEx()`.

Persönliche Empfängeradressen sind absichtlich **nicht Bestandteil des Repositorys**. Sie werden ausschließlich in der lokalen IP-Symcon-Instanzkonfiguration gespeichert.

Die Mail enthält Alarm-ID, Erstauslöser und Zeitpunkt. Ein direkter Quittierungslink in der E-Mail ist in 0.1.4 noch nicht enthalten. Die Symcon-Push-Nachricht öffnet stattdessen unmittelbar die Alarmkachel mit dem vorhandenen Button `Alarm deaktivieren`.

### Entkopplung und Fehlerverhalten

Alarmzustand und Paniklicht werden zuerst gesetzt. Push/SMTP laufen anschließend außerhalb der Alarm-Engine-Semaphore über eine kurzlebige lokale Warteschlange. Ein langsamer oder gestörter Mailserver kann daher die GUS-Auswertung und Quittierung nicht blockieren.

Benachrichtigungsfehler werden im Symcon-Log/Debug protokolliert, legen die Alarmanlage aber nicht still. Nach einem Neustart wird eine bereits begonnene Benachrichtigungswarteschlange nicht rekonstruiert, um doppelte Zustellungen zu vermeiden.

### Updateprinzip

- Bibliotheks-GUID bleibt `{931F4DEE-ED55-42F9-9DDB-A8C23293A89D}`
- Modul-GUID bleibt `{F5B0CD30-B98C-4580-BD71-432F3018628F}`
- Prefix bleibt `LCNALARM`
- bestehende Sensor-, Panik-, Zeit- und Alarmkonfigurationen aus 0.1.3 bleiben erhalten
- neue Push-/E-Mail-Properties haben neutrale Defaults und erzeugen durch das Update keine Benachrichtigung
