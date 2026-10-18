<?php

declare(strict_types=1);

class Test1 extends IPSModule
{
    private const VM_UPDATE = 10603;

    public function Create(): void
    {
        // Diese Zeile nicht löschen
        parent::Create();

        // Rückwärtskompatibilität für alte Konfigurationen
        $this->RegisterPropertyInteger('Variable1', 0);
        $this->RegisterPropertyInteger('Variable2', 0);
        $this->RegisterPropertyInteger('Variable3', 0);

        // Dynamische Variablen-Liste
        $this->RegisterPropertyString('VariableList', '[]');

        // Logische Verknüpfung
        $this->RegisterPropertyInteger('Operation', 0);

        // Optionale externe Zielvariable
        $this->RegisterPropertyInteger('TargetVariable', 0);

        // Statusvariable registrieren
        $this->RegisterVariableBoolean('Result', $this->Translate('Ergebnis'), '~Switch', 10);
    }

    public function Destroy(): void
    {
        // Diese Zeile nicht löschen
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();

        // Bestehende Nachrichten und Referenzen zurücksetzen
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        $varList = $this->getEffectiveVariableList();

        // Prüfen, ob eine aktive Variable nicht mehr existiert
        $missingVar = false;
        $activeVars = [];

        foreach ($varList as $entry) {
            if (empty($entry['Active'])) {
                continue;
            }

            $varID = (int)($entry['VariableID'] ?? 0);
            if ($varID <= 0) {
                continue;
            }

            if (!IPS_VariableExists($varID)) {
                $missingVar = true;
                continue;
            }

            $activeVars[$varID] = true;
        }

        $targetVar = $this->ReadPropertyInteger('TargetVariable');
        if ($targetVar > 0 && !IPS_VariableExists($targetVar)) {
            $missingVar = true;
        }

        if ($missingVar) {
            $this->SetStatus(201); // Fehler: Mindestens eine Variable existiert nicht
            return;
        }

        if (empty($activeVars)) {
            $this->SetStatus(104); // Inaktiv: Mindestens eine aktive Variable erforderlich
            return;
        }

        // Referenzen und Nachrichten für Eingangsvariablen registrieren
        foreach (array_keys($activeVars) as $varID) {
            $this->RegisterReference($varID);
            $this->RegisterMessage($varID, self::VM_UPDATE);
        }

        // Referenz für Zielvariable registrieren
        if ($targetVar > 0 && IPS_VariableExists($targetVar)) {
            $this->RegisterReference($targetVar);
        }

        // Status auf aktiv setzen
        $this->SetStatus(102); // IS_ACTIVE

        // Initialen Wert berechnen
        $this->Calculate();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message === self::VM_UPDATE) {
            $this->SendDebug('MessageSink', sprintf('Aktualisierung von Variable %d empfangen', $SenderID), 0);
            $this->Calculate();
        }
    }

    /**
     * Berechnet die logische Verknüpfung aller aktiven Variablen und aktualisiert die Statusvariable sowie ggf. die Zielvariable.
     * Kann über TEST1_Calculate($InstanceID) auch per Skript ausgeführt werden.
     *
     * @return bool Das berechnete Ergebnis
     */
    public function Calculate(): bool
    {
        $varList = $this->getEffectiveVariableList();
        $operation = $this->ReadPropertyInteger('Operation');

        $values = [];
        $debugInfo = [];

        foreach ($varList as $entry) {
            if (empty($entry['Active'])) {
                continue;
            }

            $varID = (int)($entry['VariableID'] ?? 0);
            if ($varID <= 0 || !IPS_VariableExists($varID)) {
                continue;
            }

            $rawVal = (bool) GetValue($varID);
            $invert = !empty($entry['Invert']);
            $finalVal = $invert ? !$rawVal : $rawVal;

            $values[] = $finalVal;
            $debugInfo[] = sprintf('%d:%s%s', $varID, $rawVal ? '1' : '0', $invert ? '(inv)' : '');
        }

        if (empty($values)) {
            $this->SetValue('Result', false);
            $this->updateTargetVariable(false);
            $this->SendDebug('Calculate', 'Keine aktiven Variablen => Ergebnis: false', 0);
            return false;
        }

        $trueCount = 0;
        foreach ($values as $v) {
            if ($v) {
                $trueCount++;
            }
        }
        $totalCount = count($values);

        $result = match ($operation) {
            0 => ($trueCount === $totalCount),          // UND (AND): alle wahr
            1 => ($trueCount > 0),                      // ODER (OR): mindestens eine wahr
            2 => ($trueCount === 1),                    // XOR (Genau eine wahr)
            3 => ($trueCount !== $totalCount),          // NAND: nicht alle wahr
            4 => ($trueCount === 0),                    // NOR: keine wahr
            5 => (($trueCount % 2) === 1),              // XOR (Parität): ungerade Anzahl wahr
            default => false,
        };

        $this->SetValue('Result', $result);
        $this->updateTargetVariable($result);

        $opNames = [
            0 => 'UND',
            1 => 'ODER',
            2 => 'XOR (genau 1)',
            3 => 'NAND',
            4 => 'NOR',
            5 => 'XOR (Parität)'
        ];
        $opName = $opNames[$operation] ?? 'OP_' . $operation;

        $this->SendDebug(
            'Calculate',
            sprintf(
                '[%s] | %s (%d/%d wahr) => Ergebnis: %s',
                implode(', ', $debugInfo),
                $opName,
                $trueCount,
                $totalCount,
                $result ? 'true' : 'false'
            ),
            0
        );

        return $result;
    }

    /**
     * Schreibt das Ergebnis in die optionale externe Zielvariable, falls konfiguriert.
     */
    private function updateTargetVariable(bool $result): void
    {
        $targetVar = $this->ReadPropertyInteger('TargetVariable');
        if ($targetVar > 0 && IPS_VariableExists($targetVar)) {
            if (GetValueBoolean($targetVar) !== $result) {
                SetValueBoolean($targetVar, $result);
                $this->SendDebug('TargetVariable', sprintf('Zielvariable %d auf %s gesetzt', $targetVar, $result ? 'true' : 'false'), 0);
            }
        }
    }

    /**
     * Liest die Variablenliste aus oder migriert alte Einzel-Properties (Variable1..3), falls die Liste leer ist.
     *
     * @return array Die Variablen-Einträge
     */
    private function getEffectiveVariableList(): array
    {
        $json = $this->ReadPropertyString('VariableList');
        $list = json_decode($json, true);

        if (is_array($list) && !empty($list)) {
            return $list;
        }

        // Rückwärtskompatibilität: Falls VariableList noch leer ist, alte Properties prüfen
        $oldVars = [
            $this->ReadPropertyInteger('Variable1'),
            $this->ReadPropertyInteger('Variable2'),
            $this->ReadPropertyInteger('Variable3')
        ];

        $migratedList = [];
        foreach ($oldVars as $oldVarID) {
            if ($oldVarID > 0 && IPS_VariableExists($oldVarID)) {
                $migratedList[] = [
                    'Active'      => true,
                    'VariableID'  => $oldVarID,
                    'Invert'      => false,
                    'Description' => IPS_GetName($oldVarID)
                ];
            }
        }

        return $migratedList;
    }
}
