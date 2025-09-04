<?php

declare(strict_types=1);

namespace Drupal\my_zoo\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\my_zoo\Service\MyZooService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Controller für die My Zoo API.
 *
 * Endpunkte:
 * - GET /api/my_animals
 * - GET /api/my_animals?habitat=forest
 * - GET /api/my_animals/{id}
 * - GET /api/my_animals/{id}/hal      (HAL-JSON)
 * - GET /api/my_animals/stats         (optional)
 */
final class AnimalController implements ContainerInjectionInterface {

  public function __construct(
    private readonly MyZooService $service,
    private readonly SerializerInterface $serializer,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('my_zoo.service'),
      $container->get('serializer'),
    );
  }

  /**
   * GET /api/my_animals[?habitat=<name>]
   * Liefert alle Tiere im geforderten JSON-Format.
   */
  public function getAll(Request $request): CacheableJsonResponse {
    $habitat = $request->query->get('habitat') ?: null;

    $ids   = $this->service->loadAnimalIds($habitat);
    $nodes = $this->service->loadAnimals($ids);
    $items = array_map(fn($n) => $this->service->mapAnimal($n), $nodes);

    $response = new CacheableJsonResponse(['items' => array_values($items)]);

    $meta = (new CacheableMetadata())
      ->setCacheMaxAge(3600)
      ->setCacheContexts(['url.query_args:habitat', 'languages:language_content'])
      ->setCacheTags(['node_list', 'taxonomy_vocabulary:habitat']);

    foreach ($nodes as $n) {
      $meta->addCacheableDependency($n);
      if ($n->hasField('field_habitat')) {
        foreach ($n->get('field_habitat')->referencedEntities() as $term) {
          $meta->addCacheableDependency($term);
        }
      }
    }
    $response->addCacheableDependency($meta);

    return $response;
  }

  /**
   * GET /api/my_animals/{id}
   * Liefert genau ein Tier (Custom JSON).
   */
  public function getOne(int $id): CacheableJsonResponse {
    $nodes = $this->service->loadAnimals([$id]);
    $node  = $nodes ? reset($nodes) : null;

    if (!$node || $node->bundle() !== 'my_animals') {
      throw new NotFoundHttpException();
    }

    // (optional) Übersetzung
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if ($node->hasTranslation($langcode)) {
      $node = $node->getTranslation($langcode);
    }

    $item = $this->service->mapAnimal($node);

    $response = new CacheableJsonResponse(['items' => [$item]]);
    $meta = (new CacheableMetadata())
      ->setCacheMaxAge(3600)
      ->setCacheTags(['node:' . $node->id()])
      ->setCacheContexts(['languages:language_content'])
      ->addCacheableDependency($node);

    // Access-Metadaten übernehmen
    $access = $node->access('view', NULL, TRUE);
    $response->addCacheableDependency($access);
    $response->addCacheableDependency($meta);

    return $response;
}

  /**
   * GET /api/my_animals/{id}/hal
   * Liefert einen Node als HAL-JSON (drupal/hal erforderlich).
   *
   * Wichtig: Für Responses Cache via CacheableResponse + addCacheableDependency(),
   * NICHT CacheableMetadata::applyTo() verwenden (das erwartet Render-Arrays).
   */
  public function getOneHal(int $id): CacheableResponse {
    $nodes = $this->service->loadAnimals([$id]);
    $node  = $nodes ? reset($nodes) : null;

    if (!$node || $node->bundle() !== 'my_animals') {
      throw new NotFoundHttpException();
    }

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if ($node->hasTranslation($langcode)) {
      $node = $node->getTranslation($langcode);
    }

    // Access prüfen + cachen
    $access = $node->access('view', NULL, TRUE);
    if (!$access->isAllowed()) {
      throw new NotFoundHttpException();
    }

    $json = $this->serializer->serialize($node, 'hal_json');

    $response = new CacheableResponse($json, 200, [
      'Content-Type' => 'application/hal+json; charset=UTF-8',
    ]);

    $meta = (new CacheableMetadata())
      ->setCacheMaxAge(3600)
      ->setCacheTags(['node:' . $node->id()])
      ->setCacheContexts(['languages:language_content'])
      ->addCacheableDependency($node);

    $response->addCacheableDependency($meta);
    $response->addCacheableDependency($access);

    return $response;
  }

  /**
   * (Optional) GET /api/my_animals/stats
   * Kleine Statistik über alle Tiere.
   */
  public function getStats(): CacheableJsonResponse {
    $stats = $this->service->getStats();

    $response = new CacheableJsonResponse(['items' => [$stats]]);
    $meta = (new CacheableMetadata())
      ->setCacheMaxAge(600)
      ->setCacheTags(['node_list', 'taxonomy_term_list']);
    $response->addCacheableDependency($meta);

    return $response;
  }

}
