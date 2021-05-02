<?php

declare(strict_types=1);

// General helper functions
require_once __DIR__ . '/../libs/_traits.php';

// CLASS LightAutomat
class LightAutomat extends IPSModule
{
    use DebugHelper;
    use EventHelper;
    use ProfileHelper;

    // Schedule constant
    const SCHEDULE_ON = 1;
    const SCHEDULE_OFF = 2;
    const SCHEDULE_NAME = 'Zeitplan';
    const SCHEDULE_IDENT = 'circuit_diagram';
    const SCHEDULE_SWITCH = [
        self::SCHEDULE_ON  => ['Aktive', 0x00FF00, "TLA_Schedule(\$_IPS['TARGET'], \$_IPS['ACTION']);"],
        self::SCHEDULE_OFF => ['Inaktive', 0xFF0000, "TLA_Schedule(\$_IPS['TARGET'], \$_IPS['ACTION']);"],
    ];
    // Time Unites constant
    const TIME_SECONDS = 0;
    const TIME_MINUTES = 1;
    const TIME_HOURS = 2;
    const TIME_UNIT = [
        self::TIME_SECONDS  => ['TLA.Seconds', ' seconds', 1, 59, 1],
        self::TIME_MINUTES  => ['TLA.Minutes', ' minutes', 1, 59, 60],
        self::TIME_HOURS    => ['TLA.Hours', ' hours', 1, 23, 3600],
    ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        // Devices ...
        $this->RegisterPropertyInteger('StateVariable', 0);
        $this->RegisterPropertyInteger('MotionVariable', 0);
        // Time Control ...
        $this->RegisterPropertyInteger('TimeUnit', 1);
        $this->RegisterPropertyInteger('Duration', 10);
        $this->RegisterPropertyInteger('EventVariable', 0);
        // Advanced Settings ...
        $this->RegisterPropertyInteger('ScriptVariable', 0);
        $this->RegisterPropertyBoolean('OnlyScript', false);
        $this->RegisterPropertyBoolean('OnlyBool', false);
        $this->RegisterPropertyBoolean('CheckSchedule', true);
        $this->RegisterPropertyBoolean('CheckDuration', true);
        $this->RegisterPropertyBoolean('CheckPermanent', true);
        // Timer
        $this->RegisterTimer('TriggerTimer', 0, "TLA_Trigger(\$_IPS['TARGET']);");
    }

    /**
     * Destroy.
     */
    public function Destroy()
    {
        parent::Destroy();
    }

    /**
     * Configuration Form.
     *
     * @return JSON configuration string.
     */
    public function GetConfigurationForm()
    {
        // Read setup
        $unit = $this->ReadPropertyInteger('TimeUnit');
        // Debug output
        $this->SendDebug(__FUNCTION__, 'unit=' . $unit);
        // Check duration
        $suf = $this->Translate(self::TIME_UNIT[$unit][1]);
        $min = self::TIME_UNIT[$unit][2];
        $max = self::TIME_UNIT[$unit][3];
        // Get Form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Set min/max/suffix
        $form['elements'][3]['items'][0]['items'][1]['minimum'] = $min;
        $form['elements'][3]['items'][0]['items'][1]['maximum'] = $max;
        $form['elements'][3]['items'][0]['items'][1]['suffix'] = $suf;
        // Debug output
        //$this->SendDebug(__FUNCTION__, $form);
        return json_encode($form);
    }

    public function ApplyChanges()
    {
        if ($this->ReadPropertyInteger('StateVariable') != 0) {
            $this->UnregisterMessage($this->ReadPropertyInteger('StateVariable'), VM_UPDATE);
        }
        //Never delete this line!
        parent::ApplyChanges();
        // Profile
        foreach (self::TIME_UNIT as $key => $value) {
            $this->RegisterProfile(vtInteger, $value[0], 'Clock', '', $this->Translate($value[1]), $value[2], $value[3], 1, 0, null);
        }
        // Maintain variables
        $permanent = $this->ReadPropertyBoolean('CheckPermanent');
        $this->MaintainVariable('continuous_operation', $this->Translate('Continuous operation'), vtBoolean, '~Switch', 0, $permanent);
        if ($permanent) {
            $this->SetValueBoolean('continuous_operation', false);
            $this->EnableAction('continuous_operation');
        }
        $duration = $this->ReadPropertyBoolean('CheckDuration');
        $timeunit = $this->ReadPropertyInteger('TimeUnit');
        $this->MaintainVariable('duty_cycle', $this->Translate('Duty cycle'), vtInteger, self::TIME_UNIT[$timeunit][0], 1, $duration);
        $this->SendDebug(__FUNCTION__, 'Create duration: ' . $duration . ' Create perament: ' . $permanent, 0);
        if ($duration) {
            $time = $this->ReadPropertyInteger('Duration');
            $this->SetValueInteger('duty_cycle', $time);
            $this->EnableAction('duty_cycle');
        }
        //Create our trigger
        if (IPS_VariableExists($this->ReadPropertyInteger('StateVariable'))) {
            $this->RegisterMessage($this->ReadPropertyInteger('StateVariable'), VM_UPDATE);
        }
    }

