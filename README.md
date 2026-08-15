# LCN Alarmanlage für IP-Symcon 9

## Version 0.1.6

Version 0.1.6 korrigiert ausschließlich die Samsung-TV-Powersteuerung aus 0.1.5. GUS-, Alarm-, Panik-, Quittierungs-, Countdown-, Push- und E-Mail-Logik bleiben unverändert. Der erste TV-Start läuft weiter über den getesteten PowerFix; solange eine echte Alarm-Session aktiv ist und der TV noch AUS meldet, folgen nach 5 s und 10 s maximal zwei zusätzliche native Wake-on-LAN-Versuche. Nach Alarmende werden keine weiteren Wake-Versuche gesendet. War der TV bereits vor dem Alarm EIN, wird er nach Alarmende nicht ausgeschaltet.

### Samsung-TV

Die Alarmanlage greift nicht direkt auf interne Funktionen des Samsung-Moduls zu, sondern auf die bereits getestete PowerFix-Fernbedienung:

- `Samsung-TV – Status (Boolean)`: reale EIN/AUS-Rückmeldung der Samsung-Tizen-Instanz.
- `Samsung-TV – Ein/Aus Impulsbutton (PowerFix)`: der von `Samsung_Tizen_PowerFix_v7.php` erzeugte Integer-Button `Ein/Aus`.

Damit bleibt das Alarmmodul von konkreten Samsung-Modul-GUIDs und internen Variablennamen entkoppelt.

Bei Alarm wird nur dann ein EIN-Impuls gesendet, wenn der bestätigte Status AUS ist. Mehrere GUS derselben Alarm-Session erzeugen keinen zusätzlichen Einschaltbefehl.

Bei Quittierung, automatischem Alarmende oder vollständigem Ausschalten während eines aktiven Alarms wird AUS angefordert. Anschließend liest die Alarmanlage alle 10 Sekunden ausschließlich die lokale PowerFix-Statusvariable. Diese Kontrolle läuft höchstens 60 Sekunden und fängt auch den Fall ab, dass ein kurz vor der Quittierung per WOL gestarteter TV erst verzögert hochfährt. Danach gibt es höchstens einen letzten OFF-Impuls und eine Abschlusskontrolle; es entsteht keine Endlosschleife.

Eine neue aktive Alarm-Session hat immer Vorrang vor einem alten Abschaltauftrag.

### Noch nicht enthalten

Der spezielle rote/blinkende Alarmbildschirm mit Sirenenton auf dem Fernseher ist bewusst noch nicht Bestandteil von 0.1.6. Zuerst wird die reine Powersteuerung real getestet.

### Push

Die Auswahl `Kachelvisualisierung für Push` wird in 0.1.6 dynamisch auf Module mit dem Symcon-Präfix `VISU` eingeschränkt. Zusätzlich wird die gewählte Instanz beim Anwenden geprüft.

### Update

Bibliotheks-GUID, Modul-GUID und Prefix bleiben unverändert. Den Inhalt des bestehenden Repository-Ordners ersetzen; keine neue Alarmanlagen-Instanz anlegen. Neue TV-Funktionen sind nach dem Update standardmäßig AUS und erzeugen daher beim Update keine Hardwarebefehle.
