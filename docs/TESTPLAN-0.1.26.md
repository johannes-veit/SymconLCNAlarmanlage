# Testplan 0.1.26 – Samsung Alarm-Lautstärke

## Ziel
Die Lautstärke ist ausschließlich Zusatzfunktion. Alarm, GUS, Paniklicht, WOL, Video, Loop und normaler TV-Endpfad müssen auch bei vollständigem Ausfall aller Lautstärke-Clicks weiter funktionieren.

## T01 – Alarmstart / Video bestätigt
1. TV AUS, Alarm auslösen.
2. WOL und Alarmvideo beobachten.
3. Erst wenn das Video tatsächlich läuft, Lautstärke beobachten.

Erwartung: genau 10 normale `KEY_VOLUP`-Clicks im 500-ms-Raster, also ca. 5 Sekunden Lauter-Sequenz. Kein Press/Release.

## T02 – Quittierung / TV vom Alarm eingeschaltet
1. Alarmvideo läuft und Lauter-Sequenz wurde gestartet.
2. Alarm quittieren.

Erwartung: Video stoppt sofort. Danach 6 `KEY_VOLDOWN`-Clicks im 500-ms-Raster. Nach ca. 3 Sekunden läuft der bisherige TV-AUS-Pfad weiter. Ein fehlgeschlagener VOLDOWN-Click darf TV-AUS nicht verhindern.

## T03 – TV war vor Alarm bereits EIN
1. TV manuell einschalten.
2. Alarm auslösen und wieder quittieren.

Erwartung: Alarmvideo und Lautstärke-Zusatz laufen; nach dem Alarm bleibt der TV wie bisher EIN.

## T04 – Sehr kurze Alarmdauer / Quittierung während VOLUP
1. Alarm auslösen.
2. Während der 5-s-Lauter-Sequenz sofort quittieren.

Erwartung: verbleibende VOLUP-Impulse werden abgebrochen, VOLDOWN beginnt sofort, Video ist gestoppt und nach ca. 3 Sekunden wird der bestehende Endpfad fortgesetzt.

## T05 – Lautstärke-Befehle gestört
SamsungTizen-Verbindung für die Lautstärke testweise nicht verfügbar / Fehler im Log simulieren.

Erwartung: Alarmkern bleibt aktiv, Video-Stopp funktioniert, der 3-s-Finalizer führt den normalen TV-Endpfad trotzdem aus.

## T06 – Neue Alarm-Session während altem Endnachlauf
Während der 3-s-VOLDOWN-Phase neue Alarm-Session auslösen.

Erwartung: neue Session gewinnt. Der alte Endpfad darf den TV der neuen Session nicht ausschalten.