    /**
     * Internal SDK funktion.
     * data[0] = new value
     * data[1] = value changed?
     * data[2] = old value
     * data[3] = timestamp.
     */
    public function MessageSink($timeStamp, $senderID, $message, $data)
    {
        // $this->SendDebug(__FUNCTION__, 'SenderId: '. $senderID . ' Data: ' . print_r($data, true), 0);
        switch ($message) {
            case VM_UPDATE:
                // Safty Check
                if ($senderID != $this->ReadPropertyInteger('StateVariable')) {
                    $this->SendDebug(__FUNCTION__, 'SenderID: ' . $senderID . ' unknown!');
                    break;
                }
                // Countinus operation?
                $permanent = $this->ReadPropertyBoolean('CheckPermanent');
                if ($permanent) {
                    $state = $this->GetValue('continuous_operation');
                    if ($state) {
                        $this->SendDebug(__FUNCTION__, 'Continuous operation is ON!');
                        break;
                    }
                }
                // Weekly schedule!
                $eid = $this->ReadPropertyInteger('EventVariable');
                if ($eid != 0) {
                    $state = $this->GetWeeklyScheduleInfo($eid);
                    if ($state['WeekPlanActiv'] == 1 && $state['ActionID'] == 2) {
                        $this->SendDebug(__FUNCTION__, 'Weekly schedule is stored but state is inaktive!');
                        break;
                    }
                }
                // Switch state?
                if ($data[0] == true && $data[1] == true) { // OnChange is TRUE => switched ON
                    $this->SendDebug(__FUNCTION__, 'OnChange is TRUE - ON');
                    $this->SetTimerInterval('TriggerTimer', $this->CalculateTimer());
                } elseif ($data[0] == false && $data[1] == true) { // OnChange is FALSE => switched OFF
                    $this->SendDebug(__FUNCTION__, 'OnChange is FALSE - OFF');
                    $this->SetTimerInterval('TriggerTimer', 0);
                } else { // OnChange - no chenges!
                    $this->SendDebug(__FUNCTION__, 'OnChange - nothing chenged!');
                }
            break;
          }
    }

