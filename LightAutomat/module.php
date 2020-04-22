<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/traits.php';  // General helper functions

// CLASS LightAutomat
class LightAutomat extends IPSModule
{
    use DebugHelper;
    use EventHelper;

    // Schedule constant
    const SCHEDULE_NAME = 'Zeitplan';
    const SCHEDULE_IDENT = 'circuit_diagram';
    const SCHEDULE_SWITCH = [
        1 => ['Aktiv', 0x00FF00, ''],
        2 => ['Inaktiv', 0xFF0000, ''],
    ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger('StateVariable', 0);
        $this->RegisterPropertyInteger('Duration', 10);
        $this->RegisterPropertyInteger('MotionVariable', 0);
        $this->RegisterPropertyInteger('PermanentVariable', 0);
        $this->RegisterPropertyBoolean('ExecScript', false);
        $this->RegisterPropertyInteger('ScriptVariable', 0);
        $this->RegisterPropertyInteger('EventVariable', 0);
        $this->RegisterPropertyBoolean('OnlyBool', false);
        $this->RegisterPropertyBoolean('OnlyScript', false);
        $this->RegisterTimer('TriggerTimer', 0, "TLA_Trigger(\$_IPS['TARGET']);");
    }

    public function ApplyChanges()
    {
        if ($this->ReadPropertyInteger('StateVariable') != 0) {
            $this->UnregisterMessage($this->ReadPropertyInteger('StateVariable'), VM_UPDATE);
        }

        //Never delete this line!
        parent::ApplyChanges();

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
        // $this->SendDebug('MessageSink', 'SenderId: '. $senderID . ' Data: ' . print_r($data, true), 0);
        switch ($message) {
            case VM_UPDATE:
                // Safty Check
                if ($senderID != $this->ReadPropertyInteger('StateVariable')) {
                    $this->SendDebug('MessageSink', 'SenderID: ' . $senderID . ' unbekannt!');
                    break;
                }
                // Dauerbetrieb, tue nix!
                $pid = $this->ReadPropertyInteger('PermanentVariable');
                if ($pid != 0 && GetValue($pid)) {
                    $this->SendDebug('MessageSink', 'Dauerbetrieb ist angeschalten!');
                    break;
                }
                // Wochenprogramm auswerten!
                $eid = $this->ReadPropertyInteger('EventVariable');
                if ($eid != 0) {
                    $state = $this->GetWeeklyScheduleInfo($eid);
                    if ($state['WeekPlanActiv'] == 1 && $state['ActionID'] == 2) {
                        $this->SendDebug('MessageSink', 'Wochenprogramm hinterlegt und Zustand ist inaktiv!');
                        break;
                    }
                }

                if ($data[0] == true && $data[1] == true) { // OnChange auf TRUE, d.h. Angeschalten
                    $this->SendDebug('MessageSink', 'OnChange auf TRUE - Angeschalten');
                    $this->SetTimerInterval('TriggerTimer', 1000 * 60 * $this->ReadPropertyInteger('Duration'));
                } elseif ($data[0] == false && $data[1] == true) { // OnChange auf FALSE, d.h. Ausgeschalten
                    $this->SendDebug('MessageSink', 'OnChange auf FALSE - Ausgeschalten');
                    $this->SetTimerInterval('TriggerTimer', 0);
                } else { // OnChange - keine Zustandsaenderung
                    $this->SendDebug('MessageSink', 'OnChange unveraendert - keine Zustandsaenderung');
                }
            break;
          }
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
                    $this->SendDebug('Trigger', 'Bewegungsmelder aktiv, also nochmal!');

                    return;
                } else {
                    if ($this->ReadPropertyBoolean('OnlyBool') == true) {
                        SetValueBoolean($sv, false);
                    } else {
                        //$pid = IPS_GetParent($sv);
                        //$ret = @HM_WriteValueBoolean($pid, 'STATE', false); //Gerät ausschalten
                        $ret = @RequestAction($sv, false); //Gerät ausschalten
                        if ($ret === false) {
                            $this->SendDebug('Trigger', 'Gerät konnte nicht ausgeschalten werden (UNREACH)!');
                        }
                    }
                    $this->SendDebug('Trigger', 'StateVariable (#' . $sv . ') auf false geschalten!');
                }
            }
            // Script ausführen
            if ($this->ReadPropertyBoolean('ExecScript') == true) {
                if ($this->ReadPropertyInteger('ScriptVariable') != 0) {
                    if (IPS_ScriptExists($this->ReadPropertyInteger('ScriptVariable'))) {
                        $rs = IPS_RunScript($this->ReadPropertyInteger('ScriptVariable'));
                        $this->SendDebug('Script Execute: Return Value', $rs);
                    }
                } else {
                    $this->SendDebug('Trigger', 'Script #' . $this->ReadPropertyInteger('ScriptVariable') . ' existiert nicht!');
                }
            }
        } else {
            $this->SendDebug('Trigger', 'STATE schon FALSE - Timer löschen!');
        }
        $this->SetTimerInterval('TriggerTimer', 0);
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     *
     * TLA_Duration($id, $duration);
     *
     * @param int $duration Wartezeit einstellen.
     */
    public function Duration(int $duration)
    {
        IPS_SetProperty($this->InstanceID, 'Duration', $duration);
        IPS_ApplyChanges($this->InstanceID);
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
        $this->CreateWeeklySchedule($this->InstanceID, self::SCHEDULE_NAME, self::SCHEDULE_IDENT, self::SCHEDULE_SWITCH, -1);
    }
}
