# Architektur 0.1.7

## Sicherheitskern

Der real getestete Alarmkern aus 0.1.4 bleibt unverändert die zentrale Zustandsmaschine. GUS-Ereignisse werden unter einer instanzbezogenen Semaphore serialisiert; externe/langsame Aktionen laufen außerhalb dieses kurzen kritischen Bereichs.

## GUS

`LCN-GUS -> nativer LCN-Binärstatus -> Symcon Boolean -> VM_UPDATE -> Alarm-Engine`

Kein Polling, kein virtuelles Relais, keine LED, keine LCN-Hilfsvariable.

## Paniklicht und Quittierung

Die ausgewählten `LCN Licht -> Status`-Booleanvariablen sind Aktionsziele. Nur abweichende Zustände werden verändert. Eine echte `true -> false`-Flanke eines freigegebenen Paniklichts bei aktiver Session quittiert. Die Session wird zuerst auf `rearm_wait` verriegelt; erst danach werden externe Aktionen beendet.

## Push und E-Mail

Unverändert gegenüber 0.1.4: einmal pro Session beim ersten Trigger, außerhalb der Engine-Semaphore. Fehler beeinflussen den Alarmkern nicht.

## Samsung-TV – strikte Entkopplung

Die TV-Funktion ist ein nachgeschalteter Helfer. Sie erhält nur zwei Ereignisse aus dem Alarmkern:

- `StartTVForAlarm(SessionID)` nach erfolgreicher Erzeugung einer neuen Alarm-Session
- `EndTVForAlarm(SessionID, Reason)` nachdem die Session bereits verriegelt/beendet wurde

Der TV-Helfer darf niemals `Arm`, `AlarmActive`, `CurrentSession`, `ArmedReady`, `RearmNotBefore` oder die Automatik verändern.

EIN erfolgt direkt über `SamsungTizen_WakeUp()`, nicht über eine PowerFix-Impulsvariable. Ein einziger Retry nach 5 s ist erlaubt. AUS erfolgt nur, wenn der TV vom Alarm übernommen/gestartet wurde. War der TV beim Alarmstart bereits EIN, wird er nach Alarmende nicht ausgeschaltet.

Die 10-s-AUS-Nachkontrolle liest ausschließlich die bereits vorhandene lokale TV-Statusvariable. Sie läuft zeitlich begrenzt und kann keinen neuen Alarm erzeugen oder die Scharfschaltung ändern. Eine neue aktive Alarm-Session hat immer Vorrang vor einem alten AUS-Nachlauf.

## Update-/Rollback-Regeln

GUIDs, Prefix und bestehende Property-/Ident-Namen nicht ändern. Neue Properties haben neutrale Defaults. Keine Hardwareaktionen allein durch Update/ApplyChanges. 0.1.4 bleibt die bekannte Rollback-Basis.