    /**
     * RequestAction.
     *
     *  @param string $ident Ident.
     *  @param string $value Value.
     */
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, $ident . ' => ' . $value);
        // Ident == OnXxxxxYyyyy
        switch ($ident) {
            case 'OnTimeUnit':
                $this->OnTimeUnit($value);
            break;
            case 'continuous_operation':
                $this->SetValueBoolean($ident, $value);
            break;
            case 'duty_cycle':
                $this->SetValueInteger($ident, $value);
            break;

        }
        //return true;
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * TLA_Trigger($id);
     */
    public function Trigger()
    {
        $sv = $this->ReadPropertyInteger('StateVariable');
        if (GetValueBoolean($sv) == true) {
            if ($this->ReadPropertyBoolean('OnlyScript') == false) {
                $mid = $this->ReadPropertyInteger('MotionVariable');
                if ($mid != 0 && GetValue($mid)) {
                    $this->SendDebug(__FUNCTION__, 'Motion detection aktive, still resume!');
                    return;
                } else {
                    if ($this->ReadPropertyBoolean('OnlyBool') == true) {
                        SetValueBoolean($sv, false);
                    } else {
                        $ret = @RequestAction($sv, false); //Gerät ausschalten
                        if ($ret === false) {
                            $this->SendDebug(__FUNCTION__, 'Device could not be switched off (UNREACH)!');
                        }
                    }
                    $this->SendDebug(__FUNCTION__, 'StateVariable (#' . $sv . ') switched to FALSE!');
                }
            }
            // Script ausführen
            if ($this->ReadPropertyInteger('ScriptVariable') != 0) {
                if (IPS_ScriptExists($this->ReadPropertyInteger('ScriptVariable'))) {
                    $rs = IPS_RunScript($this->ReadPropertyInteger('ScriptVariable'));
                    $this->SendDebug(__FUNCTION__, 'Script Execute Return Value: ' . $rs);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Script #' . $this->ReadPropertyInteger('ScriptVariable') . ' does not exist!');
                }
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'STATE already on FALSE - delete Timer!');
        }
        $this->SetTimerInterval('TriggerTimer', 0);
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * @param integer $vaue Action value (ON=1, OFF=2)
     */
    public function Schedule(int $value)
    {
        $this->SendDebug(__FUNCTION__, 'Value: ' . $value);
        // Check SChedule on Activate?
        $check = $this->ReadPropertyBoolean('CheckSchedule');
        if (!$check) {
            $this->SendDebug(__FUNCTION__, 'Check: nothing to do!');
            return;
        }
        // Is Activate ON
        if ($value == self::SCHEDULE_OFF) {
            $this->SendDebug(__FUNCTION__, 'Value: nothing to do!');
            return;
        }
        // Is Device State ON
        $sv = $this->ReadPropertyInteger('StateVariable');
        if (GetValueBoolean($sv) == true) {
            // Is a Timer active
            $interval = $this->GetTimerInterval('TriggerTimer');
            $this->SendDebug(__FUNCTION__, 'Timer: ' . $interval);
            if ($interval == 0) {
                $this->SendDebug(__FUNCTION__, 'State is TRUE and no Timer ON');
                $this->SetTimerInterval('TriggerTimer', $this->CalculateTimer());
            }
        }
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     *
     * TLA_CreateSchedule($id);
     *
     */
    public function CreateSchedule()
    {
        $eid = $this->CreateWeeklySchedule($this->InstanceID, self::SCHEDULE_NAME, self::SCHEDULE_IDENT, self::SCHEDULE_SWITCH, -1);
        if ($eid !== false) {
            $this->UpdateFormField('EventVariable', 'value', $eid);
        }
    }

    /**
     * Import death days data.
     *
     * @param string $value unit and value of duration.
     */
    protected function OnTimeUnit($value)
    {
        $this->SendDebug(__FUNCTION__, $value);
        $data = unserialize($value);
        // min/max/suffix
        $suf = $this->Translate(self::TIME_UNIT[$data['unit']][1]);
        $min = self::TIME_UNIT[$data['unit']][2];
        $max = self::TIME_UNIT[$data['unit']][3];
        // Set min/max/suffix
        $this->UpdateFormField('Duration', 'minimum', $min);
        $this->UpdateFormField('Duration', 'maximum', $max);
        $this->UpdateFormField('Duration', 'suffix', $suf);
        // Check Value
        $value = $data['value'];
        if ($value > $max) {
            $value = 10; //default: 10
            $this->UpdateFormField('Duration', 'value', $value);
        }
    }

    /**
     * Calculate duration timer.
     *
     * @return int   Timer intervall in milliseconds
     */
    private function CalculateTimer()
    {
        $interval = 0;
        $unit = $this->ReadPropertyInteger('TimeUnit');
        $duration = $this->ReadPropertyBoolean('CheckDuration');
        if ($duration) {
            $time = $this->GetValue('duty_cycle');
            $interval = 1000 * self::TIME_UNIT[$unit][4] * $time;
        } else {
            $time = $this->ReadPropertyInteger('Duration');
            $interval = 1000 * self::TIME_UNIT[$unit][4] * $time;
        }
        return $interval;
    }

    /**
     * Update a boolean value.
     *
     * @param string $ident Ident of the boolean variable
     * @param bool   $value Value of the boolean variable
     */
    private function SetValueBoolean(string $ident, bool $value)
    {
        $id = $this->GetIDForIdent($ident);
        SetValueBoolean($id, $value);
    }

    /**
     * Update a string value.
     *
     * @param string $ident Ident of the string variable
     * @param string $value Value of the string variable
     */
    private function SetValueString(string $ident, string $value)
    {
        $id = $this->GetIDForIdent($ident);
        SetValueString($id, $value);
    }

    /**
     * Update a integer value.
     *
     * @param string $ident Ident of the integer variable
     * @param int    $value Value of the integer variable
     */
    private function SetValueInteger(string $ident, int $value)
    {
        $id = $this->GetIDForIdent($ident);
        SetValueInteger($id, $value);
    }
}
