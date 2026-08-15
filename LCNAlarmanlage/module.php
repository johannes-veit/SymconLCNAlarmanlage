<?php

declare(strict_types=1);

class LCNAlarmanlage extends IPSModuleStrict
{
    private const SESSION_NONE = '';
    private const SESSION_ACTIVE = 'active';
    private const SESSION_REARM_WAIT = 'rearm_wait';

    private const OVERRIDE_NONE = 0;
    private const OVERRIDE_ON = 1;
    private const OVERRIDE_OFF = 2;

    private const MAX_EVENTS_PER_SESSION = 1000;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Sensors', '[]');
        $this->RegisterPropertyInteger('AlarmDurationSeconds', 300);
        $this->RegisterPropertyInteger('RearmDelaySeconds', 60);

        $this->RegisterAttributeInteger('ManualOverride', self::OVERRIDE_NONE);
        $this->RegisterAttributeBoolean('ArmedReady', false);
        $this->RegisterAttributeString('CurrentSession', '{}');
        $this->RegisterAttributeString('LastSession', '{}');
        $this->RegisterAttributeInteger('SessionCounter', 0);
        $this->RegisterAttributeInteger('RearmNotBefore', 0);
        $this->RegisterAttributeString('RegisteredSensorIDs', '[]');

        $created = $this->RegisterVariableBoolean(
            'Arm',
            'Alarmanlage EIN/AUS',
            ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH],
            10
        );
        if ($created) {
            $this->SetValue('Arm', false);
        }
        $this->EnableAction('Arm');

        $created = $this->RegisterVariableString(
            'Status',
            'Status',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            20
        );
        if ($created) {
            $this->SetValue('Status', 'ALARMANLAGE AUS');
        }

        $created = $this->RegisterVariableBoolean(
            'Automatic',
            'Automatik',
            ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH],
            30
        );
        if ($created) {
            // Sicherheitsvorgabe: niemals nach Erstinstallation automatisch scharf werden.
            $this->SetValue('Automatic', false);
        }
        $this->EnableAction('Automatic');

        $created = $this->RegisterVariableString(
            'AutoFrom',
            'Scharf ab',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT],
            40
        );
        if ($created) {
            $this->SetValue('AutoFrom', '22:00');
        }
        $this->EnableAction('AutoFrom');

        $created = $this->RegisterVariableString(
            'AutoTo',
            'Unscharf ab',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT],
            50
        );
        if ($created) {
            $this->SetValue('AutoTo', '06:00');
        }
        $this->EnableAction('AutoTo');

        $created = $this->RegisterVariableBoolean(
            'AlarmActive',
            'ALARM AUSGELÖST',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            60
        );
        if ($created) {
            $this->SetValue('AlarmActive', false);
        }

        $created = $this->RegisterVariableBoolean(
            'Acknowledge',
            'Alarm deaktivieren',
            ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH],
            70
        );
        if ($created) {
            $this->SetValue('Acknowledge', false);
        }
        $this->EnableAction('Acknowledge');

        $created = $this->RegisterVariableString(
            'FirstTrigger',
            'Erstauslöser',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            80
        );
        if ($created) {
            $this->SetValue('FirstTrigger', '-');
        }

        $created = $this->RegisterVariableString(
            'LastMovement',
            'Letzte Bewegung',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            90
        );
        if ($created) {
            $this->SetValue('LastMovement', '-');
        }

        $created = $this->RegisterVariableInteger(
            'MotionCount',
            'Bewegungen',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            100
        );
        if ($created) {
            $this->SetValue('MotionCount', 0);
        }

        $created = $this->RegisterVariableString(
            'MotionLog',
            'Bewegungsprofil',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            110
        );
        if ($created) {
            $this->SetValue('MotionLog', '-');
        }

        $created = $this->RegisterVariableString(
            'LastAlarm',
            'Letzter Alarm',
            ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION],
            120
        );
        if ($created) {
            $this->SetValue('LastAlarm', '-');
        }

        $this->RegisterTimer('AlarmTimeout', 0, 'LCNALARM_AlarmTimeout($_IPS[\'TARGET\']);');
        $this->RegisterTimer('RearmTimeout', 0, 'LCNALARM_RearmTimeout($_IPS[\'TARGET\']);');
        $this->RegisterTimer('ScheduleTimer', 0, 'LCNALARM_ScheduleTimer($_IPS[\'TARGET\']);');

        $this->RegisterMessage(0, IPS_KERNELSTARTED);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Interne Timer sind stateless und werden nach jedem ApplyChanges bewusst neu aufgebaut.
        $this->SetTimerInterval('AlarmTimeout', 0);
        $this->SetTimerInterval('RearmTimeout', 0);
        $this->SetTimerInterval('ScheduleTimer', 0);

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->SetValue('Status', 'INITIALISIERUNG');
            return;
        }

        $this->InitializeRuntime();
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === IPS_KERNELSTARTED) {
            $this->InitializeRuntime();
            return;
        }

        if ($Message === OM_UNREGISTER) {
            $this->HandleSensorUnavailable($SenderID);
            return;
        }

        if ($Message !== VM_UPDATE) {
            return;
        }

        // $Data wird absichtlich nicht ausgewertet. Der aktuelle Zustand wird dokumentiert
        // direkt über die SenderID aus der Boolean-Variable gelesen.
        $this->ProcessSensorUpdate($SenderID);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Arm':
                $armed = (bool) $Value;
                $this->WriteAttributeInteger('ManualOverride', $armed ? self::OVERRIDE_ON : self::OVERRIDE_OFF);
                $this->SetArmedInternal($armed, 'manual');
                $this->ScheduleNextBoundary();
                break;

            case 'Automatic':
                $automatic = (bool) $Value;
                $this->SetValue('Automatic', $automatic);
                $this->WriteAttributeInteger('ManualOverride', self::OVERRIDE_NONE);
                if ($automatic) {
                    $this->ApplyCurrentSchedule('automatic-enabled');
                }
                $this->ScheduleNextBoundary();
                break;

            case 'AutoFrom':
            case 'AutoTo':
                $time = trim((string) $Value);
                if (!$this->IsValidTime($time)) {
                    throw new Exception('Uhrzeit muss im Format HH:MM eingegeben werden.');
                }
                $this->SetValue($Ident, $time);
                $this->WriteAttributeInteger('ManualOverride', self::OVERRIDE_NONE);
                if ((bool) $this->GetValue('Automatic')) {
                    $this->ApplyCurrentSchedule('time-changed');
                }
                $this->ScheduleNextBoundary();
                break;

            case 'Acknowledge':
                if ((bool) $Value) {
                    $this->AcknowledgeAlarm();
                }
                $this->SetValue('Acknowledge', false);
                break;

            default:
                throw new Exception('Ungültige Aktion: ' . $Ident);
        }
    }

    /** Externe, dokumentierte Bedienfunktion für spätere Quittierungswege. */
    public function SetArmed(bool $Armed): void
    {
        $this->WriteAttributeInteger('ManualOverride', $Armed ? self::OVERRIDE_ON : self::OVERRIDE_OFF);
        $this->SetArmedInternal($Armed, 'external');
        $this->ScheduleNextBoundary();
    }

    /** Zentraler Quittierungsweg. Spätere GT8/GT2/Push/E-Mail-Quittierungen rufen nur diese Funktion auf. */
    public function AcknowledgeAlarm(): void
    {
        $this->WriteAttributeInteger('ManualOverride', self::OVERRIDE_OFF);
        $this->SetArmedInternal(false, 'acknowledged');
        $this->SetValue('Acknowledge', false);
        $this->ScheduleNextBoundary();
    }

    /** Einmal-Timer: beendet die aktive Alarmsignalisierung. */
    public function AlarmTimeout(): void
    {
        $this->SetTimerInterval('AlarmTimeout', 0);

        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('AlarmTimeout');
            return;
        }

        $startRearmTimer = false;
        try {
            $session = $this->ReadSession('CurrentSession');
            if (($session['state'] ?? self::SESSION_NONE) !== self::SESSION_ACTIVE) {
                return;
            }

            $session['state'] = self::SESSION_REARM_WAIT;
            $session['signalEndedAt'] = microtime(true);
            $this->WriteSession('CurrentSession', $session);
            $this->WriteAttributeBoolean('ArmedReady', false);

            $states = $this->ReadSensorStates();
            if ($this->AreAllSensorsClear($states)) {
                $delay = max(0, $this->ReadPropertyInteger('RearmDelaySeconds'));
                $notBefore = time() + $delay;
                $this->WriteAttributeInteger('RearmNotBefore', $notBefore);
                $startRearmTimer = true;
            } else {
                $this->WriteAttributeInteger('RearmNotBefore', 0);
            }
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }

        $this->SetValue('AlarmActive', false);
        $this->SetAlarmControlsVisible(false);

        if ($startRearmTimer) {
            $this->ScheduleRearmFromAttribute();
        }

        $this->RefreshDisplay();
    }

    /** Einmal-Timer: Anlage nach Timeout erst nach freiem Melderfeld wieder bereit setzen. */
    public function RearmTimeout(): void
    {
        $this->SetTimerInterval('RearmTimeout', 0);

        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('RearmTimeout');
            return;
        }

        $rearmed = false;
        $reschedule = false;
        try {
            $session = $this->ReadSession('CurrentSession');
            if (($session['state'] ?? self::SESSION_NONE) === self::SESSION_REARM_WAIT) {
                $states = $this->ReadSensorStates();

                if (!$this->AreAllSensorsClear($states)) {
                    // Ein aktiver Melder hebt die laufende Ruhezeit auf. Die nächste
                    // CLEAR-Flanke aller Melder startet sie erneut.
                    $this->WriteAttributeInteger('RearmNotBefore', 0);
                } else {
                    $notBefore = $this->ReadAttributeInteger('RearmNotBefore');
                    if ($notBefore <= 0) {
                        $this->WriteAttributeInteger(
                            'RearmNotBefore',
                            time() + max(0, $this->ReadPropertyInteger('RearmDelaySeconds'))
                        );
                        $reschedule = true;
                    } elseif ($notBefore > time()) {
                        // Timer können systembedingt minimal zu früh aufgerufen werden.
                        // In diesem Fall wird der verbleibende Einmal-Timer neu gesetzt.
                        $reschedule = true;
                    } else {
                        $session['endedAt'] = microtime(true);
                        $session['endReason'] = 'automatic-timeout';
                        $session['state'] = 'ended';
                        $this->WriteSession('LastSession', $session);
                        $this->WriteSession('CurrentSession', []);
                        $this->WriteAttributeInteger('RearmNotBefore', 0);

                        $armed = (bool) $this->GetValue('Arm');
                        $this->WriteAttributeBoolean('ArmedReady', $armed);
                        $rearmed = $armed;
                    }
                }
            }
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }

        if ($reschedule) {
            $this->ScheduleRearmFromAttribute();
        }

        if ($rearmed) {
            $this->SetValue('AlarmActive', false);
        }

        $this->RefreshDisplay();
    }

    /** Einmal-Timer für die nächste EIN- oder AUS-Zeitgrenze. */
    public function ScheduleTimer(): void
    {
        $this->SetTimerInterval('ScheduleTimer', 0);

        if (!(bool) $this->GetValue('Automatic')) {
            return;
        }

        // Eine Zeitgrenze beendet eine bestehende manuelle Übersteuerung.
        $this->WriteAttributeInteger('ManualOverride', self::OVERRIDE_NONE);
        $this->ApplyCurrentSchedule('schedule-boundary');
        $this->ScheduleNextBoundary();
    }

    private function InitializeRuntime(): void
    {
        // Während der Rekonstruktion dürfen eintreffende Sensorupdates nur die Baseline
        // aktualisieren, aber niemals einen historischen Alarm erzeugen.
        $this->SetBuffer('RuntimeReady', '0');

        $this->SetTimerInterval('AlarmTimeout', 0);
        $this->SetTimerInterval('RearmTimeout', 0);
        $this->SetTimerInterval('ScheduleTimer', 0);

        $this->UnregisterOldSensorMessages();

        [$sensorMap, $errors] = $this->BuildSensorMap();
        $this->SetBuffer('SensorMap', $this->Encode($sensorMap));

        if ($errors !== []) {
            $this->SetBuffer('ConfigurationOK', '0');
            $this->SetTimerInterval('ScheduleTimer', 0);
            $this->SetSummary('Konfigurationsfehler');
            try {
                $this->SetArmedInternal(false, 'configuration-error');
            } catch (Throwable $e) {
                IPS_LogMessage('LCN Alarmanlage #' . $this->InstanceID, 'Abschalten bei Konfigurationsfehler fehlgeschlagen: ' . $e->getMessage());
            }
            $this->SetValue('AlarmActive', false);
            $this->SetAlarmControlsVisible(false);
            $this->SetValue('Status', 'STÖRUNG – ' . implode('; ', $errors));
            $this->SendDebug('Configuration', implode(' | ', $errors), 0);
            return;
        }

        if ($sensorMap === []) {
            $this->SetBuffer('ConfigurationOK', '0');
            $this->SetTimerInterval('ScheduleTimer', 0);
            $this->SetSummary('Keine GUS');
            try {
                $this->SetArmedInternal(false, 'no-sensors');
            } catch (Throwable $e) {
                IPS_LogMessage('LCN Alarmanlage #' . $this->InstanceID, 'Abschalten ohne konfigurierte Melder fehlgeschlagen: ' . $e->getMessage());
            }
            $this->SetValue('AlarmActive', false);
            $this->SetAlarmControlsVisible(false);
            $this->SetValue('Status', 'KONFIGURATION – keine GUS ausgewählt');
            return;
        }

        $this->SetBuffer('ConfigurationOK', '1');

        $states = [];
        foreach ($sensorMap as $variableID => $sensor) {
            $states[(string) $variableID] = (bool) GetValue((int) $variableID);
            $this->RegisterMessage((int) $variableID, VM_UPDATE);
            $this->RegisterMessage((int) $variableID, OM_UNREGISTER);
            $this->RegisterReference((int) $variableID);
        }
        $this->SetBuffer('SensorStates', $this->Encode($states));
        $this->WriteAttributeString('RegisteredSensorIDs', $this->Encode(array_map('intval', array_keys($sensorMap))));
        $this->SetSummary(count($sensorMap) . ' GUS');

        $session = $this->ReadSession('CurrentSession');
        $sessionState = (string) ($session['state'] ?? self::SESSION_NONE);

        if ($sessionState === self::SESSION_ACTIVE) {
            if (!(bool) $this->GetValue('Arm')) {
                // Inkonsistenz nach Abbruch während einer Abschaltung: über denselben
                // kollisionsgeschützten Pfad wie jede andere Abschaltung rekonstruieren.
                $this->SetArmedInternal(false, 'restart-reconcile');
            } else {
                $this->SetValue('AlarmActive', true);
                $this->WriteAttributeBoolean('ArmedReady', false);
                $this->SetAlarmControlsVisible(true);
                $duration = max(10, $this->ReadPropertyInteger('AlarmDurationSeconds'));
                $startedAt = (float) ($session['startedAt'] ?? microtime(true));
                $remainingMs = (int) round((($startedAt + $duration) - microtime(true)) * 1000);
                if ($remainingMs <= 0) {
                    $this->AlarmTimeout();
                } else {
                    $this->SetTimerInterval('AlarmTimeout', max(1, $remainingMs));
                }
            }
        } elseif ($sessionState === self::SESSION_REARM_WAIT) {
            $this->SetValue('AlarmActive', false);
            $this->WriteAttributeBoolean('ArmedReady', false);
            $this->SetAlarmControlsVisible(false);
            if ($this->AreAllSensorsClear($states)) {
                if ($this->ReadAttributeInteger('RearmNotBefore') <= 0) {
                    $this->WriteAttributeInteger(
                        'RearmNotBefore',
                        time() + max(0, $this->ReadPropertyInteger('RearmDelaySeconds'))
                    );
                }
                $this->ScheduleRearmFromAttribute();
            }
        } else {
            $this->SetValue('AlarmActive', false);
            $this->SetAlarmControlsVisible(false);

            if ((bool) $this->GetValue('Automatic') && $this->ReadAttributeInteger('ManualOverride') === self::OVERRIDE_NONE) {
                $this->ApplyCurrentSchedule('startup');
            } else {
                $this->ApplyDesiredArmState((bool) $this->GetValue('Arm'));
            }
        }

        // Baseline und persistenter Zustand sind jetzt konsistent. Ab hier dürfen
        // neue FALSE->TRUE-Flanken als echte Bewegungsereignisse ausgewertet werden.
        $this->SetBuffer('RuntimeReady', '1');

        $this->ScheduleNextBoundary();
        $this->RefreshDisplay();
    }

    private function ProcessSensorUpdate(int $VariableID): void
    {
        $sensorMap = $this->ReadSensorMap();
        if (!isset($sensorMap[(string) $VariableID]) && !isset($sensorMap[$VariableID])) {
            return;
        }

        if (!IPS_VariableExists($VariableID)) {
            $this->HandleSensorUnavailable($VariableID);
            return;
        }

        $newValue = (bool) GetValue($VariableID);

        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('ProcessSensorUpdate #' . $VariableID);
            return;
        }

        $firstAlarmTrigger = false;
        $scheduleRearm = false;
        $cancelRearm = false;
        $changed = false;

        try {
            $states = $this->ReadSensorStates();
            $key = (string) $VariableID;

            // Fehlender Baseline-Wert ist Initialisierung, kein Alarmereignis.
            if (!array_key_exists($key, $states)) {
                $states[$key] = $newValue;
                $this->SetBuffer('SensorStates', $this->Encode($states));
                return;
            }

            $oldValue = (bool) $states[$key];
            if ($oldValue === $newValue) {
                return;
            }

            $states[$key] = $newValue;
            $this->SetBuffer('SensorStates', $this->Encode($states));
            $changed = true;

            // ApplyChanges/Kernelstart: Statusänderungen werden als aktuelle Baseline
            // übernommen. Damit kann ein bereits aktiver GUS niemals durch die
            // Initialisierung einen historischen Fehlalarm auslösen.
            if ($this->GetBuffer('RuntimeReady') !== '1') {
                return;
            }

            $session = $this->ReadSession('CurrentSession');
            $sessionState = (string) ($session['state'] ?? self::SESSION_NONE);

            if ($sessionState !== self::SESSION_NONE) {
                $this->AppendSessionEvent($session, $VariableID, $newValue ? 'motion' : 'clear');
                $this->WriteSession('CurrentSession', $session);

                if ($sessionState === self::SESSION_REARM_WAIT) {
                    if ($newValue) {
                        $this->WriteAttributeInteger('RearmNotBefore', 0);
                        $cancelRearm = true;
                    } elseif ($this->AreAllSensorsClear($states)) {
                        $this->WriteAttributeInteger(
                            'RearmNotBefore',
                            time() + max(0, $this->ReadPropertyInteger('RearmDelaySeconds'))
                        );
                        $scheduleRearm = true;
                    }
                }
            } elseif ((bool) $this->GetValue('Arm')) {
                if (!(bool) $this->ReadAttributeBoolean('ArmedReady')) {
                    if (!$newValue && $this->AreAllSensorsClear($states)) {
                        $this->WriteAttributeBoolean('ArmedReady', true);
                    }
                } elseif ($newValue) {
                    $session = $this->CreateAlarmSession($VariableID);
                    $this->WriteSession('CurrentSession', $session);
                    $this->WriteAttributeBoolean('ArmedReady', false);

                    // Alle schnellen, lokalen Zustandsänderungen der ersten Auslösung
                    // werden noch unter derselben Semaphore gesetzt. Dadurch kann eine
                    // exakt gleichzeitig eintreffende manuelle Abschaltung sie danach
                    // zuverlässig wieder aufheben; außerhalb der Semaphore wird nichts
                    // erneut auf ALARM gesetzt.
                    $this->SetValue('AlarmActive', true);
                    $this->SetValue('Acknowledge', false);
                    $this->SetAlarmControlsVisible(true);
                    $duration = max(10, $this->ReadPropertyInteger('AlarmDurationSeconds'));
                    $this->SetTimerInterval('AlarmTimeout', $duration * 1000);
                    $firstAlarmTrigger = true;
                }
            }
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }

        if (!$changed) {
            return;
        }

        if ($cancelRearm) {
            $this->SetTimerInterval('RearmTimeout', 0);
        }

        if ($scheduleRearm) {
            $this->ScheduleRearmFromAttribute();
        }

        if ($firstAlarmTrigger) {
            // Hier später nur nicht-kritische Folgeschritte anstoßen. Vor externen
            // Aktionen (Panik/Push/TV/E-Mail) wird die Session-ID erneut geprüft.
            $this->SendDebug('ALARM', 'Neue Alarm-Session durch Variable #' . $VariableID, 0);
        }

        $this->RefreshDisplay();
    }

    private function HandleSensorUnavailable(int $VariableID): void
    {
        // Ab diesem Zeitpunkt darf keine Automatik die Anlage wieder scharf setzen.
        $this->SetBuffer('ConfigurationOK', '0');
        $this->SetBuffer('RuntimeReady', '0');
        $this->SetTimerInterval('ScheduleTimer', 0);
        $this->WriteAttributeInteger('ManualOverride', self::OVERRIDE_OFF);

        try {
            $this->SetArmedInternal(false, 'sensor-unavailable');
        } catch (Throwable $e) {
            IPS_LogMessage(
                'LCN Alarmanlage #' . $this->InstanceID,
                'Sicheres Abschalten nach Sensorausfall fehlgeschlagen: ' . $e->getMessage()
            );
        }

        $this->SetValue('AlarmActive', false);
        $this->SetAlarmControlsVisible(false);
        $this->SetSummary('Sensorstörung');
        $this->SetValue('Status', 'STÖRUNG – Bewegungsmelder nicht verfügbar (#' . $VariableID . ')');
        IPS_LogMessage(
            'LCN Alarmanlage #' . $this->InstanceID,
            'Konfigurierter Bewegungsmelder ist nicht mehr verfügbar: #' . $VariableID
        );
    }

    /**
     * Einziger Pfad für Scharf/Unscharf. Arm, ArmedReady und CurrentSession werden
     * unter derselben Semaphore geändert wie Sensorereignisse. Damit kann eine
     * gleichzeitige GUS-Flanke nicht mit einer manuellen/automatischen Abschaltung
     * kollidieren.
     */
    private function SetArmedInternal(bool $Armed, string $Reason): void
    {
        if ($Armed && $this->GetBuffer('ConfigurationOK') !== '1') {
            throw new Exception('Alarmanlage kann wegen fehlerhafter oder fehlender Melderkonfiguration nicht scharf geschaltet werden.');
        }

        if (!$this->AcquireEngineLock()) {
            $this->ReportLockFailure('SetArmedInternal/' . $Reason);
            throw new Exception('Alarmanlage konnte wegen einer internen Zugriffskollision nicht sicher geschaltet werden.');
        }

        $stopAlarmTimer = false;
        $stopRearmTimer = false;
        $hideAlarmControls = false;
        $alarmBecameInactive = false;

        try {
            $this->SetValue('Arm', $Armed);

            if (!$Armed) {
                // Zuerst intern unscharf und Session beenden. Externe/langsame Aktionen
                // werden in späteren Versionen erst NACH diesem kritischen Abschnitt ausgeführt.
                $this->WriteAttributeBoolean('ArmedReady', false);
                $this->WriteAttributeInteger('RearmNotBefore', 0);
                $this->FinalizeCurrentSession($Reason);

                $stopAlarmTimer = true;
                $stopRearmTimer = true;
                $hideAlarmControls = true;
                $alarmBecameInactive = true;
            } else {
                $session = $this->ReadSession('CurrentSession');
                if ($session === []) {
                    $states = $this->ReadSensorStates();
                    $this->WriteAttributeBoolean('ArmedReady', $this->AreAllSensorsClear($states));
                }
                // Eine vorhandene aktive/rearm_wait-Session wird niemals durch ein
                // erneutes EIN überschrieben.
            }
        } finally {
            IPS_SemaphoreLeave($this->EngineSemaphoreName());
        }

        if ($stopAlarmTimer) {
            $this->SetTimerInterval('AlarmTimeout', 0);
        }
        if ($stopRearmTimer) {
            $this->SetTimerInterval('RearmTimeout', 0);
        }
        if ($alarmBecameInactive) {
            $this->SetValue('AlarmActive', false);
        }
        if ($hideAlarmControls) {
            $this->SetAlarmControlsVisible(false);
        }

        $this->RefreshDisplay();
    }

    private function ApplyDesiredArmState(bool $Armed): void
    {
        if (!$Armed) {
            $this->SetValue('Arm', false);
            $this->WriteAttributeBoolean('ArmedReady', false);
            return;
        }

        $this->SetValue('Arm', true);
        $states = $this->ReadSensorStates();
        $this->WriteAttributeBoolean('ArmedReady', $this->AreAllSensorsClear($states));
    }

    private function ApplyCurrentSchedule(string $Reason): void
    {
        if (!(bool) $this->GetValue('Automatic')) {
            return;
        }

        $from = (string) $this->GetValue('AutoFrom');
        $to = (string) $this->GetValue('AutoTo');
        if (!$this->IsValidTime($from) || !$this->IsValidTime($to)) {
            $this->SetValue('Status', 'STÖRUNG – ungültige Automatikzeit');
            return;
        }

        $this->SetArmedInternal($this->IsInsideSchedule($from, $to), $Reason);
    }

    private function ScheduleNextBoundary(): void
    {
        $this->SetTimerInterval('ScheduleTimer', 0);

        if (!(bool) $this->GetValue('Automatic') || $this->GetBuffer('ConfigurationOK') !== '1') {
            return;
        }

        $from = (string) $this->GetValue('AutoFrom');
        $to = (string) $this->GetValue('AutoTo');
        if (!$this->IsValidTime($from) || !$this->IsValidTime($to) || $from === $to) {
            return;
        }

        $now = new DateTimeImmutable('now');
        $nextFrom = $this->NextOccurrence($now, $from);
        $nextTo = $this->NextOccurrence($now, $to);
        $next = ($nextFrom <= $nextTo) ? $nextFrom : $nextTo;
        $milliseconds = max(1000, (int) (($next->getTimestamp() - $now->getTimestamp()) * 1000));
        $this->SetTimerInterval('ScheduleTimer', $milliseconds);
    }

    private function ScheduleRearmFromAttribute(): void
    {
        $notBefore = $this->ReadAttributeInteger('RearmNotBefore');
        if ($notBefore <= 0) {
            $this->SetTimerInterval('RearmTimeout', 0);
            return;
        }

        $milliseconds = max(1, ($notBefore - time()) * 1000);
        $this->SetTimerInterval('RearmTimeout', $milliseconds);
    }

    private function CreateAlarmSession(int $VariableID): array
    {
        $counter = $this->ReadAttributeInteger('SessionCounter') + 1;
        $this->WriteAttributeInteger('SessionCounter', $counter);

        $sensorName = $this->SensorName($VariableID);
        $now = microtime(true);

        return [
            'id' => date('Ymd-His') . '-' . sprintf('%04d', $counter % 10000),
            'state' => self::SESSION_ACTIVE,
            'startedAt' => $now,
            'signalEndedAt' => 0,
            'endedAt' => 0,
            'endReason' => '',
            'firstSensorID' => $VariableID,
            'firstSensorName' => $sensorName,
            'events' => [
                [
                    'seq' => 1,
                    'ts' => $now,
                    'sensorID' => $VariableID,
                    'sensor' => $sensorName,
                    'event' => 'motion'
                ]
            ]
        ];
    }

    private function AppendSessionEvent(array &$Session, int $VariableID, string $Event): void
    {
        if (!isset($Session['events']) || !is_array($Session['events'])) {
            $Session['events'] = [];
        }

        if (count($Session['events']) >= self::MAX_EVENTS_PER_SESSION) {
            return;
        }

        $Session['events'][] = [
            'seq' => count($Session['events']) + 1,
            'ts' => microtime(true),
            'sensorID' => $VariableID,
            'sensor' => $this->SensorName($VariableID),
            'event' => $Event
        ];
    }

    private function FinalizeCurrentSession(string $Reason): void
    {
        $session = $this->ReadSession('CurrentSession');
        if ($session === []) {
            return;
        }

        $session['endedAt'] = microtime(true);
        $session['endReason'] = $Reason;
        $session['state'] = 'ended';
        $this->WriteSession('LastSession', $session);
        $this->WriteSession('CurrentSession', []);
        $this->WriteAttributeInteger('RearmNotBefore', 0);
    }

    private function RefreshDisplay(): void
    {
        if ($this->GetBuffer('ConfigurationOK') !== '1') {
            return;
        }

        $current = $this->ReadSession('CurrentSession');
        $last = $this->ReadSession('LastSession');
        $session = ($current !== []) ? $current : $last;

        $currentState = (string) ($current['state'] ?? self::SESSION_NONE);
        if ($currentState === self::SESSION_ACTIVE) {
            $this->SetValue('Status', 'ALARM AUSGELÖST');
        } elseif ($currentState === self::SESSION_REARM_WAIT) {
            if ($this->AreAllSensorsClear($this->ReadSensorStates())) {
                $this->SetValue('Status', 'ALARM BEENDET – Wiederbereitschaft');
            } else {
                $this->SetValue('Status', 'ALARM BEENDET – warte auf freie Melder');
            }
        } elseif (!(bool) $this->GetValue('Arm')) {
            $this->SetValue('Status', 'ALARMANLAGE AUS');
        } elseif ($this->ReadAttributeBoolean('ArmedReady')) {
            $this->SetValue('Status', 'ALARMANLAGE SCHARF');
        } else {
            $this->SetValue('Status', 'SCHARFSCHALTUNG – warte auf freie Melder');
        }

        if ($session === []) {
            $this->SetValue('FirstTrigger', '-');
            $this->SetValue('LastMovement', '-');
            $this->SetValue('MotionCount', 0);
            $this->SetValue('MotionLog', '-');
            return;
        }

        $firstName = (string) ($session['firstSensorName'] ?? '-');
        $startedAt = (float) ($session['startedAt'] ?? 0);
        $this->SetValue('FirstTrigger', $firstName . (($startedAt > 0) ? ' – ' . $this->FormatTimestamp($startedAt) : ''));

        $events = (isset($session['events']) && is_array($session['events'])) ? $session['events'] : [];
        $motionCount = 0;
        $lastMovement = '-';
        $lines = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $eventType = (string) ($event['event'] ?? '');
            if ($eventType === 'motion') {
                $motionCount++;
                $lastMovement = (string) ($event['sensor'] ?? '-') . ' – ' . $this->FormatTimestamp((float) ($event['ts'] ?? 0));
            }
            $label = ($eventType === 'motion') ? 'Bewegung' : 'frei';
            $lines[] = sprintf(
                '%03d  %s  %s  %s',
                (int) ($event['seq'] ?? 0),
                $this->FormatTimestamp((float) ($event['ts'] ?? 0)),
                (string) ($event['sensor'] ?? '-'),
                $label
            );
        }

        $this->SetValue('MotionCount', $motionCount);
        $this->SetValue('LastMovement', $lastMovement);
        $this->SetValue('MotionLog', ($lines === []) ? '-' : implode("\n", $lines));

        if ($last !== []) {
            $reason = (string) ($last['endReason'] ?? '');
            $endedAt = (float) ($last['endedAt'] ?? 0);
            $this->SetValue(
                'LastAlarm',
                (string) ($last['id'] ?? '-') . (($endedAt > 0) ? ' – ' . $this->FormatTimestamp($endedAt) : '') . (($reason !== '') ? ' – ' . $reason : '')
            );
        }
    }

    private function SetAlarmControlsVisible(bool $Visible): void
    {
        $alarmID = $this->GetIDForIdent('AlarmActive');
        $ackID = $this->GetIDForIdent('Acknowledge');
        IPS_SetHidden($alarmID, !$Visible);
        IPS_SetHidden($ackID, !$Visible);
    }

    private function BuildSensorMap(): array
    {
        $decoded = json_decode($this->ReadPropertyString('Sensors'), true);
        if (!is_array($decoded)) {
            return [[], ['Sensorliste ist ungültig']];
        }

        $map = [];
        $errors = [];
        foreach ($decoded as $index => $row) {
            if (!is_array($row) || !(bool) ($row['Enabled'] ?? false)) {
                continue;
            }

            $variableID = (int) ($row['VariableID'] ?? 0);
            if ($variableID <= 0 || !IPS_VariableExists($variableID)) {
                $errors[] = 'Melderzeile ' . ($index + 1) . ': Variable fehlt';
                continue;
            }

            $variable = IPS_GetVariable($variableID);
            if ((int) $variable['VariableType'] !== VARIABLETYPE_BOOLEAN) {
                $errors[] = 'Melderzeile ' . ($index + 1) . ': keine Boolean-Variable';
                continue;
            }

            if (isset($map[(string) $variableID])) {
                $errors[] = 'Variable #' . $variableID . ' ist doppelt eingetragen';
                continue;
            }

            $name = trim((string) ($row['Name'] ?? ''));
            if ($name === '') {
                $name = IPS_GetName($variableID);
            }

            $map[(string) $variableID] = [
                'id' => $variableID,
                'name' => $name
            ];
        }

        return [$map, $errors];
    }

    private function UnregisterOldSensorMessages(): void
    {
        $old = json_decode($this->ReadAttributeString('RegisteredSensorIDs'), true);
        if (!is_array($old)) {
            $old = [];
        }

        foreach ($old as $sensorID) {
            $sensorID = (int) $sensorID;
            if ($sensorID <= 0) {
                continue;
            }
            $this->UnregisterMessage($sensorID, VM_UPDATE);
            $this->UnregisterMessage($sensorID, OM_UNREGISTER);
            $this->UnregisterReference($sensorID);
        }

        $this->WriteAttributeString('RegisteredSensorIDs', '[]');
    }

    private function ReadSensorMap(): array
    {
        $decoded = json_decode($this->GetBuffer('SensorMap'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function ReadSensorStates(): array
    {
        $decoded = json_decode($this->GetBuffer('SensorStates'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function AreAllSensorsClear(array $States): bool
    {
        if ($States === []) {
            return false;
        }

        foreach ($States as $state) {
            if ((bool) $state) {
                return false;
            }
        }
        return true;
    }

    private function SensorName(int $VariableID): string
    {
        $map = $this->ReadSensorMap();
        $key = (string) $VariableID;
        if (isset($map[$key]['name'])) {
            return (string) $map[$key]['name'];
        }
        return IPS_VariableExists($VariableID) ? IPS_GetName($VariableID) : ('#' . $VariableID);
    }

    private function ReadSession(string $Attribute): array
    {
        $decoded = json_decode($this->ReadAttributeString($Attribute), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function WriteSession(string $Attribute, array $Session): void
    {
        $this->WriteAttributeString($Attribute, $this->Encode($Session === [] ? new stdClass() : $Session));
    }

    private function Encode(mixed $Value): string
    {
        $encoded = json_encode($Value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new Exception('Interner JSON-Fehler: ' . json_last_error_msg());
        }
        return $encoded;
    }

    private function AcquireEngineLock(): bool
    {
        if (IPS_SemaphoreEnter($this->EngineSemaphoreName(), 2000)) {
            return true;
        }
        // Zweiter Versuch ohne sleep(): kritischer Bereich ist bewusst sehr kurz.
        return IPS_SemaphoreEnter($this->EngineSemaphoreName(), 3000);
    }

    private function EngineSemaphoreName(): string
    {
        return 'LCNAlarmanlage_' . $this->InstanceID;
    }

    private function ReportLockFailure(string $Context): void
    {
        $message = 'Semaphore konnte nicht übernommen werden: ' . $Context;
        IPS_LogMessage('LCN Alarmanlage #' . $this->InstanceID, $message);
        $this->SendDebug('LockFailure', $message, 0);
        $this->SetValue('Status', 'STÖRUNG – interner Kollisionsschutz');
    }

    private function IsValidTime(string $Time): bool
    {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $Time)) {
            return false;
        }
        return true;
    }

    private function IsInsideSchedule(string $From, string $To): bool
    {
        if ($From === $To) {
            // Gleichstand wird aus Sicherheitsgründen als AUS interpretiert, nicht als 24h scharf.
            return false;
        }

        $current = ((int) date('H')) * 60 + (int) date('i');
        $from = $this->TimeToMinutes($From);
        $to = $this->TimeToMinutes($To);

        if ($from < $to) {
            return $current >= $from && $current < $to;
        }
        return $current >= $from || $current < $to;
    }

    private function TimeToMinutes(string $Time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $Time));
        return $hour * 60 + $minute;
    }

    private function NextOccurrence(DateTimeImmutable $Now, string $Time): DateTimeImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $Time));
        $candidate = $Now->setTime($hour, $minute, 0);
        if ($candidate <= $Now) {
            $candidate = $candidate->modify('+1 day');
        }
        return $candidate;
    }

    private function FormatTimestamp(float $Timestamp): string
    {
        if ($Timestamp <= 0) {
            return '-';
        }

        $seconds = (int) floor($Timestamp);
        $milliseconds = (int) floor(($Timestamp - $seconds) * 1000);
        return date('d.m.Y H:i:s', $seconds) . sprintf('.%03d', $milliseconds);
    }
}
