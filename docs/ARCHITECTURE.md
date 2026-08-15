# Architektur 0.1.2

## Sicherheitskern

Der Alarmkern aus 0.1.1 bleibt zentrale Zustandsmaschine. GUS-Ereignisse werden unter einer instanzbezogenen Semaphore serialisiert; externe Aktionen laufen außerhalb dieses kurzen kritischen Bereichs.

## GUS

`LCN-GUS -> nativer LCN-Binärstatus -> Symcon Boolean -> VM_UPDATE -> Alarm-Engine`

Kein Polling, kein virtuelles Relais, keine LED, keine LCN-Hilfsvariable.

## Panikgruppe

Das Alarmmodul erhält nur die Statusvariable einer bereits vorhandenen Lichtgruppe. Über `RequestActionEx` wird derselbe definierte Aktionsweg wie bei einer normalen Symcon-Bedienung verwendet. Zielwerte sind 1=EIN und 0=AUS.

Vor PANIK EIN und unmittelbar danach wird geprüft, ob dieselbe Alarm-Session noch aktiv ist. Wurde zwischenzeitlich quittiert, folgt sofort ein deterministisches PANIK AUS.

## Quittier-Lichter

Die ausgewählten Boolean-Statusvariablen der LCN-Light-Instanzen werden nur auf `VM_UPDATE` abonniert. Das Alarmmodul hält eine lokale Baseline. Nur `true -> false` bei `CurrentSession.state == active` wird als Quittierung akzeptiert.

Dadurch gilt:

- PANIK EIN erzeugt false->true: keine Quittierung
- PANIK AUS erfolgt nach Sessionwechsel auf rearm_wait: keine Quittierung
- mehrere nahezu gleichzeitige AUS-Flanken sind harmlos; nur die erste kann die aktive Session quittieren

Die Quittierung ist damit absichtlich **zustandsbasiert und nicht Telegramm-basiert**: Das Alarmmodul muss kein undokumentiertes GT8/GT2-Empfangsformat auswerten. Jede reale externe Ausschaltung eines freigegebenen Paniklichts während einer aktiven Session gilt als Quittierung.

## Update-/Rollback-Regeln

GUIDs, Prefix und bestehende Property-/Ident-Namen nicht ändern. Neue Properties haben neutrale Defaults. Keine Hardwareaktionen durch ein Update, solange keine aktive Alarm-Session rekonstruiert werden muss.
