# SSHOC Training Inventory

Script for importing sources from the TEI files of SSK scenarios (http://ssk.huma-num.fr/#/scenarios)

Work done in SSHOC WP6: https://training-inventory.acdh-dev.oeaw.ac.at/
see also #15185

Workflow
* Get the XML scenario files from the GitHub repo of the SSK (https://github.com/ParthenosWP4/SSK/tree/master/scenarios) and put them into the directory ssk_scenarios/ (defined in getSSKData::directory) 
* Download the necessary csv library: https://csv.thephpleague.com/9.0/installation/ (look into the source of convert_csv.php for detail information) and put it into a dedicated directory (see the convert-script for more information)
* Call the convert-script: getSSKData.php (check if getSSKData::trainingSourceId is correct set and if getSSKData::processGitHub is set to TRUE)
* The convert-script creates two files: ssk_data.csv (the items of the SSK source), and ssk_data_contributors.csv (the contributors of the SSK scenarios)
* Go to the Drupal administration page of importing a configuration (admin/config/development/configuration/single/import)
* Select as configuration type "Migration" and import the three migration configurations from the directory drupal-migration-templates/ one by one (the files are: ssk_data_csv.drupal.migration.conf, ssk_data_csv_paragraph_contributors.drupal.migration.conf, ssk_data_csv_paragraph_extent.drupal.migration.conf)
* If you like to change something later on the migration configuration, go at first to the export (admin/config/development/configuration/single/import) and choose the configuration  that should be changed. it now has an additional line with an uuid. Take the export including the uuid, make the changes and once agina use the single item import (by using the uuid Drupal understands, that this is an update of an existing configuration)
* Now it is necessary to put the two from the convert-script created files into the sites/default/files/-folder of Drupal (that is public://), otherwise the migration will not find the input data
* After having all three migrations integrated, they should show up in the migration overview (/admin/structure/migrate/manage/) in the migration group "Source data"
* Either run the migrations from there or - better - use drush
* For drush call "drush mim [machine-name-of-migration]" (start first with the paragraph extent "ssk_data_csv_paragraph_extent", the paragraph contributors "ssk_data_csv_paragraph_contributors", and finally the last migration on creating the items "ssk_data_csv")
* For doing a rollback of a migration call "drush mr [machine-name-of-migration]"
* After processing the workflow, the items are available on the Drupal website.

The created files in the workflow process are available in this repository.