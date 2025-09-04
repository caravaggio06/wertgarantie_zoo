<?php

declare(strict_types=1);

namespace Drupal\my_zoo\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

final class MyZooService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * IDs der Tiere (Bundle: my_animals) optional nach Habitat-Termname filtern.
   */
  public function loadAnimalIds(?string $habitatName = null): array {
    $query = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->condition('status', 1)
      ->condition('type', 'my_animals');

    if ($habitatName) {
      $terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties(['vid' => 'habitat', 'name' => $habitatName]);
      if (!$terms) {
        return [];
      }
      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = reset($terms);
      $query->condition('field_habitat.target_id', $term->id());
    }

    $query->sort('created', 'DESC');
    return array_values($query->execute());
  }

  /** Lädt mehrere Nodes. */
  public function loadAnimals(array $ids): array {
    return $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
  }

  /** Mappt einen Node in das geforderte JSON-Objekt. */
  public function mapAnimal(NodeInterface $node): array {
    $sci = $node->hasField('field_scientific_name') ? $node->get('field_scientific_name')->value : null;
    $gender = $node->hasField('field_gender') ? $node->get('field_gender')->value : null;
    $age = $node->hasField('field_age') ? (int) $node->get('field_age')->value : null;

    $birthIso = null;
    if ($node->hasField('field_birth_date') && !$node->get('field_birth_date')->isEmpty()) {
      $ts = $node->get('field_birth_date')->date?->getTimestamp();
      if ($ts) {
        $birthIso = $this->dateFormatter->format($ts, 'custom', \DateTime::ATOM);
      }
    }

    $photoUrl = null;
    if ($node->hasField('field_photo') && !$node->get('field_photo')->isEmpty()) {
      $file = $node->get('field_photo')->entity;
      if ($file) {
        $photoUrl = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      }
    }

    $habitats = [];
    if ($node->hasField('field_habitat')) {
      /** @var \Drupal\taxonomy\TermInterface[] $terms */
      $terms = $node->get('field_habitat')->referencedEntities();
      foreach ($terms as $t) {
        if ($t instanceof TermInterface) {
          $habitats[] = $t->label();
        }
      }
    }

    return [
      'id' => (int) $node->id(),
      'title' => $node->label(),
      'scientific_name' => $sci,
      'foto' => $photoUrl,
      'habitat' => $habitats,
      'gender' => $gender,
      'age' => $age,
      'birth_date' => $birthIso,
    ];
  }

  /** (Optional) einfache Statistik. */
  public function getStats(): array {
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('status', 1)
      ->condition('type', 'my_animals')
      ->execute();

    $countTotal = count($ids);
    $byHabitat = [];
    $ageSum = 0; $ageCount = 0;

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
    foreach ($nodes as $n) {
      if ($n->hasField('field_habitat')) {
        foreach ($n->get('field_habitat')->referencedEntities() as $term) {
          $label = $term->label();
          $byHabitat[$label] = ($byHabitat[$label] ?? 0) + 1;
        }
      }
      if ($n->hasField('field_age') && $n->get('field_age')->value !== null) {
        $ageSum += (int) $n->get('field_age')->value;
        $ageCount++;
      }
    }

    return [
      'count_total' => $countTotal,
      'count_by_habitat' => $byHabitat,
      'average_age' => $ageCount ? $ageSum / $ageCount : null,
    ];
  }

}
