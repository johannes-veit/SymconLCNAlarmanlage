# Testplan 0.1.27 – Dahua Active Deterrence

## Vorbedingungen

- Ausgangsstand 0.1.26 funktioniert unverändert.
- Separates Modul `Dahua Alarmkameras` 0.1.0 ist installiert und mit beiden Kameras getestet.
- Dahua-Instanz wird in der Alarmanlagen-Konfiguration ausgewählt.
- Nach dem Update stehen **Alarmlicht** und **Sirene** in der Kachel zunächst AUS.

## Struktur / Update

1. Bestehende Alarmanlagen-Instanz bleibt erhalten.
2. Library-GUID `{931F4DEE-ED55-42F9-9DDB-A8C23293A89D}`, Modul-GUID `{F5B0CD30-B98C-4580-BD71-432F3018628F}` und Prefix `LCNALARM` sind unverändert.
3. Keine neue feste Symcon-Statusvariable gegenüber 0.1.26.
4. Rollback auf 0.1.26 lädt die bestehende Instanz weiter; unbekannte 0.1.27-Attribute werden ignoriert.

## Visualisierung

1. Kategorie **Einstellungen** öffnen.
2. `Dahua Rot/Blau-Alarmlicht` EIN/AUS schalten; Zustand bleibt nach Kachel-Neuladen erhalten.
3. `Dahua Sirene` EIN/AUS schalten; Zustand bleibt nach Kachel-Neuladen erhalten.
4. Während `AlarmActive=true` sind beide Schalter deaktiviert und serverseitig gegen Änderungen geschützt.
5. Ohne gültige Dahua-Instanz sind beide Schalter deaktiviert; Alarmanlage bleibt ansonsten funktionsfähig.

## Alarmlicht ohne Sirene

1. Alarmlicht EIN, Sirene AUS.
2. Echten GUS-Alarm auslösen.
3. Beide Dahua-Kameras müssen Rot/Blau starten; keine Sirene.
4. Quittieren über Visu/LCN-Licht: Rot/Blau muss wieder AUS gehen.
5. Automatischen Nachlauf abwarten: Rot/Blau muss ebenfalls AUS gehen.
6. Während Alarm vollständig unscharf schalten: Rot/Blau muss AUS gehen.

## Sirene

Nur zu geeigneter Testzeit durchführen.

1. Alarmlicht optional, Sirene EIN.
2. Echten Alarm auslösen.
3. Dahua-Modul startet Sirene ca. 10 s und wiederholt den Start alle 11 s.
4. Quittierung muss den Dahua-Sirenentimer zuerst stoppen und sofort `StopAlarm()` auslösen.
5. Nach Quittierung darf keine weitere 11-s-Wiederholung mehr erscheinen.

## Fehlerfälle

1. Dahua-Instanz offline: Alarmkern, Paniklicht, Samsung-TV und Benachrichtigungen laufen weiter.
2. Dahua-Instanz nach Alarmstart nicht erreichbar: Quittierung bleibt erfolgreich; Fehler nur Debug/Log.
3. `ApplyChanges` während aktivem Alarm: darf Dahua nicht erstmals aktivieren.
4. Update von 0.1.26 auf 0.1.27 während bereits aktivem Alarm: darf Dahua nicht nachträglich aktivieren.
5. Echter Kernel-Neustart während einer von 0.1.27 gestarteten Dahua-Session: nach GUS-Startschutz darf genau diese aktive Session fortgeführt werden.
6. Neustart in `rearm_wait`: eventuell verbliebene Dahua-Ausgabe wird best-effort beendet.
