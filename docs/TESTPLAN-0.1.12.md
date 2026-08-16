# Testplan 0.1.12 – Samsung-Alarmvideo integriert

## Ziel

Version 0.1.12 übernimmt den real getesteten Alarmvideo-Pfad aus `Samsung Alarmvideo Test 0.2.6` in die bestehende `LCN Alarmanlage 0.1.11`, ohne den Alarmkern umzubauen.

Das Alarmvideo verhält sich wie ein weiteres Paniklicht:

- Alarm beginnt -> Alarmvideo einschalten.
- TV ist AUS -> Wake-on-LAN, anschließend Alarmvideo starten.
- TV ist bereits EIN -> Alarmvideo starten, TV nach Alarmende eingeschaltet lassen.
- Alarm endet / wird quittiert -> Alarmvideo stoppen.
- Wurde der TV durch den Alarm eingeschaltet -> TV anschließend wieder ausschalten und nachkontrollieren.
- Samsung-Alarmvideo ist über `Samsung-Alarmvideo bei Alarm verwenden` vollständig abwählbar.

## Vor dem Live-Test

1. Backup von IP-Symcon beibehalten.
2. `LCN Alarmanlage 0.1.11` als Rollback-Version aufbewahren.
3. `Samsung Alarmvideo Test 0.2.6` und dessen alten MediaServer/Server-Socket **noch nicht löschen**.
4. Update auf 0.1.12 installieren und die bestehende Alarmanlagen-Instanz öffnen.
5. Prüfen:
   - Samsung-Alarmvideo aktiviert.
   - richtige SamsungTizen-Instanz gewählt.
   - richtige Boolean-Statusvariable des TVs gewählt.
   - TV-IP `192.168.103.54`.
   - SymBox-IP `192.168.103.59`.
   - Wunschport `8090`.
   - Video-Startverzögerung `4000 ms`.
6. Übernehmen. Allein durch Update/Übernehmen darf weder Alarm noch TV ausgelöst werden.

Hinweis: Solange der alte Test-Medienserver Port 8090 belegt, wählt 0.1.12 automatisch einen freien Port ab 8091. Das ist für den Migrationstest vorgesehen.

## Live-Test A – TV bereits EIN

1. TV normal einschalten und warten, bis dessen Status in Symcon EIN ist.
2. Alarmanlage scharf schalten.
3. Einen überwachten GUS auslösen.
4. Erwartung:
   - Alarm-Session startet genau einmal.
   - Paniklicht wird wie bisher eingeschaltet.
   - Alarmvideo startet auf dem bereits eingeschalteten TV.
   - Video läuft dauerhaft in Endlosschleife.
   - Push/E-Mail verhalten sich unverändert.
5. Alarm über `Alarm deaktivieren` bzw. die vorhandene Quittierung beenden.
6. Erwartung:
   - Alarmvideo stoppt sofort.
   - Paniklicht wird wie bisher ausgeschaltet.
   - TV bleibt EIN, weil er vor dem Alarm bereits EIN war.
   - Hauptschalter der Alarmanlage bleibt EIN.
   - Danach gilt unverändert die vorhandene Frei-/Wieder-scharf-Logik.

## Live-Test B – TV AUS

1. TV vollständig ausschalten und so lange warten, bis Wake-on-LAN wieder zuverlässig möglich ist.
2. Alarmanlage scharf schalten und GUS auslösen.
3. Erwartung:
   - Wake-on-LAN wird unmittelbar gesendet.
   - falls die Statusvariable nach 5 s noch AUS meldet, wird genau ein Wake-Retry gesendet.
   - Alarmvideo startet automatisch und läuft in Endlosschleife.
   - Paniklicht/Benachrichtigungen funktionieren parallel unverändert.
4. Alarm quittieren.
5. Erwartung:
   - Video stoppt.
   - Paniklicht geht AUS.
   - der vom Alarm gestartete TV wird ausgeschaltet.
   - die vorhandene 10-s-Nachkontrolle verhindert, dass ein spät hochfahrender TV eingeschaltet bleibt.

## Live-Test C – automatisches Alarmende

