# Installation (DDEV, Drupal 11)
```bash
ddev start
ddev composer install
ddev composer require drush/drush drupal/hal
ddev drush site:install -y
ddev drush en rest serialization hal my_zoo -y
ddev drush cr
