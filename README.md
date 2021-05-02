# Toolmatic Light Automat (Lichtautomat)

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-5.2-blue.svg)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-5.0.20210502-orange.svg)](https://github.com/Wilkware/IPSymconLightAutomat)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://github.com/Wilkware/IPSymconLightAutomat/workflows/Check%20Style/badge.svg)](https://github.com/Wilkware/IPSymconLightAutomat/actions)

Die *Toolmatic Bibliothek* ist eine kleine Tool-Sammlung im Zusammenhang mit HomeMatic/IP Geräten.  
Hauptsächlich beinhaltet sie kleine Erweiterung zur Automatisierung von Aktoren oder erleichtert das Steuern von Geräten bzw. bietet mehr Komfort bei der Bedienung.  
  
Der *Lichtautomat* überwacht und schaltet das Licht automatisch nach einer bestimmten Zeit wieder aus.

## Inhaltverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)
8. [Versionshistorie](#8-versionshistorie)

### 1. Funktionsumfang

* Überwacht und schaltet das Licht automatisch nach einer bestimmten Zeit wieder aus.
* Dabei wird der Schaltstatus eines HomeMatic Tasters (z.B. HM-LC-Sw1PBU-FM) überwacht.
* Bei Variablenänderung der Statusvariable (STATE)) wird ein Timer gestartet.
* Nach eingestellter Zeit wird der Staus wieder zurückgestellt ("STATE" = flase).
* Sollte das Licht schon vorher manuell aus geschalten worden sein, wird der Timer deaktiviert.
* Zusätzlich bzw. ausschließlich kann ein Script ausgeführt werden.
* Möglichkeit des manuellen Dauerbetriebes schaltbar über boolesche Variable, wenn **true** wird kein Timer gestartet.
* Hinterlegung eines Wochenplans zum gezielten Aktivieren bzw. Deaktivierung des Automaten.
* Modul mit Bewegungsmelder, wenn dieser aktiv ist wird der Timer immer wieder erneuert.
* Möglichkeit der Steuerung der Wartezeit über eigene Laufzeit-Variable (z.B. via WebFront).
* Statusvariable muss nicht von einer HM-Instanze sein, kann auch einfach eine boolsche Variable sein.
* Start der Wartezeit bei Aktivierung des Automaten via Wochenplan (Übergang Inaktiv zu Aktiv).

### 2. Voraussetzungen

* IP-Symcon ab Version 5.2

### 3. Software-Installation

* Über den Modul Store das Modul *Toolmatic Light Automat* installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/IPSymconLightAutomat` oder `git://github.com/Wilkware/IPSymconLightAutomat.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter 'Instanz hinzufügen' ist das _Lichtautomat_-Modul (Alias: _Treppenautomat_, _Tasterschaltung_) unter dem Hersteller '(Geräte)' aufgeführt.

__Konfigurationsseite__:

Einstellungsbereich:

> Geräte ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Schaltervariable                                 | Quellvariable, über welche der Automat getriggert wird. Meistens im Kanal 1 von HomeMatic Geräten zu finden und ist vom Typ boolean und hat den Namen "STATE" (z.B: wenn man die Geräte mit dem HomeMatic Configurator anlegen lässt.).
Bewegungsvariable                                | Statusvariable eines Bewegungsmelders (true = Anwesend; false = Abwesend).

> Zeitsteuerung ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Zeiteinheit                                      | Bestimmt ob Dauer in Sekunden, Minuten oder Stunden ausgewertet werden soll.
Einschaltdauer (Vorgabe)                         | Zeitdauer, bis das Licht(Aktor) wieder ausgeschaltet werden soll. Wird bei eigner Variable für Einschaltdauer (siehe Erweiterte Einstellungen) als Vorgabewert/Initialwert benutzt.
Zeitplan                                         | Wochenprogram, welches den Lichtautomaten zeitgesteuert aktiviert bzw. deaktiviert.

> Erweiterte Einstellungen ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Gleichzeitiges Ausführen eines Scriptes          | Auswahl eines Skriptes, welches zusätzlich ausgeführt werden soll (IPS_ExecScript).
Schaltervariable ist eine reine boolsche Variable| Schalter, ob die Statusvariable über RequestAction-Befehl geschaltet werden soll oder ob nur ein boolscher Switch gemacht werden soll.
Starte Einschaltdauer bei eingeschaltem Licht und Aktivierung über Schaltplan| Schalter, ob nach Aktivierung über Wochenplan der Automat starten soll.
Variable für Einstellung der Einschaltdauer anlegen | Schalter, ob eine Statusvariable für Einschaltdauer angelegt werden soll.
Variable für Aktivierung des Dauerbetriebes anlegen | Schalter, ob eine Statusvariable für Dauerbetrieb angelegt werden soll.

_Aktionsbereich:_

Aktion                  | Beschreibung
----------------------- | ---------------------------------
ZEITPLAN HINZUFÜGEN     | Es wird ein Wochenplan mit 2 Zuständen (Aktiv & Inaktiv) angelegt und in den Einstellung hinterlegt.

### 5. Statusvariablen und Profile

Die Statusvariablen werden unter Berücksichtigung der erweiterten Einstellungen angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Name                 | Typ          | Beschreibung
-------------------- | ------------ | ----------------
Dauerbetrieb         | Boolean      | Ein- und Ausschalten des Dauerbetriebes (z.B. bei Besuch oder Party's)
Einschaltdauer       | Integer      | Dauer der Wartezeit in Abhängigkeit der eingestellten Zeiteinheit
Zeitplan             | (Wochenplan) | Einstellen der Zeitpunkte für Aktivieren bzw. Deaktivieren des Automaten

Folgende Profile werden angelegt:

Name                 | Typ       | Beschreibung
-------------------- | --------- | ----------------
TLA.Seconds          | Integer   | Zeitraum von 1 bis 59 Sekunden
TLA.Minutes          | Integer   | Zeitraum von 1 bis 59 Minuten
TLA.Hours            | Integer   | Zeitraum von 1 bis 23 Stunden

### 6. WebFront

Es ist keine weitere Steuerung oder gesonderte Darstellung integriert.  
Der Dauerbetrieb kann über die Statusvariable "Dauerbetrieb" im WebFront realsiert werden.  
Die Wartezeit kann auch über die Statusvariable "Einschaltdauer" im Webfront realisiert werden.

### 7. PHP-Befehlsreferenz

```php
void TLA_Trigger(int $InstanzID);
```

Schaltet das Licht (den Actor) aus.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `TLA_Trigger(12345);`

```php
void TLA_Schedule(int $InstanzID, int x);
```

Wird vom Wochenplan aufgerufen und dient der internen Prozessverarbeitung.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `TLA_Schedule(12345, 1);`

```php
void TLA_CreateSchedule(int $InstanzID);
```

Wird vom Konfigurationsformular aufgerufen und erzeugt den Wochenplan.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `TLA_CreateSchedule(12345);`

### 8. Versionshistorie

v5.0.20210502

* _NEU_: Umstellung auf Statusvariablen für Einschaltdauer und Dauerbetrieb
* _NEU_: Wartezeit jetzt frei konfigurierbar (Sekunden, Minuten oder Stunden)
* _NEU_: Check ob Licht nach Aktivierung des Automaten über Wochenplan ausgeschalten werden soll
* _FIX_: Funktion "TLA_Duration" entfernt (wegen Nutzung von IPS_SetProperty/ IPS_ApplyChanges)
* _FIX_: Konfigurationsformular vereinheitlicht bzw. vereinfacht
* _FIX_: Interne Bibliotheken überarbeitet

v4.0.20200421

* _NEU_: Zeitplan hinzugefügt
* _NEU_: Unterstützung für die Erstellung eines Wochenplans
* _FIX_: Interne Bibliotheken überarbeitet
* _FIX_: Dokumentation überarbeitet

v3.3.20190818

* _NEU_: Umstellung für Module Store
* _FIX_: Dokumentation überarbeitet

v3.2.20170322

* _FIX_: Anpassungen für IPS Version 5

v3.1.20170120

* _FIX_: Korrekte Auswertung der Schaltvariable.

v3.0.20170109

* _NEU_: Dauerbetrieb miitels hinterlegter boolean Variable, wenn _true_ wird kein Timer gestartet.
* _NEU_: Modul mit Bewegungsmelder, wenn dieser aktiv ist wird der Timer immer wieder erneuert.
* _NEU_: Über die Funktion _TLA_Duration(id, minuten)_ kann die Wartezeit via Script (WebFront) gesetzt werden.

v2.0.20170101

* _FIX_: Umstellung auf Nachrichten (RegisterMessage/MessageSink)
* _NEU_: Erweiterung zum Ausführen eines Scriptes

v1.0.20161220

* _NEU_: Initialversion

## Entwickler

* Heiko Wilknitz ([@wilkware](https://github.com/wilkware))

## Spenden

Die Software ist für die nicht kommzerielle Nutzung kostenlos, Schenkungen als Unterstützung für den Entwickler bitte hier:

[![License](https://img.shields.io/badge/Einfach%20spenden%20mit-PayPal-blue.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

[![Licence](https://licensebuttons.net/i/l/by-nc-sa/transparent/00/00/00/88x31-e.png)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