1. Alarm erneut auslösen.
2. GUS wieder frei werden lassen und keine weitere Bewegung erzeugen.
3. Vorhandene Alarm-Nachlaufzeit vollständig ablaufen lassen.
4. Erwartung:
   - Video wird beim automatischen Alarmende gestoppt.
   - Paniklicht wird ausgeschaltet.
   - TV wird nur dann ausgeschaltet, wenn die Alarmanlage ihn selbst gestartet hatte.
   - Wieder-scharf-Ablauf bleibt identisch zu 0.1.11.

## Live-Test D – neue Bewegung während Nachlauf

1. Alarm auslösen, danach alle GUS frei werden lassen.
2. Während des laufenden Nachlaufs erneut Bewegung auslösen.
3. Erwartung:
   - Alarm bleibt aktiv.
   - Video läuft ohne Unterbrechung weiter.
   - Nachlauf wird wie in 0.1.11 verworfen und beginnt erst nach erneut vollständig freien GUS neu.

## Live-Test E – Video deaktiviert

1. `Samsung-Alarmvideo bei Alarm verwenden` ausschalten und übernehmen.
2. Alarm auslösen.
3. Erwartung:
   - kein Wake-on-LAN.
   - kein Alarmvideo.
   - Paniklicht, GUS, Push/E-Mail, Nachlauf, Quittierung und Wieder-scharf funktionieren unverändert.

## Entfernen der Testmodule

Erst wenn A bis C erfolgreich waren:

1. `Samsung Alarmvideo Test` löschen.
2. dessen alten `Samsung Alarmvideo MediaServer Helper` löschen.
3. den alten, zum Testmodul gehörenden Server Socket `Samsung Alarmvideo HTTP` löschen, falls er als technische Altinstanz übrig bleibt.
4. Die neue versteckte Hilfsinstanz `LCN Alarmanlage MediaServer (intern)` und `LCN Alarmanlage Video HTTP` **nicht löschen**; sie gehören jetzt zur Alarmanlage.

Die Alarmanlage darf danach weiter ihren bei der Migration gewählten Port verwenden. Ein Wechsel zurück auf 8090 ist technisch nicht erforderlich.

## Durchgeführte Offline-/Regressionsprüfung

Vor Paketierung wurden 80 automatische statische Prüfungen ohne Fehler ausgeführt (`PASS=80, FAIL=0`). Zusätzlich bestand der neue interne MediaServer einen isolierten HTTP-/DLNA-Harness mit 10/10 Prüfungen (`HELPER_PASS=10, HELPER_FAIL=0`). Geprüft wurden unter anderem:

- PHP-Syntax beider Module.
- alle JSON-Dateien.
- unveränderte Bibliotheks-GUID, Hauptmodul-GUID und Hauptmodul-Prefix.
- bestehende HTML-Kachel byte-identisch zu 0.1.11.
- kritische Alarmkern-Funktionen byte-identisch zu 0.1.11.
- nur die vorgesehenen TV-/Initialisierungsfunktionen der bestehenden Version verändert.
- beide Videodateien SHA256-identisch zum real getesteten Testmodul 0.2.6.
- HTTP-/Range-/Binär-Streaming des internen MediaServers funktional identisch zum getesteten 0.2.6-Helper.
- Isolierter MediaServer-Test: `/status` = HTTP 200, MPEG-HEAD = HTTP 200, DLNA-Header vorhanden, Range `bytes=0-1023` = HTTP 206 mit exakt 1024 Byte Nutzdaten, unbekannter Pfad = HTTP 404, Request-Statistik korrekt.
- `ReceiveData($JSONString)`-Signatur des getesteten Helpers beibehalten.
- Wake-Retry 5 s, Videostart-Retry 2 s, Startdelay 4000 ms und Loop-NextURI-Nachladung 30 s.
- `SetAVTransportURI`, `SetNextAVTransportURI`, `Play` und `Stop` vorhanden.
- Sessionbindung verhindert veraltete Video-Timer aus einer früheren Alarm-Session.
- Video-/MediaServer-Funktionen schreiben nicht in Alarm-Hauptschalter, Alarm-Session, GUS-Status, Nachlauf- oder Wieder-scharf-Zustände.
- Portkonflikt mit dem noch installierten Testmodul wird abgefangen.

Diese Prüfung kann den abschließenden Live-Test auf SymBox, Samsung-TV und LCN-Bus nicht ersetzen.
