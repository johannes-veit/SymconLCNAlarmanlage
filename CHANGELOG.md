# Changelog

## 0.1.1 – 2026-08-15

- Quittierung korrigiert: `Alarm deaktivieren` beendet nur die aktuelle Alarm-Session; `Alarmanlage EIN/AUS` bleibt EIN.
- Nach Quittierung oder automatischem Alarmende bleibt die Session verriegelt, bis alle GUS frei sind und die Wieder-Scharf-Verzögerung abgelaufen ist.
- Status während aktiver GUS: `Warte auf freie Bewegungsmelder`.
- Sichtbarer Countdown: `Wieder scharf in N s`.
- Neuer lokaler 1-s-Anzeigetimer ausschließlich während des Countdowns; erzeugt keinerlei LCN-Abfragen oder Busverkehr.
- Endgrund der Session unterscheidet nun `acknowledged` und `automatic-timeout`.
- GUIDs, Prefix, Properties, Sensorzuordnung und bestehende Instanzkonfiguration bleiben unverändert.

## 0.1.0 – 2026-08-15

- Erstversion des Alarm-Kerns.
- Native Boolean-GUS-Eingänge als ereignisgesteuerte Alarmquellen.
- Keine zusätzlichen LCN-Relais, LEDs oder Hilfsvariablen.
- Kein zyklisches Polling.
- Zentrale Alarm-Session mit Semaphore-Kollisionsschutz.
- Scharf-/Unscharf-Zustand und Sensorverarbeitung verwenden denselben Kollisionsschutz.
- Lokale Erstalarm-Aktivierung wird atomar mit der Session erzeugt, sodass eine gleichzeitige Quittierung keinen verspäteten Alarmzustand hinterlassen kann.
- Bewegungsprofil mehrerer und gleichzeitig aktiver GUS.
- Alarm-Timeout und kontrollierte Wiederbereitschaft.
- Manuelle Scharfschaltung sowie Zeitautomatik mit manueller Übersteuerung.
- Persistente Sessiondaten und Neustart-Rekonstruktion.
- Initialisierungs-Gate verhindert historische Alarme durch bereits aktive GUS.
- Früher Timer-Aufruf der Wiederbereitschaft wird sicher neu terminiert.
- Sensorausfall/Konfigurationsfehler führt zu sicherem Unscharfschalten.
