# LCN Alarmanlage für IP-Symcon 9

## Version 0.1.8

Version 0.1.8 basiert weiterhin **direkt auf der real getesteten stabilen Version 0.1.4**. Die TV-Erweiterung aus 0.1.7 wurde nochmals abgesichert. Zusätzlich erhält jeder konfigurierte GUS einen eigenen persistenten EIN/AUS-Schalter in der normalen Symcon-Visualisierung.

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

Die sichtbare Integer-Statusvariable der vorhandenen `LCNLightGroup` ist nur eine optionale Kontrollreferenz. Geschaltet werden die explizit konfigurierten Boolean-Statusvariablen der Gruppenmitglieder. Nur abweichende Leuchten erhalten über ihre normale Symcon-Aktion einen Befehl; zwischen notwendigen Befehlen liegen 100 ms.

Eine echte Statusflanke `EIN -> AUS` eines freigegebenen Paniklichts während einer aktiven Alarm-Session quittiert den aktuellen Alarm. Die Alarmanlage selbst bleibt EIN. Nach freien Bewegungsmeldern läuft `Wieder scharf in …`; danach ist die Anlage wieder scharf.

### Push und E-Mail

Push und E-Mail entsprechen dem getesteten Stand 0.1.4. Pro Alarm-Session wird nur beim ersten GUS-Trigger eine Benachrichtigung eingeplant. Weitere Bewegungen ergänzen ausschließlich das Bewegungsprofil. Persönliche Empfängeradressen bleiben ausschließlich in der lokalen Symcon-Instanzkonfiguration.

### Samsung-TV

Die TV-Funktion ist nach dem Update standardmäßig AUS. Konfiguriert werden ausschließlich:

- die vorhandene `SamsungTizen`-Instanz
- die vorhandene Boolean-Statusvariable des TV

Die PowerFix-Impulsvariable wird **nicht** verwendet.

Ablauf beim Alarmstart:

1. TV-Status lokal lesen.
2. Bereits EIN: keine Aktion; der TV bleibt auch nach dem Alarm EIN.
3. AUS: sofort `SamsungTizen_WakeUp()` senden.
4. Nach 5 s noch AUS: genau ein zweiter `SamsungTizen_WakeUp()`.
5. Danach keine weiteren Wake-Versuche.

Ablauf beim Alarmende, falls der TV vom Alarm gestartet wurde:

1. ausstehenden Wake-Retry sofort stoppen
2. wenn TV EIN: `SamsungTizen_SendKeys(..., 'KEY_POWER')`
3. nach 10 s lokalen TV-Status kontrollieren
4. falls weiterhin EIN: erneut `KEY_POWER`
5. begrenzte Nachkontrolle; keine Endlosschleife

Die TV-Helfer besitzen **keinen Pfad**, der `Arm`, `AlarmActive`, `CurrentSession`, Automatik oder den Wieder-scharf-Countdown setzt. Samsung-Fehler werden nur protokolliert und dürfen den Alarmkern nicht beeinflussen.

### Updateprinzip

- Bibliotheks-GUID bleibt `{931F4DEE-ED55-42F9-9DDB-A8C23293A89D}`
- Modul-GUID bleibt `{F5B0CD30-B98C-4580-BD71-432F3018628F}`
- Prefix bleibt `LCNALARM`
- alle bestehenden 0.1.4-Properties und Variablen-Idents bleiben erhalten
- neue TV-Properties haben neutrale Defaults; ein Update allein sendet keinen TV-Befehl
- keine Hardwareaktion allein durch `ApplyChanges()`
