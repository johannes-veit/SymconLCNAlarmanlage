# Architektur 0.1.4

## Sicherheitskern

Der real getestete Alarmkern aus 0.1.3 bleibt die zentrale Zustandsmaschine. GUS-Ereignisse werden unter einer instanzbezogenen Semaphore serialisiert; externe/langsame Aktionen laufen außerhalb dieses kurzen kritischen Bereichs.

## GUS

`LCN-GUS -> nativer LCN-Binärstatus -> Symcon Boolean -> VM_UPDATE -> Alarm-Engine`

Kein Polling, kein virtuelles Relais, keine LED, keine LCN-Hilfsvariable.

## Paniklicht

Die ausgewählten `LCN Licht -> Status`-Booleanvariablen sind die Aktionsziele. Nur abweichende Zustände werden verändert. Eine kurze 100-ms-Warteschlange serialisiert tatsächlich notwendige Lichtbefehle.

## Quittierung

Nur `true -> false` eines freigegebenen Paniklichts bei `CurrentSession.state == active` quittiert. Die Session wird zuerst auf `rearm_wait` verriegelt, erst danach werden Paniklichter ausgeschaltet. Der Hauptschalter bleibt EIN.

## Push und E-Mail

Beim ersten GUS-Trigger einer neuen Session wird nach Freigabe der Engine-Semaphore eine lokale `NotificationQueue` erzeugt. Sie enthält maximal einen Push-Auftrag und je einen SMTP-Auftrag pro gültigem Empfänger. Weitere GUS derselben Session erzeugen keine neuen Benachrichtigungen.

Reihenfolge:

1. Alarm-Session unter Semaphore erzeugen
2. `AlarmActive` und Alarmtimer setzen
3. Semaphore freigeben
4. Paniklicht anfordern
5. Push/E-Mail lokal einplanen
6. Benachrichtigungsworker arbeitet außerhalb der Alarm-Engine-Semaphore

SMTP-/Push-Fehler werden protokolliert und beeinflussen den Alarmkern nicht. Die Queue ist absichtlich nicht persistent: Ein Neustart rekonstruiert sie nicht, damit eine bereits versendete Nachricht nicht doppelt zugestellt wird.

Persönliche E-Mail-Adressen werden ausschließlich in Symcon-Properties der lokalen Instanz gespeichert und niemals als Repository-Default ausgeliefert.

## Update-/Rollback-Regeln

GUIDs, Prefix und bestehende Property-/Ident-Namen nicht ändern. Neue Properties haben neutrale Defaults. Keine Hardwareaktionen oder Benachrichtigungen allein durch ein Update.
