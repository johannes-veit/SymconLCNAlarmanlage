# LCN Alarmanlage – Version 0.1.0

Erste bewusst kleine Kern-Testversion für IP-Symcon 9.0.

## Zweck dieser Version

Diese Version testet ausschließlich den sicherheitskritischen Kern:

- beliebig viele native LCN-GUS-Binärstatus-Booleanvariablen auswählen
- vollständig ereignisgesteuert über `VM_UPDATE`
- kein LCN-Polling und kein `LCN_RequestStatus()` im Normalbetrieb
- Flankenerkennung je GUS
- kollisionsgeschützte zentrale Alarm-Session
- gleichzeitige Meldungen mehrerer GUS
- Bewegungsprofil mit Zeitstempeln und Reihenfolge
- manuell EIN/AUS
- Zeitautomatik
- manuelle Übersteuerung bis zur nächsten Automatik-Zeitgrenze
- automatisches Ende der Signalisierungsphase nach 300 s (einstellbar)
- Wiederbereitschaft erst nach freien Meldern plus Verzögerung
- persistente aktuelle/letzte Alarm-Session
- Wiederanlauf nach Symcon-Neustart ohne historischen Fehlalarm
- sicheres Abschalten bei Verlust einer konfigurierten Sensorvariable

Noch **nicht** enthalten: Paniklicht, LCN-Quittierung über GT8/GT2, Push, E-Mail, Samsung-TV, blinkende Sonderdarstellung und TV-Alarm-App.

## Installation

Der ZIP-Inhalt besitzt genau einen Hauptordner `LCN-Alarmanlage`. Dieser Ordner ist als vollständiger Repository-/GitHub-Ordner gedacht.

1. Ordner in das vorgesehene Git-Repository übernehmen.
2. Repository über die Symcon-Modulverwaltung laden/aktualisieren.
3. Instanz **LCN Alarmanlage** anlegen.
4. In der Sensorliste ausschließlich die bereits praktisch getesteten nativen Boolean-Variablen der LCN-Binäreingänge auswählen.
5. Pro Variable eine eindeutige räumliche Bezeichnung vergeben.
6. Übernehmen.

Die Automatik ist nach der Erstinstallation absichtlich **AUS**. Für die ersten Tests die Alarmdauer bei Bedarf vorübergehend auf z. B. 20 Sekunden reduzieren.

## Erster Test

1. Zwei oder mehr GUS konfigurieren.
2. Alarmanlage manuell EIN schalten.
3. Wenn alle Melder frei sind, muss der Status `ALARMANLAGE SCHARF` erscheinen.
4. GUS 1 auslösen: genau eine Alarm-Session muss starten.
5. Während des Alarms GUS 2, GUS 3 und wieder GUS 1 auslösen.
6. Im Bewegungsprofil müssen alle echten Zustandswechsel in Reihenfolge auftauchen.
7. Mehrere GUS gleichzeitig auslösen.
8. `Alarm deaktivieren` betätigen: Anlage muss sofort AUS werden.
9. Danach automatisches Alarmende mit kurzer Testdauer prüfen.
10. Neustart/ApplyChanges nach Testplan prüfen.
11. Erst nach erfolgreichem Kern-Test werden externe Alarmaktionen ergänzt.

## Sicherheitsprinzipien

- Das Modul liest `MessageSink::$Data` nicht zur Alarmentscheidung aus.
- Der aktuelle GUS-Zustand wird über die registrierte Sender-ID/Boolean-Variable gelesen.
- Keine Objektbaum-Pfade oder Namen dienen der technischen Zuordnung.
- Ausgewählte Variablen werden per ID referenziert und auf Boolean-Typ geprüft.
- Sensorereignisse und Scharf-/Unscharfschaltung nutzen denselben zentralen Semaphore-Kollisionsschutz.
- Externe/langsame Aktionen laufen später niemals innerhalb des Alarm-Semaphors.
- Timer sind kurze Einmal-Abläufe und werden nach `ApplyChanges`/Neustart rekonstruiert.
- Ein bereits aktiver GUS beim Initialisieren löst keinen historischen Alarm aus.

Siehe zusätzlich `docs/ARCHITECTURE.md` und `docs/TESTPLAN-0.1.0.md`.

## Hinweis zur Testphase

Version 0.1.0 ist bewusst eine Funktions- und Stabilitätstestversion des Kerns. Sie sollte erst nach dem vollständigen Testplan als Grundlage für die späteren Alarmaktionen verwendet werden.
