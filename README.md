# My Zoo – Drupal 11 + HAL (DDEV)

Kurzanleitung für das Projekt-Setup mit DDEV. Das Modul **`my_zoo`** liefert JSON-REST und zusätzlich einen HAL-Endpoint (HAL ist in Drupal 11 ein **Contrib-Modul**).

## Voraussetzungen
- Docker Desktop (WSL2-Integration, falls Windows)
- DDEV
- Optional: `jq` für JSON-Formatierung

## Setup
```bash
ddev start
ddev composer install
ddev composer require drush/drush drupal/hal
ddev drush site:install -y
ddev drush en rest serialization hal my_zoo -y
ddev drush cr
```

## Schnelltest

```bash
# (A) UI-Variante (am einfachsten)
# 1) Struktur → Taxonomie → „Habitat“ → Begriffe anlegen: forest, woodlands, scrub, grasslands, desert
# 2) Inhalt → Inhalt hinzufügen → My Animals
#    Titel, scientific name, gender, age, birth date, (optional Foto), Habitat auswählen → Speichern

# (B) CLI-Variante (Seeds per Drush; sicher via Heredoc)

# 1) Vokabular + Beispiel-Begriffe anlegen (idempotent)
cat <<'PHP' | ddev drush php:eval -
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

$vocab = Vocabulary::load('habitat');
if (!$vocab) {
  $vocab = Vocabulary::create(['vid'=>'habitat','name'=>'Habitat']);
  $vocab->save();
  echo "Vocabulary 'habitat' erstellt\n";
}

$defaults = ['forest','woodlands','scrub','grasslands','desert'];
$storage  = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
foreach ($defaults as $name) {
  $found = $storage->loadByProperties(['vid'=>'habitat','name'=>$name]);
  if (!$found) {
    Term::create(['vid'=>'habitat','name'=>$name])->save();
    echo "created: $name\n";
  } else {
    echo "ok: $name\n";
  }
}
PHP

# 2) Einen Beispiel-Node anlegen (My Animals)
cat <<'PHP' | ddev drush php:eval -
use Drupal\node\Entity\Node;

$n = Node::create([
  'type' => 'my_animals',
  'title' => 'Löwe',
  'field_scientific_name' => 'Panthera leo',
  'field_gender' => 'male',
  'field_age' => 5,
  'field_birth_date' => '2020-04-23',
  'status' => 1,
  'langcode' => 'de',
]);
$n->save();
echo "nid=" . $n->id() . "\n";
PHP

# 3) (optional) „forest“ an den neuesten My-Animals-Node hängen
cat <<'PHP' | ddev drush php:eval -
use Drupal\node\Entity\Node;

$term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
  ->loadByProperties(['vid'=>'habitat','name'=>'forest']);
$term = $term ? reset($term) : NULL;

$nids = \Drupal::entityQuery('node')->condition('type','my_animals')
  ->sort('nid','DESC')->range(0,1)->execute();

if ($term && $nids) {
  $n = Node::load(reset($nids));
  if ($n->hasField('field_habitat')) {
    $n->get('field_habitat')->appendItem(['target_id' => $term->id()]);
    $n->save();
    echo "OK: Node ".$n->id()." ↔ Term ".$term->id()." (".$term->label().")\n";
  } else {
    echo "Feld field_habitat fehlt\n";
  }
} else {
  echo "Kein Term/Node gefunden\n";
}
PHP

# ---- API-Tests ----

# Liste
curl -s http://my-zoo.ddev.site/api/my_animals | jq

# ID dynamisch holen (bricht sauber ab, wenn Liste leer ist)
ID=$(curl -fsS http://my-zoo.ddev.site/api/my_animals | jq -er '.items[0].id')

# Detail (JSON)
curl -s http://my-zoo.ddev.site/api/my_animals/$ID | jq

# HAL (HAL-JSON)
curl -s -H "Accept: application/hal+json" http://my-zoo.ddev.site/api/my_animals/$ID/hal | jq
