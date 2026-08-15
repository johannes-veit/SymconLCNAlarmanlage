# Architektur und Stabilitätsregeln – LCN Alarmanlage

Diese Datei ist Bestandteil der Referenzbasis. Künftige Versionen sollen diese Regeln nur gezielt und nachvollziehbar ändern.

## Eingangspfad

Der Bewegungsmelder wird ausschließlich über die bereits getestete native Boolean-Statusvariable eines LCN-Binäreingangs ausgewertet:

`LCN-GUS -> LCN-Modul -> IP-Symcon LCN-Binäreingang -> Boolean-Variable -> VM_UPDATE -> Alarm-Engine`

Im Normalbetrieb gibt es **keine zyklische Statusabfrage**, kein `LCN_RequestStatus()`, kein virtuelles Relais, keine LED und keine LCN-Hilfsvariable.

## Flankenerkennung

Für jeden konfigurierten GUS wird ein eigener Baseline-Zustand geführt.

- `FALSE -> TRUE`: neue Bewegung
- `TRUE -> FALSE`: Bewegungsende
- identischer Wert erneut: kein neues Ereignis

Beim Start bzw. nach `ApplyChanges()` werden aktuelle Werte nur als Baseline übernommen. Ein bereits aktiver Melder darf dadurch keinen historischen Alarm erzeugen.

## Kollisionsschutz

Alle Änderungen an den sicherheitskritischen Kernzuständen laufen über **eine instanzbezogene Semaphore**:

- `Arm`
- `ArmedReady`
- `CurrentSession`
- `RearmNotBefore`
- erste lokale Alarmaktivierung

Damit werden nahezu gleichzeitig eintreffende GUS-Ereignisse sowie gleichzeitige Bedienungen serialisiert.

Der kritische Abschnitt muss kurz bleiben. Netzwerk-, LCN-, TV-, E-Mail- oder Push-Aktionen dürfen in späteren Versionen **niemals** darin ausgeführt werden.

## Regel für spätere externe Alarmaktionen

Paniklicht, Push, E-Mail und Samsung-TV werden erst in Folgeversionen ergänzt. Vor jeder solchen Aktion muss die erzeugte Alarm-Session erneut über ihre Session-ID validiert werden. Eine bereits quittierte oder beendete Session darf keine verspätete Aktion mehr auslösen.

## MessageSink

`MessageSink::$Data` wird nicht zur Alarmentscheidung interpretiert. Der Sender wird über `SenderID` eindeutig identifiziert; der aktuelle Zustand wird aus der registrierten Boolean-Variable gelesen.

## Initialisierung

Während der Laufzeitrekonstruktion ist `RuntimeReady = 0`. Eingehende Sensoränderungen dürfen dann nur die Baseline aktualisieren. Erst nach konsistenter Rekonstruktion von Sensoren, Scharfzustand und Alarm-Session wird `RuntimeReady = 1` gesetzt.

## Alarm-Session

Nur die erste gültige Bewegungsflanke bei scharfer und bereiter Anlage erzeugt eine neue Alarm-Session. Weitere Melderbewegungen werden derselben Session als Ereignisse hinzugefügt.

Die Session enthält mindestens:

- eindeutige Session-ID
- Startzeit
- Erstauslöser
- fortlaufende Ereignisnummer
- Zeitstempel je Bewegung/Freimeldung
- Melder-ID und Meldername
- Endzeit und Endgrund

## Timeout und Wiederbereitschaft

Nach Ablauf der konfigurierten Alarmdauer endet die Signalisierungsphase automatisch. Die Anlage bleibt grundsätzlich scharf, wird aber erst wieder auslösebereit, wenn:

1. alle ausgewählten Melder frei sind und
2. die Wiederbereitschaftszeit abgelaufen ist.

Dadurch kann ein dauerhaft aktiver GUS keine endlose Folge von 5-Minuten-Alarmen erzeugen.

Eine **manuelle Quittierung** ist absichtlich anders: Sie beendet die aktuelle Session und setzt die Alarmanlage vollständig auf AUS.

## Timer

Alle Timer sind als Einmal-Abläufe aufgebaut: Sie werden mit einem Intervall gestartet und am Beginn des Aufrufs wieder auf `0` gesetzt. Nach Neustart/`ApplyChanges()` werden benötigte Restzeiten aus persistenten Zuständen rekonstruiert.

Keine Funktion verwendet lange `sleep()`-Wartezeiten.

## Konfigurationsfehler

Fehlende, gelöschte, doppelte oder nicht-Boolean Sensorvariablen verhindern das Scharfschalten. Bei Verlust eines bereits konfigurierten Sensors wird die Anlage sicher auf AUS gesetzt und die Automatik bis zur erneuten erfolgreichen Konfiguration blockiert.

## Update-Regeln

Für Folgeversionen gelten verbindlich:

- Bibliotheks-GUID und Modul-GUID beibehalten.
- Prefix `LCNALARM` beibehalten.
- bestehende Property-/Attribut-/Ident-Namen nicht ohne Migration ändern.
- keine technische Zuordnung über Objektbaumpfade oder sichtbare Namen.
- keine fremden LCN-/Symcon-Instanzen automatisch umkonfigurieren.
- Funktionsänderungen und reine Visu-Änderungen möglichst getrennt entwickeln und testen.
- vor jedem Release PHP-Syntax, JSON, Struktur, GUIDs, Prefix, Updatepfad und ZIP-Struktur prüfen.
- ZIP enthält genau einen Hauptordner `LCN-Alarmanlage`.
