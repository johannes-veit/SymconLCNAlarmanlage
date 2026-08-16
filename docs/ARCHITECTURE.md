# Architektur 0.1.11

## Sicherheitskern

Die zentrale Alarm-Engine bleibt semaphore-geschützt. GUS-Ereignisse verändern innerhalb des kurzen kritischen Bereichs nur Session, Zustände und Deadlines; Paniklicht, TV, Push und E-Mail laufen weiterhin außerhalb der Semaphore.

## GUS

`LCN-GUS -> nativer LCN-Binärstatus -> Symcon Boolean -> VM_UPDATE -> Alarm-Engine`

Kein Polling, kein virtuelles Relais, keine LED, keine LCN-Hilfsvariable.

## Alarm-Nachlauf

`AlarmDurationSeconds` bleibt aus Update-Kompatibilitätsgründen als Property-Name erhalten, bedeutet ab 0.1.9 aber **Nachlaufzeit nach letzter Bewegung**.

- Alarmstart: keine Alarm-Enddeadline.
- Solange mindestens ein aktiv überwachter GUS `true` meldet: kein Alarm-Endtimer.
- Wenn alle aktiv überwachten GUS frei sind: `AlarmQuietNotBefore = now + AlarmDurationSeconds` und einmaliger `AlarmTimeout`.
- Neue Bewegung: `AlarmQuietNotBefore = 0`, Timer AUS.
- Wieder alle frei: volle Nachlaufzeit beginnt neu.
- `AlarmTimeout()` prüft vor dem Ende nochmals das vollständige Freisein aller überwachten GUS und beendet niemals bei aktiver Bewegung.
- Die Deadline ist persistent, damit ein Neustart während eines bereits laufenden Nachlaufs sauber rekonstruiert werden kann.

Nach Ablauf des Alarm-Nachlaufs werden Panik/TV beendet und erst danach beginnt die getrennte Wieder-scharf-Verzögerung `RearmDelaySeconds`.

## Paniklicht und Quittierung

Die ausgewählten `LCN Licht -> Status`-Booleanvariablen sind Aktionsziele. Nur abweichende Zustände werden verändert. Eine echte `true -> false`-Flanke eines freigegebenen Paniklichts bei aktiver Session quittiert. Die Session wird zuerst auf `rearm_wait` verriegelt; erst danach werden externe Aktionen beendet.

## Push und E-Mail

Einmal pro Session beim ersten Trigger, außerhalb der Engine-Semaphore. Fehler beeinflussen den Alarmkern nicht.

## Samsung-TV – strikte Entkopplung

Die TV-Funktion ist ein nachgeschalteter Helfer. Sie erhält nur zwei Ereignisse aus dem Alarmkern:

- `StartTVForAlarm(SessionID)` nach erfolgreicher Erzeugung einer neuen Alarm-Session
- `EndTVForAlarm(SessionID, Reason)` nachdem die Session bereits verriegelt/beendet wurde

Der TV-Helfer darf niemals `Arm`, `AlarmActive`, `CurrentSession`, `ArmedReady`, `RearmNotBefore`, `AlarmQuietNotBefore` oder die Automatik verändern.

EIN erfolgt direkt über `SamsungTizen_WakeUp()`. Ein einziger Retry nach 5 s ist erlaubt. AUS erfolgt nur, wenn der TV vom Alarm übernommen/gestartet wurde. War der TV beim Alarmstart bereits EIN, wird er nach Alarmende nicht ausgeschaltet.

## Kompakte Kachel

Die Kacheldarstellung nutzt das offizielle Symcon HTML-SDK. Kritische Zustände und Aktionen bleiben gleichzeitig als native Modulvariablen vorhanden und damit in der Listen-/Fallbackdarstellung verfügbar.

Direkt sichtbar:

- Alarmanlage EIN/AUS
- Status
- Alarm deaktivieren bei aktiver Session
- Automatik
- Scharf ab / Unscharf ab

Einklappbar:

- **Überwachte Räume**: GUS-Raumname, Bewegungsindikator, lokaler EIN/AUS-Schalter
- **Protokoll**: Erstauslöser, letzte Bewegung, Bewegungsanzahl, letzter Alarm und scrollbar begrenzte Historie

Die Historie zeigt ausschließlich `motion`-Ereignisse des aktuellen bzw. letzten Alarms in chronologischer Reihenfolge. Die vollständige interne Session behält weiterhin auch CLEAR-Ereignisse für Diagnose und Zustandslogik.

## Update-/Rollback-Regeln

GUIDs, Prefix und bestehende Property-/Ident-Namen nicht ändern. `AlarmDurationSeconds` wurde ausdrücklich nicht umbenannt. Neue Attribute haben neutrale Defaults. Keine Hardwareaktionen allein durch Update/ApplyChanges.
