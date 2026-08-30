# Testplan 0.1.24 – Symcon Connect / mobile Bedienung

1. Automatik EIN, innerhalb Scharfzeit: Anlage ist automatisch SCHARF.
2. Auf Smartphone `Alarmanlage` manuell AUS schalten. Erwartung: Arm AUS, Automatik bleibt EIN, kein Fehlerdialog.
3. Prüfen, dass der Schalter nicht zurückspringt und der bestätigte Zustand per Push ankommt.
4. Netzwerk-/Debugkontrolle: nach normal bestätigter Bedienung kein zusätzlicher `RefreshVisualization`-RPC nach 900 ms.
5. Automatik nächste Zeitgrenze: manueller Override wird wie bisher beendet und Zeitplan übernimmt wieder.
6. Alarmanlage EIN/AUS, Automatik EIN/AUS, GUS-Schalter sowie Uhrzeiten mobil bedienen.
7. Desktop-Gegenprüfung derselben Bedienelemente.
8. Regression: Alarmstart, Quittierung, Paniklicht-Restore, TV/WOL/Video, Neustartschutz unverändert.
