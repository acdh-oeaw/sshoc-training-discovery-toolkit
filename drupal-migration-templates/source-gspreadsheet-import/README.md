# SSHOC Training Inventory

Script for importing sources from a gSpreadsheet (that is exported as csv)

Work done in SSHOC WP6: https://training-inventory.acdh-dev.oeaw.ac.at/
see also #15185

Workflow
* Export gSpreadsheet as csv to the file sourcedata.csv
* Download the necessary csv library: https://csv.thephpleague.com/9.0/installation/ (look into the source of convert_csv.php for detail information)
* Call the convert-script: convert_csv.php
* The convert-script creates three files: sources.csv (the main source entries), disciplines.csv (the values for the disciplines-taxonomy), and topics.csv (the values for the topics-taxonomy)
* Go to the Drupal administration page of importing a configuration (admin/config/development/configuration/single/import)
* Select as configuration type "Migration" and import the three migration configurations from the directory drupal-migration-templates/ one by one (the files are: source_data_csv.drupal.migration.conf, source_disciplines_csv.drupal.migration.conf, source_topics_csv.drupal.migration.conf)
* If you like to change something later on the migration configuration, go at first to the export (admin/config/development/configuration/single/import) and choose the configuration  that should be changed. it now has an additional line with an uuid. Take the export including the uuid, make the changes and once agina use the single item import (by using the uuid Drupal understands, that this is an update of an existing configuration)
* Now it is necessary to put the three by the convert-script created files into the sites/default/files/-folder of Drupal (that is public://), otherwise the migration will not find the input data
* After having all three migrations integrated, they should show up in the migration overview (/admin/structure/migrate/manage/) in the migration group "Source data"
* Either run the migrations from there or - better - use drush
* For drush call "drush mim [machine-name-of-migration]" (start first with the taxonomies "source_disciplines_csv" and "source_topics_csv" before running the last migration on creating the sources "source_data_csv")
* For doing a rollback of a migration call "drush mr [machine-name-of-migration]"
* After processing the workflow, the sources are available on the Drupal website.

The created files in the workflow process are available in this repository.