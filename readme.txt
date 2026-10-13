NeDi Import - GLPI plugin
=========================

Imports network devices from NeDi (https://www.nedi.ch) into GLPI 11:

* switches (as Network equipment), with their ports and untagged VLAN (PVID)
* links between switches (from NeDi's "links" table)
* nodes seen on the access ports, as Computers with MAC address and IP address,
  cabled to the switch port they were seen on
* several nodes seen behind the same port are attached to an automatically
  created "<switch>-<port>-VirtualSwitch" (an unmanaged switch/hub)

Originally written by Sein Coray (2012). Ported to GLPI 11 and rewritten to read
the NeDi database directly instead of scraping NeDi's HTML pages.

Requirements
------------
* GLPI 11.x, PHP with the pdo_mysql extension
* NeDi 2.x (tested against the 2.4 database schema; the NeDi 1.x layout is also handled)
* Network access from the GLPI server to the NeDi MySQL/MariaDB database, with a
  read-only account (e.g. GRANT SELECT ON nedi.* TO 'glpi'@'glpi-host')

Installation
------------
Copy this directory to GLPI's plugins/ directory as "nediimport"
(plugins/nediimport/setup.php must exist), then Setup > Plugins > install and enable.

Usage
-----
Tools > NeDi import:
1. "NeDi connection and settings": database host/user/password, entity for new
   items, age limit for nodes. "Save and test connection" checks the access.
2. "Choose the switches to import" (unless "import every device" is enabled).
3. "Import data from NeDi" runs the import. The same import is available as the
   automatic action "Nediimport" (Setup > Automatic actions); it is disabled by
   default.

Notes
-----
* The import never deletes anything. A node that moved is re-cabled to its new
  port; items (switches, computers) are matched by name / MAC address.
* Computers created from NeDi nodes without a name are called unknown-<mac>.
* Ports on which NeDi has a switch-to-switch link are not scanned for nodes.
