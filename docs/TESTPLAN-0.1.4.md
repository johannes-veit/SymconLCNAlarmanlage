# Testplan 0.1.4 – Push und E-Mail

## Vorbedingungen

- 0.1.3 Alarmkern/Paniklicht real getestet
- bestehende GUS- und Panik-Konfiguration unverändert
- Push/E-Mail direkt nach Update zunächst AUS
- vorhandene Kachelvisualisierung und SMTP-Instanz identifiziert

## T01 Update ohne Seiteneffekt

1. 0.1.3 Instanz auf 0.1.4 aktualisieren.
2. Prüfen: GUS, Paniklichter, Zeiten und Hauptschalter unverändert.
3. Prüfen: keine Push-Nachricht, keine E-Mail, kein LCN-Befehl allein durch Update.

## T02 Push einzeln

1. Kachelvisualisierung auswählen, Push aktivieren, E-Mail noch AUS.
2. Anlage scharf, einen GUS auslösen.
3. Erwartet: genau eine Push-Nachricht `ALARM AUSGELÖST!` mit Erstauslöser/Zeit.
4. Push antippen: Alarmanlagen-Kachel öffnet sich.
5. Weitere GUS auslösen: keine zweite Push-Nachricht.

## T03 E-Mail an zwei Empfänger

1. SMTP-Instanz auswählen.
2. Zwei gültige Empfänger mit Semikolon eintragen, E-Mail aktivieren.
3. Neue Alarm-Session auslösen.
4. Erwartet: jeder Empfänger erhält genau eine Mail mit Betreff `ALARM ausgelöst!`, Alarm-ID, Erstauslöser und Zeit.
5. Weitere GUS derselben Session: keine weitere Mail.

## T04 Parallelität

Mehrere GUS nahezu gleichzeitig auslösen. Erwartet: eine Session, ein Push, je eine Mail pro Empfänger; Bewegungsprofil enthält alle Bewegungen.

## T05 Quittierung während Benachrichtigung

Alarm auslösen und sofort über Panik-Licht bzw. Symcon quittieren. Erwartet: Alarmkern reagiert sofort; SMTP darf die Quittierung nicht blockieren. Hauptschalter bleibt EIN.

## T06 Benachrichtigungsfehler

Testweise ungültige/nicht erreichbare SMTP-Konfiguration verwenden. Erwartet: Alarm und Paniklicht funktionieren weiter; Fehler nur im Log/Debug.

## T07 Neustart

Nach abgeschlossener Benachrichtigung Symcon neu starten. Erwartet: keine Wiederholungs-Pushs und keine Wiederholungs-Mails für dieselbe Session.

## T08 Regression 0.1.3

GUS-Mehrfachtest, Panik-EIN/AUS, Quittierung über Licht, Timeout und Wieder-scharf-Countdown erneut prüfen.
