# Test1 (Logik-Gatter)

Modul für IP-Symcon zur logischen Verknüpfung einer unbegrenzten Anzahl von Variablen.

### Funktionen
- **Dynamische Liste:** Beliebig viele Variablen können über eine Tabelle hinzugefügt und entfernt werden
- **Aktiv-Schalter:** Einzelne Variablen können flexibel aktiviert/deaktiviert werden, ohne sie zu löschen
- **Invertieren (NICHT):** Einzelne Eingangsvariablen können direkt negiert werden
- **Optionale Beschreibung:** Eigene Notizen für jeden Eingang
- **Unterstützte Verknüpfungen:**
  - **UND (AND)**: alle aktiven Variablen sind wahr
  - **ODER (OR)**: mindestens eine der aktiven Variablen ist wahr
  - **XOR (Genau eine wahr)**: exakt eine der aktiven Variablen ist wahr
  - **NAND (Nicht-UND)**: nicht alle aktiven Variablen sind wahr
  - **NOR (Nicht-ODER)**: keine der aktiven Variablen ist wahr (alle falsch)
  - **XOR (Parität)**: ungerade Anzahl der aktiven Variablen ist wahr
- **Echtzeit-Aktualisierung:** Automatische Neuberechnung bei Änderung/Aktualisierung einer überwachten Variable
- **Statusvariable `Ergebnis`:** Typ Boolean, Profil `~Switch` unterhalb der Instanz
- **Optionale externe Zielvariable:** Schreibt das Ergebnis bei Bedarf direkt in eine bestehende Variable
- **Manuelle Berechnung per Skript:** `TEST1_Calculate($InstanceID);`
